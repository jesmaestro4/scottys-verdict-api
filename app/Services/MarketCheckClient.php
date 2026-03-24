<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class MarketCheckClient
{
    public function searchPrivatePartyListings(array $criteria): array
    {
        $params = array_filter([
            'api_key' => config('services.marketcheck.key'),
            'rows' => min((int) ($criteria['rows'] ?? 10), 50),
            'start' => max((int) ($criteria['start'] ?? 0), 0),
            'radius' => min((int) ($criteria['radius'] ?? 100), 100),
            'sort_by' => $criteria['sort_by'] ?? 'price',
            'sort_order' => $criteria['sort_order'] ?? 'asc',
            'stats' => $criteria['stats'] ?? null,
            'make' => $criteria['make'] ?? null,
            'model' => $criteria['model'] ?? null,
            'year' => $criteria['year'] ?? null,
            'zip' => $criteria['zip'] ?? null,
            'latitude' => $criteria['latitude'] ?? null,
            'longitude' => $criteria['longitude'] ?? null,
            'car_type' => 'used',
        ], fn ($value): bool => $value !== null && $value !== '');

        return $this->request()
            ->get('/search/car/fsbo/active', $params)
            ->throw()
            ->json();
    }

    public function extractListings(array $payload): array
    {
        $listings = data_get($payload, 'listings')
            ?? data_get($payload, 'records')
            ?? data_get($payload, 'data.listings')
            ?? [];

        return is_array($listings) ? $listings : [];
    }

    public function extractPriceRange(array $payload, ?array $fallbackListings = null): array
    {
        $stats = data_get($payload, 'stats.price')
            ?? data_get($payload, 'stats.price_stats')
            ?? data_get($payload, 'price_stats')
            ?? [];

        $min = data_get($stats, 'min')
            ?? data_get($stats, 'min_price')
            ?? data_get($stats, 'MinValue');
        $max = data_get($stats, 'max')
            ?? data_get($stats, 'max_price')
            ?? data_get($stats, 'MaxValue');
        $count = data_get($stats, 'count')
            ?? data_get($stats, 'listing_count')
            ?? data_get($stats, 'CountValue');

        $fallbackListings ??= $this->extractListings($payload);

        if (($min === null || $max === null) && $fallbackListings !== []) {
            $prices = collect($fallbackListings)
                ->map(fn (array $listing): ?int => $this->toInt(data_get($listing, 'price')))
                ->filter()
                ->values();

            $min = $min ?? $prices->min();
            $max = $max ?? $prices->max();
            $count = $count ?? $prices->count();
        }

        return [
            'min' => $min !== null ? (int) $min : null,
            'max' => $max !== null ? (int) $max : null,
            'listing_count' => $count !== null ? (int) $count : 0,
            'currency' => 'USD',
        ];
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.marketcheck.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(4)
            ->timeout(8)
            ->withOptions(['verify' => (bool) config('services.marketcheck.verify_ssl', true)]);

        if ($secret = config('services.marketcheck.secret')) {
            $request = $request->withHeaders(['X-Api-Secret' => $secret]);
        }

        return $request;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) Arr::get(['value' => $value], 'value');
    }
}
