<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'best_offer' => [
                'id' => $this->best_offer_id,
                'supplier' => $this->best_offer_supplier,
                'price' => $this->best_offer_price,
                'currency' => $this->best_offer_currency,
                'available_units' => $this->best_offer_available_units,
                'expires_at' => Carbon::parse($this->best_offer_expires_at)->toJSON(),
            ],
        ];
    }
}
