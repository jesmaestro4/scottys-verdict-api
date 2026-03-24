<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ListingSearchService;
use App\Services\ScottyVerdictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ScottyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/verdicts/search?make=Toyota&model=Corolla')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_pat_bearer_token_can_fetch_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('e2e-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_search_endpoint_returns_service_payload(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(ScottyVerdictService::class);
        $service->shouldReceive('searchVerdicts')
            ->once()
            ->with('Toyota', 'Corolla', 2020)
            ->andReturn([
                [
                    'guid' => 'car-guid',
                    'make' => 'TOYOTA',
                    'model' => 'Corolla',
                    'year' => 2020,
                    'vehicle_type_id' => 2,
                    'vehicle_type' => 'Car',
                    'manufacturer_name' => 'TOYOTA',
                    'trim' => null,
                    'body_class' => 'Passenger Car',
                    'drive_type' => null,
                    'fuel_type_primary' => null,
                    'engine_cylinders' => null,
                    'transmission_style' => null,
                    'tobuy' => true,
                    'total_mentions' => 7,
                    'videos' => [],
                    'images' => [],
                    'price_range' => ['min' => 12000, 'max' => 18000, 'listing_count' => 5, 'currency' => 'USD'],
                ],
            ]);
        $this->app->instance(ScottyVerdictService::class, $service);

        $this->actingAs($user, 'web')
            ->getJson('/api/verdicts/search?make=Toyota&model=Corolla&year=2020')
            ->assertOk()
            ->assertJsonPath('data.0.total_mentions', 7)
            ->assertJsonPath('data.0.tobuy', true);
    }

    public function test_top_best_endpoint_returns_paginated_payload(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(ScottyVerdictService::class);
        $service->shouldReceive('topVerdicts')
            ->once()
            ->with('good', 1, 10, null)
            ->andReturn([
                'data' => [],
                'meta' => ['page' => 1, 'page_size' => 10, 'total' => 0],
            ]);
        $this->app->instance(ScottyVerdictService::class, $service);

        $this->actingAs($user, 'web')
            ->getJson('/api/verdicts/top-best')
            ->assertOk()
            ->assertJsonPath('meta.page_size', 10);
    }

    public function test_video_mentions_endpoint_returns_filtered_payload(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(ScottyVerdictService::class);
        $service->shouldReceive('videoMentions')
            ->once()
            ->with('Toyota', 'Corolla', 2000, 2005, 25)
            ->andReturn([
                'data' => [[
                    'video_id' => 'abc123def45',
                    'comment' => 'sample mention',
                    'video_title' => 'Sample Video',
                    'timestamp' => '00:00:30',
                    'start_year' => 2003,
                    'end_year' => 2003,
                ]],
                'meta' => [
                    'make' => 'Toyota',
                    'model' => 'Corolla',
                    'start_year' => 2000,
                    'end_year' => 2005,
                    'limit' => 25,
                ],
            ]);
        $this->app->instance(ScottyVerdictService::class, $service);

        $this->actingAs($user, 'web')
            ->getJson('/api/verdicts/video-mentions?make=Toyota&model=Corolla&start_year=2000&end_year=2005&limit=25')
            ->assertOk()
            ->assertJsonPath('data.0.video_id', 'abc123def45')
            ->assertJsonPath('meta.make', 'Toyota');
    }

    public function test_nearby_listings_endpoint_returns_service_payload(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(ListingSearchService::class);
        $service->shouldReceive('findNearbyListings')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool => ($payload['zip'] ?? null) === '75001'))
            ->andReturn([
                'data' => [[
                    'listing_id' => 'abc123',
                    'heading' => '2020 Toyota Corolla',
                    'price' => 14500,
                    'miles' => 72000,
                    'distance_miles' => 4.2,
                    'vdp_url' => 'https://example.test/listing/abc123',
                    'seller_type' => 'fsbo',
                    'inventory_type' => 'private',
                    'source' => 'marketcheck',
                    'zip' => '75001',
                    'latitude' => 32.9618,
                    'longitude' => -96.8292,
                    'build' => [
                        'year' => 2020,
                        'make' => 'Toyota',
                        'model' => 'Corolla',
                        'trim' => 'LE',
                        'car_guid' => 'car-guid',
                    ],
                ]],
                'meta' => ['count' => 1, 'radius_miles' => 100, 'source' => 'marketcheck'],
            ]);
        $this->app->instance(ListingSearchService::class, $service);

        $this->actingAs($user, 'web')
            ->getJson('/api/listings/near-me?zip=75001')
            ->assertOk()
            ->assertJsonPath('data.0.listing_id', 'abc123');
    }
}
