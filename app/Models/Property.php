<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'city'];

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function scopeWithBestOffer(Builder $query, string $checkIn, string $checkOut, int $guests, ?string $city = null): Builder
    {
        $rankedOffers = Offer::query()
            ->select([
                'id',
                'property_id',
                'supplier_id',
                'price',
                'currency',
                'available_units',
                'expires_at',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY property_id ORDER BY price ASC, id ASC) as rn'),
            ])
            ->where('check_in', $checkIn)
            ->where('check_out', $checkOut)
            ->where('max_guests', '>=', $guests)
            ->where('available_units', '>', 0)
            ->where('expires_at', '>', now());

        $bestOffers = DB::query()
            ->fromSub($rankedOffers, 'ranked')
            ->where('rn', 1);

        return $query
            ->select([
                'properties.*',
                'best.id as best_offer_id',
                'suppliers.code as best_offer_supplier',
                'best.price as best_offer_price',
                'best.currency as best_offer_currency',
                'best.available_units as best_offer_available_units',
                'best.expires_at as best_offer_expires_at',
            ])
            ->joinSub($bestOffers, 'best', 'properties.id', '=', 'best.property_id')
            ->join('suppliers', 'suppliers.id', '=', 'best.supplier_id')
            ->when($city, fn(Builder $q) => $q->where('properties.city', $city))
            ->orderBy('best_offer_price')
            ->orderBy('properties.id');
    }
}
