<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times to attempt the job before giving up.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retry attempts.
     */
    public int $backoff = 10;

    /**
     * Seconds allowed for a single attempt before it's considered timed out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param  array<int, array<string, mixed>>  $offers
     */
    public function __construct(
        public Import $import,
        public array $offers,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->import->update([
            'status' => ImportStatus::Processing,
            'processed_offers' => 0,
            'error' => null,
            'completed_at' => null,
        ]);

        foreach ($this->offers as $offerData) {
            $this->processOffer($offerData);
        }

        $this->import->update([
            'status' => ImportStatus::Completed,
            'error' => null,
            'completed_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->import->update([
            'status' => ImportStatus::Failed,
            'error' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $offerData
     */
    private function processOffer(array $offerData): void
    {
        DB::transaction(function () use ($offerData): void {
            $property = Property::firstOrCreate(
                ['code' => $offerData['property']['code']],
                [
                    'name' => $offerData['property']['name'],
                    'city' => $offerData['property']['city'],
                ]
            );

            Offer::updateOrCreate(
                [
                    'supplier_id' => $this->import->supplier_id,
                    'external_id' => $offerData['external_id'],
                ],
                [
                    'property_id' => $property->id,
                    'import_id' => $this->import->id,
                    'check_in' => $offerData['check_in'],
                    'check_out' => $offerData['check_out'],
                    'max_guests' => $offerData['max_guests'],
                    'price' => $offerData['price'],
                    'currency' => $offerData['currency'],
                    'available_units' => $offerData['available_units'],
                    'expires_at' => $offerData['expires_at'],
                ]
            );

            $this->import->increment('processed_offers');
        });
    }
}
