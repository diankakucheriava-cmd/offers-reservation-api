<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'client_reference' => $this->faker->unique()->uuid(),
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->safeEmail(),
        ];
    }
}
