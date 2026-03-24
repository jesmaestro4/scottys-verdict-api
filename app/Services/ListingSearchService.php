<?php

namespace App\Services;

use App\Repositories\ListingRepository;
use Illuminate\Support\Str;
use Throwable;

class ListingSearchService
{
    public function __construct(
        private readonly ListingRepository $listings,
        private readonly CarProfileService $cars,
        private readonly MarketCheckClient $marketCheck,
    ) {
    }

    public function findNearbyListings(array $criteria): array
    {
        $radius = min((int) ($criteria['radius'] ?? 100), 100);
        $pageSize = min((int) ($criteria['page_size'] ?? 20), 50);

        $localResults = $this->listings->searchNearby(array_merge($criteria, ['radius' => $radius]), $pageSize);

        if ($localResults->isNotEmpty()) {
            return [
                'data' => $localResults->map(fn (array $row): array => $this->formatCachedListing($row))->all(),
                'meta' => [
                    'count' => $localResults->count(),
                    'radius_miles' => $radius,
                    'source' => 'cache',
                ],
            ];
        }

        try {
            $payload = $this->marketCheck->searchPrivatePartyListings([
                'make' => $criteria['make'] ?? null,
                'model' => $criteria['model'] ?? null,
                'year' => $criteria['year'] ?? null,
                'zip' => $criteria['zip'] ?? null,
                'latitude' => $criteria['latitude'] ?? null,
                'longitude' => $criteria['longitude'] ?? null,
                'radius' => $radius,
                'rows' => $pageSize,
                'sort_by' => 'price',
                'sort_order' => 'asc',
            ]);
        } catch (Throwable) {
            // Provider timeout/outage fallback: return cached matches by make/model/year
            // without strict geo requirement so endpoint remains responsive.
            $fallback = $this->listings->searchNearby([
                'make' => $criteria['make'] ?? null,
                'model' => $criteria['model'] ?? null,
                'year' => $criteria['year'] ?? null,
            ], $pageSize);

            return [
                'data' => $fallback->map(fn (array $row): array => $this->formatCachedListing($row))->all(),
                'meta' => [
                    'count' => $fallback->count(),
                    'radius_miles' => $radius,
                    'source' => 'cache-fallback',
                ],
            ];
        }

        $cachedRows = [];
        $responseItems = [];

        foreach ($this->marketCheck->extractListings($payload) as $listing) {
            $make = (string) (data_get($listing, 'build.make') ?? data_get($listing, 'make') ?? '');
            $model = (string) (data_get($listing, 'build.model') ?? data_get($listing, 'model') ?? '');
            $year = data_get($listing, 'build.year') ?? data_get($listing, 'year');

            if ($make === '' || $model === '') {
                continue;
            }

            $car = $this->cars->ensureCachedCar($make, $model, $year !== null ? (int) $year : null);

            $listingId = (string) (data_get($listing, 'id') ?? data_get($listing, 'listing_id') ?? Str::uuid());
            $cachedRows[] = [
                'ListingId' => $listingId,
                'car_GUID' => $car['Guid'],
                'Vin' => data_get($listing, 'vin'),
                'Heading' => data_get($listing, 'heading') ?? trim($year.' '.$make.' '.$model),
                'Price' => data_get($listing, 'price'),
                'Miles' => data_get($listing, 'miles'),
                'DataSource' => data_get($listing, 'source'),
                'VdpUrl' => data_get($listing, 'vdp_url'),
                'SellerType' => data_get($listing, 'seller_type', 'fsbo'),
                'InventoryType' => data_get($listing, 'inventory_type', 'private'),
                'Source' => data_get($listing, 'source'),
                'MediaPhotoLinks' => json_encode(data_get($listing, 'photo_links') ?? data_get($listing, 'media.photo_links') ?? []),
                'McLatitude' => data_get($listing, 'dealer.latitude') ?? data_get($listing, 'seller.latitude') ?? data_get($listing, 'latitude'),
                'McLongitude' => data_get($listing, 'dealer.longitude') ?? data_get($listing, 'seller.longitude') ?? data_get($listing, 'longitude'),
                'McZip' => data_get($listing, 'dealer.zip') ?? data_get($listing, 'seller.zip') ?? data_get($listing, 'zip'),
                'BuildYear' => $year,
                'BuildMake' => $make,
                'BuildModel' => $model,
                'BuildTrim' => data_get($listing, 'build.trim') ?? data_get($listing, 'trim'),
                'BuildVehicleType' => data_get($listing, 'build.vehicle_type') ?? data_get($listing, 'vehicle_type'),
            ];

            $responseItems[] = [
                'listing_id' => $listingId,
                'heading' => data_get($listing, 'heading') ?? trim($year.' '.$make.' '.$model),
                'price' => data_get($listing, 'price') !== null ? (int) data_get($listing, 'price') : null,
                'miles' => data_get($listing, 'miles') !== null ? (int) data_get($listing, 'miles') : null,
                'distance_miles' => data_get($listing, 'dist') ?? data_get($listing, 'distance'),
                'vdp_url' => data_get($listing, 'vdp_url'),
                'seller_type' => data_get($listing, 'seller_type', 'fsbo'),
                'inventory_type' => data_get($listing, 'inventory_type', 'private'),
                'source' => data_get($listing, 'source'),
                'zip' => data_get($listing, 'dealer.zip') ?? data_get($listing, 'seller.zip') ?? data_get($listing, 'zip'),
                'latitude' => data_get($listing, 'dealer.latitude') ?? data_get($listing, 'seller.latitude') ?? data_get($listing, 'latitude'),
                'longitude' => data_get($listing, 'dealer.longitude') ?? data_get($listing, 'seller.longitude') ?? data_get($listing, 'longitude'),
                'build' => [
                    'year' => $year !== null ? (int) $year : null,
                    'make' => $make,
                    'model' => $model,
                    'trim' => data_get($listing, 'build.trim') ?? data_get($listing, 'trim'),
                    'car_guid' => $car['Guid'],
                ],
            ];
        }

        if ($cachedRows !== []) {
            $this->listings->cacheListings($cachedRows);
        }

        if ($responseItems === []) {
            $fallback = $this->listings->searchNearby([
                'make' => $criteria['make'] ?? null,
                'model' => $criteria['model'] ?? null,
                'year' => $criteria['year'] ?? null,
            ], $pageSize);

            if ($fallback->isNotEmpty()) {
                return [
                    'data' => $fallback->map(fn (array $row): array => $this->formatCachedListing($row))->all(),
                    'meta' => [
                        'count' => $fallback->count(),
                        'radius_miles' => $radius,
                        'source' => 'cache-fallback',
                    ],
                ];
            }
        }

        return [
            'data' => $responseItems,
            'meta' => [
                'count' => count($responseItems),
                'radius_miles' => $radius,
                'source' => 'marketcheck',
            ],
        ];
    }

    private function formatCachedListing(array $row): array
    {
        return [
            'listing_id' => $row['ListingId'],
            'heading' => $row['Heading'],
            'price' => $row['Price'] !== null ? (int) $row['Price'] : null,
            'miles' => $row['Miles'] !== null ? (int) $row['Miles'] : null,
            'distance_miles' => $row['distance_miles'] !== null ? round((float) $row['distance_miles'], 2) : null,
            'vdp_url' => $row['VdpUrl'],
            'seller_type' => $row['SellerType'],
            'inventory_type' => $row['InventoryType'],
            'source' => $row['Source'] ?? $row['DataSource'],
            'zip' => $row['DealerZip'] ?? $row['McZip'],
            'latitude' => $row['DealerLatitude'] ?? $row['McLatitude'],
            'longitude' => $row['DealerLongitude'] ?? $row['McLongitude'],
            'build' => [
                'year' => $row['BuildYear'] !== null ? (int) $row['BuildYear'] : null,
                'make' => $row['BuildMake'],
                'model' => $row['BuildModel'],
                'trim' => $row['BuildTrim'],
                'car_guid' => $row['car_GUID'],
            ],
        ];
    }
}
