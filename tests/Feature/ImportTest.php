<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier' => 'supplier-a',
            'external_import_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [
                [
                    'external_id' => 'offer-a-10001',
                    'property' => [
                        'code' => 'BCN-0001',
                        'name' => 'Apartment near Sagrada Familia',
                        'city' => 'Barcelona',
                    ],
                    'check_in' => '2026-10-10',
                    'check_out' => '2026-10-15',
                    'max_guests' => 4,
                    'price' => 72500,
                    'currency' => 'EUR',
                    'available_units' => 2,
                    'expires_at' => '2026-09-10T23:59:59Z',
                ],
            ],
        ], $overrides);
    }

    public function test_it_accepts_an_import_and_dispatches_a_job(): void
    {
        Queue::fake();
        Supplier::factory()->create(['code' => 'supplier-a']);

        $response = $this->postJson('/api/imports', $this->payload());

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_offers', 1);

        $this->assertDatabaseCount('imports', 1);
        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_it_rejects_an_unknown_supplier(): void
    {
        $response = $this->postJson('/api/imports', $this->payload(['supplier' => 'unknown-supplier']));

        $response->assertStatus(422);
        $this->assertDatabaseCount('imports', 0);
    }

    public function test_resubmitting_the_same_import_does_not_duplicate_or_redispatch(): void
    {
        Queue::fake();
        Supplier::factory()->create(['code' => 'supplier-a']);

        $first = $this->postJson('/api/imports', $this->payload());
        $second = $this->postJson('/api/imports', $this->payload());

        $first->assertStatus(202);
        $second->assertStatus(202);
        $this->assertSame($first->json('data.id'), $second->json('data.id'));

        $this->assertDatabaseCount('imports', 1);
        Queue::assertPushed(ProcessImportJob::class, 1);
    }

    public function test_the_job_creates_the_property_and_offer_and_completes_the_import(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => ImportStatus::Pending,
            'total_offers' => 1,
            'processed_offers' => 0,
        ]);
        $offers = $this->payload()['offers'];

        (new ProcessImportJob($import, $offers))->handle();

        $import->refresh();
        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(1, $import->processed_offers);
        $this->assertNotNull($import->completed_at);

        $this->assertDatabaseHas('properties', ['code' => 'BCN-0001', 'city' => 'Barcelona']);

        $property = Property::where('code', 'BCN-0001')->firstOrFail();
        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'property_id' => $property->id,
            'external_id' => 'offer-a-10001',
            'price' => 72500,
        ]);
    }

    public function test_the_job_updates_an_existing_offer_when_resent_in_a_new_import(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $firstImport = Import::factory()->create(['supplier_id' => $supplier->id]);
        $secondImport = Import::factory()->create(['supplier_id' => $supplier->id]);

        $offerData = $this->payload()['offers'];

        (new ProcessImportJob($firstImport, $offerData))->handle();

        $updatedOfferData = $offerData;
        $updatedOfferData[0]['price'] = 65000;
        $updatedOfferData[0]['available_units'] = 1;

        (new ProcessImportJob($secondImport, $updatedOfferData))->handle();

        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseHas('offers', [
            'supplier_id' => $supplier->id,
            'external_id' => 'offer-a-10001',
            'import_id' => $secondImport->id,
            'price' => 65000,
            'available_units' => 1,
        ]);
    }

    public function test_it_shows_the_import_status(): void
    {
        $supplier = Supplier::factory()->create(['code' => 'supplier-a']);
        $import = Import::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => ImportStatus::Completed,
            'total_offers' => 1,
            'processed_offers' => 1,
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.supplier', 'supplier-a')
            ->assertJsonPath('data.status', 'completed');
    }
}
