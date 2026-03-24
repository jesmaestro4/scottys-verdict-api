<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class CarImagesClient
{
    public function fetchSignedImageUrls(string $make, string $model, ?int $year): array
    {
        $apiKey = config('services.car_images.key');

        if ($apiKey === null || $apiKey === '') {
            return [];
        }

        $query = array_filter([
            'api_key' => $apiKey,
            'make' => $make,
            'model' => $model,
            'year' => $year,
        ], fn ($value): bool => $value !== null && $value !== '');

        if ($secret = config('services.car_images.secret')) {
            $query['secret'] = $secret;
        }

        $payload = Http::baseUrl(rtrim((string) config('services.car_images.base_url'), '/'))
            ->acceptJson()
            ->timeout(20)
            ->withOptions(['verify' => (bool) config('services.car_images.verify_ssl', true)])
            ->get('/api/v1/signed-url', $query)
            ->throw()
            ->json();

        if (is_string($payload)) {
            return [$payload];
        }

        $candidates = Collection::make([
            data_get($payload, 'url'),
            data_get($payload, 'signed_url'),
            data_get($payload, 'src'),
        ])
            ->merge(data_get($payload, 'urls', []))
            ->merge(data_get($payload, 'data', []))
            ->map(function (mixed $item): ?string {
                if (is_string($item)) {
                    return $item;
                }

                if (is_array($item)) {
                    return data_get($item, 'url') ?? data_get($item, 'signed_url');
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        return $candidates->all();
    }
}
