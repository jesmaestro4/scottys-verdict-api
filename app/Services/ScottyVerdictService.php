<?php

namespace App\Services;

use App\Repositories\VerdictRepository;
use Illuminate\Support\Collection;

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
            $groups = $this->verdicts->findGroupedVerdicts($source, $make, $model, $year);

            foreach ($groups as $group) {
                $results[] = $this->buildPayload($group, $toBuy);
            }
        }

        usort($results, static fn (array $a, array $b): int => ($b['total_mentions'] ?? 0) <=> ($a['total_mentions'] ?? 0));

        return $results;
    }

    public function verdictByGuid(string $guid): ?array
    {
        $good = $this->verdicts->groupedVerdictByGuid('good', $guid);
        $bad = $this->verdicts->groupedVerdictByGuid('bad', $guid);

        if ($good === null && $bad === null) {
            return null;
        }

        $goodMentions = (int) ($good['total_mentions'] ?? 0);
        $badMentions = (int) ($bad['total_mentions'] ?? 0);
        $toBuy = $goodMentions >= $badMentions;
        $primary = $toBuy ? ($good ?? $bad) : ($bad ?? $good);

        if ($primary === null) {
            return null;
        }

        $carPayload = $this->cars->resolveCarObjectByGuid($guid);

        if ($carPayload === null) {
            return null;
        }

        $startCandidates = array_values(array_filter([
            $good['start_year'] ?? null,
            $bad['start_year'] ?? null,
        ], static fn ($year): bool => is_int($year)));

        $endCandidates = array_values(array_filter([
            $good['end_year'] ?? null,
            $bad['end_year'] ?? null,
        ], static fn ($year): bool => is_int($year)));

        $videos = collect(array_merge($good['videos'] ?? [], $bad['videos'] ?? []))
            ->unique(fn (array $video): string => implode('|', [
                (string) ($video['video_id'] ?? ''),
                (string) ($video['timestamp'] ?? ''),
                (string) ($video['video_title'] ?? ''),
            ]))
            ->values()
            ->all();

        return array_merge(
            $carPayload,
            [
                'year' => $primary['year'] ?? ($carPayload['year'] ?? null),
                'start_year' => $startCandidates !== [] ? min($startCandidates) : null,
                'end_year' => $endCandidates !== [] ? max($endCandidates) : null,
                'tobuy' => $toBuy,
                'total_mentions' => $goodMentions + $badMentions,
                'videos' => $videos,
            ],
        );
    }

    public function topVerdicts(string $source, int $page, int $pageSize, ?int $vehicleTypeId): array
    {
        $items = [];

        foreach ($this->verdicts->topGroupedVerdicts($source) as $group) {
            $guid = isset($group['guid']) ? (string) $group['guid'] : null;
            $year = isset($group['year']) ? (int) $group['year'] : null;
            $make = (string) ($group['make'] ?? '');
            $model = (string) ($group['model'] ?? '');

            $images = $guid !== null
                ? [['id' => $guid, 'url' => route('api.images.car', ['carGuid' => $guid])]]
                : [];

            $payload = [
                'guid' => $guid,
                'make' => strtoupper($make),
                'model' => $model,
                'year' => $year,
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
                'images' => $images,
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

    public function vehicleTypes(): array
    {
        return [
            'data' => $this->verdicts->vehicleTypes()->all(),
        ];
    }

    public function videoMentions(string $make, string $model, ?int $startYear, ?int $endYear, int $limit = 50): array
    {
        return [
            'data' => $this->mergedVideoMentions($make, $model, $startYear, $endYear, $limit),
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

    private function mergedVideoMentions(string $make, string $model, ?int $startYear, ?int $endYear, int $limit): array
    {
        return collect(['good', 'bad'])
            ->flatMap(fn (string $source): array => $this->verdicts->videoMentionsByRange($source, $make, $model, $startYear, $endYear, $limit))
            ->unique(fn (array $video): string => implode('|', [
                (string) ($video['video_id'] ?? ''),
                (string) ($video['timestamp'] ?? ''),
                (string) ($video['video_title'] ?? ''),
            ]))
            ->values()
            ->take($limit)
            ->all();
    }
}
