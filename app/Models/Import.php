<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    /** @use HasFactory<\Database\Factories\ImportFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'external_import_id',
        'sent_at',
        'status',
        'total_offers',
        'processed_offers',
        'error',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'status' => ImportStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public static function createOrFirstPending(Supplier $supplier, string $externalImportId, string $sentAt, int $totalOffers): self
    {
        return static::createOrFirst(
            [
                'supplier_id' => $supplier->id,
                'external_import_id' => $externalImportId,
            ],
            [
                'sent_at' => $sentAt,
                'status' => ImportStatus::Pending,
                'total_offers' => $totalOffers,
                'processed_offers' => 0,
            ]
        );
    }
}
