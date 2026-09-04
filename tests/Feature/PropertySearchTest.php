<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    private const CHECK_IN = '2026-10-10';

    private const CHECK_OUT = '2026-10-15';

    private function makeOffer(Property $property, array $overrides = []): Offer
    {
        return Offer::factory()->create(array_merge([
            'supplier_id' => Supplier::factory(),
            'property_id' => $property->id,
            'check_in' => self::CHECK_IN,
            'check_out' => self::CHECK_OUT,
            'max_guests' => 4,
            'available_units' => 2,
            'expires_at' => now()->addDay(),
        ], $overrides));
    }

    public function test_it_returns_the_cheapest_offer_per_property(): void
    {
        $property = Property::factory()->create(['city' => 'Barcelona']);
        $this->makeOffer($property, ['price' => 90000]);
        $cheapest = $this->makeOffer($property, ['price' => 72500]);

        $response = $this->getJson('/api/properties?' . http_build_query([
            'city' => 'Barcelona',
            'check_in' => self::CHECK_IN,
            'check_out' => self::CHECK_OUT,
            'guests' => 2,
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.code', $property->code);
        $response->assertJsonPath('data.0.best_offer.id', $cheapest->id);
        $response->assertJsonPath('data.0.best_offer.price', 72500);
    }

    public function test_it_filters_by_city(): void
    {
        $barcelona = Property::factory()->create(['city' => 'Barcelona']);
        $madrid = Property::factory()->create(['city' => 'Madrid']);
        $this->makeOffer($barcelona);
        $this->makeOffer($madrid);

        $response = $this->getJson('/api/properties?' . http_build_query([
            'city' => 'Barcelona',
            'check_in' => self::CHECK_IN,
            'check_out' => self::CHECK_OUT,
            'guests' => 2,
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.city', 'Barcelona');
    }

    public function test_it_excludes_offers_with_mismatched_dates(): void
    {
        $property = Property::factory()->create();
        $this->makeOffer($property, ['check_in' => '2026-11-01', 'check_out' => '2026-11-05']);

        $response = $this->searchDefault();

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_it_excludes_offers_below_guest_capacity(): void
    {
        $property = Property::factory()->create();
        $this->makeOffer($property, ['max_guests' => 1]);

        $response = $this->searchDefault(['guests' => 2]);

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_it_excludes_offers_with_no_available_units(): void
    {
        $property = Property::factory()->create();
        $this->makeOffer($property, ['available_units' => 0]);

        $response = $this->searchDefault();

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_it_excludes_expired_offers(): void
    {
        $property = Property::factory()->create();
        $this->makeOffer($property, ['expires_at' => now()->subDay()]);

        $response = $this->searchDefault();

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_it_paginates_results_at_the_database_level(): void
    {
        foreach (range(1, 3) as $i) {
            $property = Property::factory()->create();
            $this->makeOffer($property, ['price' => 10000 * $i]);
        }

        $response = $this->searchDefault(['per_page' => 2]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('per_page'));
        $this->assertNotNull($response->json('next'));
        $this->assertNull($response->json('prev'));
    }

    private function searchDefault(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/properties?' . http_build_query(array_merge([
            'check_in' => self::CHECK_IN,
            'check_out' => self::CHECK_OUT,
            'guests' => 2,
        ], $overrides)));
    }
}
