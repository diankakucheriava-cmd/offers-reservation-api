# Offers Reservation API

Laravel 12 REST API for asynchronously importing housing offers from suppliers, searching the
cheapest currently valid offer per property, and safely reserving an offer.

## Requirements

- PHP 8.2+
- Composer
- Docker (used here to run MySQL 8; the app itself runs locally with your PHP install)

## Setup

```bash
git clone https://github.com/diankakucheriava-cmd/offers-reservation-api.git
cd offers-reservation-api

composer install
cp .env.example .env
php artisan key:generate

# Starts a MySQL 8 container (see compose.yaml). The app connects to it via
# 127.0.0.1:3306, using the credentials already set in .env.example.
docker compose up -d
```

## Migrations & seeders

```bash
php artisan migrate --seed
```

The seeder creates the two suppliers required by the task: `supplier-a` and `supplier-b`.

## Running the app

```bash
php artisan serve
```

The API is then available at `http://127.0.0.1:8000/api`.

## Queue worker

Imports are processed asynchronously via `QUEUE_CONNECTION=database`. Run a worker in a
separate terminal so `POST /api/imports` jobs actually get processed:

```bash
php artisan queue:work
```

(`php artisan queue:work --once` processes a single job and exits, useful for manual testing.)

`ProcessImportJob` retries up to 3 times (10s backoff) and allows up to 120s per attempt before
being considered timed out. If you pass `--timeout` to `queue:work` explicitly, keep it at 120
or higher so the worker doesn't kill a job before the job's own timeout does.

## Tests

```bash
php artisan test
```

Feature tests use the `offers_reservation_test` MySQL database (see `phpunit.xml`), created
automatically by `docker/mysql/create-testing-database.sh` when the MySQL container starts
for the first time.

## API overview

| Method | Endpoint                          | Description                                   |
|--------|------------------------------------|------------------------------------------------|
| POST   | `/api/imports`                     | Submit a supplier import (returns `202`)       |
| GET    | `/api/imports/{import}`            | Check the status of an import                  |
| GET    | `/api/properties`                  | Search properties by their cheapest valid offer|
| POST   | `/api/offers/{offer}/reservations` | Reserve an offer                               |

### Example: submit an import

```bash
curl -X POST http://127.0.0.1:8000/api/imports \
  -H "Content-Type: application/json" \
  -d '{
    "supplier": "supplier-a",
    "external_import_id": "import-2026-09-01-001",
    "sent_at": "2026-09-01T10:00:00Z",
    "offers": [
      {
        "external_id": "offer-a-10001",
        "property": {"code": "BCN-0001", "name": "Apartment near Sagrada Familia", "city": "Barcelona"},
        "check_in": "2026-10-10",
        "check_out": "2026-10-15",
        "max_guests": 4,
        "price": 72500,
        "currency": "EUR",
        "available_units": 2,
        "expires_at": "2026-09-10T23:59:59Z"
      }
    ]
  }'
```

### Example: search properties

```bash
curl "http://127.0.0.1:8000/api/properties?city=Barcelona&check_in=2026-10-10&check_out=2026-10-15&guests=2"
```

### Example: reserve an offer

```bash
curl -X POST http://127.0.0.1:8000/api/offers/1/reservations \
  -H "Content-Type: application/json" \
  -d '{"client_reference": "web-order-9f782b1c", "customer_name": "John Smith", "customer_email": "john@example.com"}'
```

## Import idempotency

Two things guarantee that resending the *same* import request never creates duplicates or
reprocesses data:

1. **`imports` unique constraint** on `(supplier_id, external_import_id)`. The controller calls
   `Import::createOrFirstPending()`, which attempts an `INSERT` and, if the unique constraint is
   violated (including under concurrent requests), falls back to fetching the existing row instead
   of failing. The `ProcessImportJob` is only dispatched when the import row was actually just
   created (`$import->wasRecentlyCreated`), so a resent request never re-queues processing.
2. **`offers` unique constraint** on `(supplier_id, external_id)`. Inside the job, each offer is
   written with `updateOrCreate()` keyed on that pair, so an offer that was already imported (even
   from a different `import_id`) is *updated* in place rather than duplicated, and its `import_id`
   is repointed to the most recent import that touched it.

Known limitations of this scheme (deliberate scope decisions, not oversights):

- **Each offer is processed in its own transaction**, not the whole import in one transaction. If
  offer 5 of 20 fails, offers 1–4 are already committed and visible even though the import ends up
  `failed`. An all-or-nothing import would require staging the offers separately and publishing
  them only once the whole batch succeeds — out of scope here.
- **A `failed` import is not automatically retried by resubmitting the same request.** Resending
  the same `(supplier, external_import_id)` finds the existing (failed) row and does not dispatch
  a new job, since the row already exists. The job itself does retry transient failures (`$tries`
  + `backoff` on `ProcessImportJob`, with `failed()` recording the final error), but a supplier
  wanting to force reprocessing after a permanent failure would need a new `external_import_id`.
- **No cross-import freshness check.** If an older import (by `sent_at`) arrives after a newer one
  has already updated the same offer, its data still overwrites the newer data — the task
  specification requires updating an existing offer's data on resubmission and does not describe
  out-of-order delivery, so no ordering/staleness rule was invented for it.
- **Cheapest-offer comparison assumes a single comparable currency** across all suppliers/offers
  for the same search; prices are compared as raw integers with no currency conversion.

## Protecting against double-booking the last unit

`POST /api/offers/{offer}/reservations` (`Offer::reserve()`) wraps the whole operation in a
database transaction and re-fetches the offer row with `lockForUpdate()` (`SELECT ... FOR UPDATE`).
This takes a row-level exclusive lock in MySQL: if two requests try to reserve the same offer at
the same time, the second request's `SELECT ... FOR UPDATE` blocks until the first transaction
commits (or rolls back). By the time the second request acquires the lock, it re-reads the
already-decremented `available_units` and re-validates availability/expiry before proceeding — so
only one of the two requests can succeed for the last unit; the other receives `409 Conflict`.
Without the lock, both requests could read `available_units = 1` concurrently and both decide to
book, resulting in overselling.

`client_reference` is validated as unique at the request level for a clear `422` in the common
case, and is also enforced unique at the database level as the final guard: if two concurrent
requests somehow pass validation with the same reference, `Offer::reserve()` catches the resulting
DB unique-constraint violation and turns it into a `409 Conflict` rather than a `500`.


## Design notes

- Prices are stored as integers in the currency's minor unit (e.g. cents) to avoid floating-point
  rounding issues.
- Foreign keys on `imports`, `offers`, and `reservations` use `restrictOnDelete()` rather than
  cascading deletes, since these are audit/booking records that should never disappear silently
  as a side effect of deleting a supplier, property, or offer.
- The cheapest-offer search (`GET /api/properties`) is done entirely in SQL using a window
  function (`ROW_NUMBER() OVER (PARTITION BY property_id ORDER BY price)`) joined back to
  `properties`, with pagination applied by Eloquent's query builder — no offers are loaded into
  PHP and grouped in memory.

