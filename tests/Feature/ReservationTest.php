<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_reference' => 'web-order-9f782b1c',
            'customer_name' => 'John Smith',
            'customer_email' => 'john@example.com',
        ], $overrides);
    }

    public function test_it_creates_a_reservation_and_decrements_available_units(): void
    {
        $offer = Offer::factory()->create(['available_units' => 2]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.client_reference', 'web-order-9f782b1c');

        $this->assertDatabaseHas('reservations', [
            'offer_id' => $offer->id,
            'client_reference' => 'web-order-9f782b1c',
        ]);
        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function test_the_second_reservation_for_the_last_unit_returns_a_conflict(): void
    {
        $offer = Offer::factory()->create(['available_units' => 1]);

        $first = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload());
        $second = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload(['client_reference' => 'web-order-second']));

        $first->assertStatus(201);
        $second->assertStatus(409);

        $this->assertSame(0, $offer->fresh()->available_units);
        $this->assertSame(1, Reservation::count());
    }

    public function test_an_expired_offer_cannot_be_reserved(): void
    {
        $offer = Offer::factory()->create([
            'available_units' => 2,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload());

        $response->assertStatus(409);
        $this->assertSame(2, $offer->fresh()->available_units);
    }

    public function test_a_sold_out_offer_cannot_be_reserved(): void
    {
        $offer = Offer::factory()->create(['available_units' => 0]);

        $response = $this->postJson("/api/offers/{$offer->id}/reservations", $this->payload());

        $response->assertStatus(409);
    }

    public function test_duplicate_client_reference_is_rejected(): void
    {
        $offerA = Offer::factory()->create(['available_units' => 2]);
        $offerB = Offer::factory()->create(['available_units' => 2]);

        $this->postJson("/api/offers/{$offerA->id}/reservations", $this->payload())->assertStatus(201);
        $response = $this->postJson("/api/offers/{$offerB->id}/reservations", $this->payload());

        $response->assertStatus(422);
    }

    public function test_a_client_reference_race_that_bypasses_form_validation_is_rejected_by_the_db_constraint(): void
    {
        $offerA = Offer::factory()->create(['available_units' => 5]);
        $offerB = Offer::factory()->create(['available_units' => 5]);

        $offerA->reserve($this->payload());

        $this->expectException(\App\Exceptions\DuplicateReservationException::class);
        $offerB->reserve($this->payload());
    }
}
