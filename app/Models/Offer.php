<?php

namespace App\Models;

use App\Exceptions\OfferUnavailableException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Offer extends Model
{
    /** @use HasFactory<\Database\Factories\OfferFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'property_id',
        'import_id',
        'external_id',
        'check_in',
        'check_out',
        'max_guests',
        'price',
        'currency',
        'available_units',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'expires_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isBookable(): bool
    {
        return $this->available_units > 0 && $this->expires_at->isFuture();
    }

    /**
     *
     * @param  array<string, mixed>  $customer
     *
     * @throws OfferUnavailableException
     */
    public function reserve(array $customer): Reservation
    {
        return DB::transaction(function () use ($customer): Reservation {
            $offer = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if (! $offer->isBookable()) {
                throw new OfferUnavailableException();
            }

            $offer->decrement('available_units');

            return $offer->reservations()->create($customer);
        });
    }
}
