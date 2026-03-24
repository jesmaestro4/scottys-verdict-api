<?php

namespace App\Services;

use App\Repositories\VerdictRepository;

class ScottyVerdictService
{
    public function __construct(
        private readonly VerdictRepository $verdicts,
        private readonly CarProfileService $cars,
    ) {
    }

    public function searchVerdicts(string $make, string $model, ?int $year): array
    {
        $results = [];

        foreach (['good' => true, 'bad' => false] as $source => $toBuy) {
            $group = $this->verdicts->findGroupedVerdict($source, $make, $model, $year);

            if ($group === null) {
                continue;
            }

            $results[] = $this->buildPayload($group, $toBuy);
        }

        return $results;
    }

    public function topVerdicts(string $source, int $page, int $pageSize, ?int $vehicleTypeId): array
    {
        $items = [];

        foreach ($this->verdicts->topGroupedVerdicts($source) as $group) {
            $payload = [
                'guid' => $group['guid'] ?? null,
                'make' => strtoupper((string) ($group['make'] ?? '')),
                'model' => (string) ($group['model'] ?? ''),
                'year' => isset($group['year']) ? (int) $group['year'] : null,
                'start_year' => $group['start_year'] ?? null,
                'end_year' => $group['end_year'] ?? null,
                'vehicle_type_id' => $group['vehicle_type_id'] ?? null,
                'vehicle_type' => $group['vehicle_type'] ?? null,
                'manufacturer_name' => $group['manufacturer_name'] ?? strtoupper((string) ($group['make'] ?? '')),
                'trim' => $group['trim'] ?? null,
                'body_class' => $group['body_class'] ?? null,
                'drive_type' => $group['drive_type'] ?? null,
                'fuel_type_primary' => $group['fuel_type_primary'] ?? null,
                'engine_cylinders' => $group['engine_cylinders'] ?? null,
                'transmission_style' => $group['transmission_style'] ?? null,
                'images' => array_map(fn (array $image): array => [
                    'id' => $image['id'],
                    'url' => route('api.images.show', ['carGuid' => $group['guid'], 'imageId' => $image['id']]),
                ], $group['images'] ?? []),
                'price_range' => ['min' => null, 'max' => null, 'listing_count' => 0, 'currency' => 'USD'],
                'tobuy' => $source === 'good',
                'total_mentions' => $group['total_mentions'],
                'videos' => $group['videos'] ?? [],
            ];

            if ($vehicleTypeId !== null && $payload['vehicle_type_id'] !== $vehicleTypeId) {
                continue;
            }

            $items[] = $payload;
        }

        $total = count($items);
        $offset = max($page - 1, 0) * $pageSize;

        return [
            'data' => array_slice($items, $offset, $pageSize),
            'meta' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
            ],
        ];
    }

    public function videoMentions(string $make, string $model, ?int $startYear, ?int $endYear, int $limit = 50): array
    {
        return [
            'data' => $this->verdicts->videoMentionsByRange('good', $make, $model, $startYear, $endYear, $limit),
            'meta' => [
                'make' => $make,
                'model' => $model,
                'start_year' => $startYear,
                'end_year' => $endYear,
                'limit' => $limit,
            ],
        ];
    }

    private function buildPayload(array $group, bool $toBuy, bool $enrich = true, bool $includeVideos = true): array
    {
        $carPayload = $enrich
            ? $this->cars->resolveCarObject($group['make'], $group['model'], $group['year'])
            : $this->cars->resolveCachedCarObject($group['make'], $group['model'], $group['year']);

        $payload = array_merge(
            $carPayload,
            [
                'tobuy' => $toBuy,
                'total_mentions' => $group['total_mentions'],
            ],
        );

        if ($includeVideos) {
            $payload['videos'] = $group['videos'];
        }

        return $payload;
    }
}
