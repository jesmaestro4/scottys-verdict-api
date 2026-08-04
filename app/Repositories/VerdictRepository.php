<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VerdictRepository
{
    private const WHERE_MAKE_LOWER = 'LOWER(Make) = ?';
    private const WHERE_MODEL_LOWER = 'LOWER(Model) = ?';

    private const TABLE_MAP = [
        'good' => 'verdict_goodcars',
        'bad' => 'verdict_badcars',
    ];

    public function vehicleTypes(): Collection
    {
        return DB::table('car_vehicletype')
            ->select([
                'VehicleTypeId as vehicle_type_id',
                'VehicleTypeName as vehicle_type_name',
                'CustomVehicleTypeName as custom_vehicle_type_name',
            ])
            ->whereNotNull('VehicleTypeId')
            ->orderBy('VehicleTypeId')
            ->get()
            ->map(fn (object $row): array => [
                'vehicle_type_id' => (int) $row->vehicle_type_id,
                'vehicle_type_name' => $row->vehicle_type_name !== null ? (string) $row->vehicle_type_name : null,
                'custom_vehicle_type_name' => $row->custom_vehicle_type_name !== null ? (string) $row->custom_vehicle_type_name : null,
            ]);
    }

    public function findGroupedVerdict(string $source, string $make, string $model, ?int $year): ?array
    {
        $groups = $this->findGroupedVerdicts($source, $make, $model, $year);

        return $groups->first();
    }

    public function findGroupedVerdicts(string $source, string $make, string $model, ?int $year): Collection
    {
        $rows = $this->baseQuery($source, $make, $model, $year)
            ->orderBy('v.Id')
            ->get(['v.Make', 'v.Model', 'v.StartYear', 'v.EndYear', 'v.YoutubeVideoId', 'v.Comment', 'v.VideoTitle', 'v.Timestamp']);

        if ($rows->isEmpty()) {
            return collect();
        }

        $groups = [];

        foreach ($rows as $row) {
            $startYear = $row->StartYear !== null ? (int) $row->StartYear : null;
            $endYear = $row->EndYear !== null ? (int) $row->EndYear : $startYear;
            $bucketKey = ($startYear ?? 0).'|'.($endYear ?? 0);

            if (!isset($groups[$bucketKey])) {
                $groups[$bucketKey] = [
                    'make' => (string) $row->Make,
                    'model' => (string) $row->Model,
                    'year' => $startYear ?? $endYear,
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                    'total_mentions' => 0,
                    'videos' => [],
                ];
            }

            $groups[$bucketKey]['total_mentions']++;
            $groups[$bucketKey]['videos'][] = [
                'video_id' => $row->YoutubeVideoId,
                'comment' => $row->Comment,
                'video_title' => $row->VideoTitle,
                'timestamp' => $row->Timestamp,
                'isGoodverdict' => $source === 'good',
            ];
        }

        return collect(array_values($groups))
            ->sortByDesc('total_mentions')
            ->values();
    }

    public function groupedVerdictByGuid(string $source, string $guid): ?array
    {
        $table = $this->tableFor($source);

        $row = DB::table($table.' as v')
            ->join('car_car as c', 'c.Guid', '=', 'v.car_GUID')
            ->join('car_make as m', 'm.MakeId', '=', 'c.MakeId')
            ->where('v.car_GUID', $guid)
            ->groupBy('v.car_GUID', 'm.MakeName', 'c.Model', 'c.ModelYear')
            ->first([
                'v.car_GUID as guid',
                'm.MakeName as make',
                'c.Model as model',
                'c.ModelYear as model_year',
                DB::raw('COUNT(*) as total_mentions'),
                DB::raw('MIN(v.StartYear) as start_year'),
                DB::raw('MAX(COALESCE(v.EndYear, v.StartYear)) as end_year'),
            ]);

        if ($row === null) {
            return null;
        }

        $videosByGuid = $this->topVideosForCarGuids($table, [$guid], 25, $source === 'good');

        return [
            'guid' => (string) $row->guid,
            'make' => (string) $row->make,
            'model' => (string) $row->model,
            'year' => $row->model_year !== null ? (int) $row->model_year : null,
            'start_year' => $row->start_year !== null ? (int) $row->start_year : null,
            'end_year' => $row->end_year !== null ? (int) $row->end_year : null,
            'total_mentions' => (int) $row->total_mentions,
            'videos' => $videosByGuid[$guid] ?? [],
        ];
    }

    public function topGroupedVerdicts(string $source, int $limit = 200): Collection
    {
        $table = $this->tableFor($source);

        $groups = DB::table($table.' as v')
            ->join('car_car as c', 'c.Guid', '=', 'v.car_GUID')
            ->leftJoin('car_make as m', 'm.MakeId', '=', 'c.MakeId')
            ->leftJoin('car_vehicletype as vt', 'vt.VehicleTypeId', '=', 'c.VehicleTypeId')
            ->whereNotNull('v.car_GUID')
            ->where('v.car_GUID', '<>', '')
            ->groupBy(
                'v.car_GUID',
                'm.MakeName',
                'c.Model',
                'c.ModelYear',
                'c.VehicleTypeId',
                'vt.VehicleTypeName',
                'vt.CustomVehicleTypeName',
                'c.ManufacturerName',
                'c.Trim',
                'c.BodyClass',
                'c.DriveType',
                'c.FuelTypePrimary',
                'c.EngineNumberOfCylinders',
                'c.TransmissionStyle'
            )
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get([
                'v.car_GUID as guid',
                'm.MakeName as make',
                'c.Model as model',
                'c.ModelYear as model_year',
                'c.VehicleTypeId as vehicle_type_id',
                'vt.VehicleTypeName as vehicle_type_name',
                'vt.CustomVehicleTypeName as custom_vehicle_type_name',
                'c.ManufacturerName as manufacturer_name',
                'c.Trim as trim',
                'c.BodyClass as body_class',
                'c.DriveType as drive_type',
                'c.FuelTypePrimary as fuel_type_primary',
                'c.EngineNumberOfCylinders as engine_cylinders',
                'c.TransmissionStyle as transmission_style',
                DB::raw('COUNT(*) as total_mentions'),
                DB::raw('MIN(v.StartYear) as start_year'),
                DB::raw('MAX(COALESCE(v.EndYear, v.StartYear)) as end_year'),
            ]);

        $carGuids = $groups->pluck('guid')->filter()->values()->all();
        $videosByGuid = $this->topVideosForCarGuids($table, $carGuids, 10, $source === 'good');
        $imagesByGuid = $this->imagesForCarGuids($carGuids);

        return $groups->map(function (object $row) use ($videosByGuid, $imagesByGuid): array {
            $guid = (string) $row->guid;

            return [
                'guid' => $guid,
                'make' => $row->make,
                'model' => $row->model,
                'start_year' => $row->start_year !== null ? (int) $row->start_year : null,
                'end_year' => $row->end_year !== null ? (int) $row->end_year : null,
                'year' => $row->model_year !== null ? (int) $row->model_year : ($row->start_year !== null ? (int) $row->start_year : null),
                'vehicle_type_id' => $row->vehicle_type_id !== null ? (int) $row->vehicle_type_id : null,
                'vehicle_type' => $row->custom_vehicle_type_name ?? $row->vehicle_type_name,
                'manufacturer_name' => $row->manufacturer_name,
                'trim' => $row->trim,
                'body_class' => $row->body_class,
                'drive_type' => $row->drive_type,
                'fuel_type_primary' => $row->fuel_type_primary,
                'engine_cylinders' => $row->engine_cylinders,
                'transmission_style' => $row->transmission_style,
                'total_mentions' => (int) $row->total_mentions,
                'videos' => $videosByGuid[$guid] ?? [],
                'images' => $imagesByGuid[$guid] ?? [],
            ];
        });
    }

    public function distinctMakeModelMissingCarGuid(int $limit = 5000): Collection
    {
        $good = DB::table(self::TABLE_MAP['good'])
            ->selectRaw('Make, Model')
            ->where(function (Builder $query): void {
                $query->whereNull('car_GUID')->orWhere('car_GUID', '');
            })
            ->whereNotNull('Make')
            ->whereNotNull('Model');

        return DB::table(self::TABLE_MAP['bad'])
            ->selectRaw('Make, Model')
            ->where(function (Builder $query): void {
                $query->whereNull('car_GUID')->orWhere('car_GUID', '');
            })
            ->whereNotNull('Make')
            ->whereNotNull('Model')
            ->union($good)
            ->groupBy('Make', 'Model')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'make' => trim((string) $row->Make),
                'model' => trim((string) $row->Model),
            ])
            ->filter(fn (array $row): bool => $row['make'] !== '' && $row['model'] !== '')
            ->values();
    }

    public function attachCarGuidForMakeModel(string $make, string $model, string $guid): int
    {
        $affected = 0;

        foreach (self::TABLE_MAP as $table) {
            $affected += DB::table($table)
                ->whereRaw(self::WHERE_MAKE_LOWER, [strtolower($make)])
                ->whereRaw(self::WHERE_MODEL_LOWER, [strtolower($model)])
                ->update(['car_GUID' => $guid]);
        }

        return $affected;
    }

    public function videoMentionsByRange(string $source, string $make, string $model, ?int $startYear, ?int $endYear, int $limit = 50): array
    {
        $query = DB::table($this->tableFor($source))
            ->whereRaw(self::WHERE_MAKE_LOWER, [strtolower($make)])
            ->whereRaw(self::WHERE_MODEL_LOWER, [strtolower($model)]);

        if ($startYear !== null || $endYear !== null) {
            $from = $startYear ?? 0;
            $to = $endYear ?? 3000;

            $query->where(function (Builder $builder) use ($from, $to): void {
                $builder
                    ->where(function (Builder $exact) use ($from, $to): void {
                        $exact->whereBetween('StartYear', [$from, $to]);
                    })
                    ->orWhere(function (Builder $exactEnd) use ($from, $to): void {
                        $exactEnd->whereNotNull('EndYear')
                            ->whereBetween('EndYear', [$from, $to]);
                    })
                    ->orWhere(function (Builder $overlap) use ($from, $to): void {
                        $overlap->whereNotNull('StartYear')
                            ->where('StartYear', '<=', $to)
                            ->where(function (Builder $end) use ($from): void {
                                $end->whereNull('EndYear')
                                    ->orWhere('EndYear', '>=', $from);
                            });
                    });
            });
        }

        return $query
            ->orderBy('Id')
            ->limit($limit)
            ->get(['YoutubeVideoId', 'Comment', 'VideoTitle', 'Timestamp', 'StartYear', 'EndYear'])
            ->map(fn (object $row): array => [
                'video_id' => $row->YoutubeVideoId,
                'comment' => $row->Comment,
                'video_title' => $row->VideoTitle,
                'timestamp' => $row->Timestamp,
                'start_year' => $row->StartYear !== null ? (int) $row->StartYear : null,
                'end_year' => $row->EndYear !== null ? (int) $row->EndYear : null,
                'isGoodverdict' => $source === 'good',
            ])
            ->all();
    }

    private function baseQuery(string $source, string $make, string $model, ?int $year): Builder
    {
        $verdictTable = $this->tableFor($source);
        $query = DB::table($verdictTable . ' as v')
            ->join('car_car as c', 'c.Guid', '=', 'v.car_GUID')
            ->join('car_make as m', 'm.MakeId', '=', 'c.MakeId')
            ->whereRaw('LOWER(m.MakeName) = ?', [strtolower($make)])
            ->whereRaw('LOWER(c.Model) = ?', [strtolower($model)]);
        if ($year !== null) {
            $query->where(function (Builder $builder) use ($year): void {
                $builder
                    ->Where('c.ModelYear', $year)
                    ->orwhere('v.StartYear', $year)
                    ->orWhere('v.EndYear', $year)
                    ->orWhere(function (Builder $range) use ($year): void {
                        $range->whereNotNull('v.StartYear')
                            ->where('v.StartYear', '<=', $year)
                            ->where(function (Builder $end) use ($year): void {
                                $end->whereNull('v.EndYear')
                                    ->orWhere('v.EndYear', '>=', $year);
                            });
                    });
            });
        }

        return $query;
    }

    private function topVideosForCarGuids(string $table, array $carGuids, int $perCarLimit, bool $isGoodVerdict): array
    {
        if ($carGuids === []) {
            return [];
        }

        $rows = DB::table($table)
            ->whereIn('car_GUID', $carGuids)
            ->orderBy('Id')
            ->get(['car_GUID', 'YoutubeVideoId', 'Comment', 'VideoTitle', 'Timestamp']);

        $grouped = [];
        $counts = [];

        foreach ($rows as $row) {
            $guid = (string) $row->car_GUID;

            $counts[$guid] = ($counts[$guid] ?? 0) + 1;

            if ($counts[$guid] > $perCarLimit) {
                continue;
            }

            $grouped[$guid] ??= [];
            $grouped[$guid][] = [
                'video_id' => $row->YoutubeVideoId,
                'comment' => $row->Comment,
                'video_title' => $row->VideoTitle,
                'timestamp' => $row->Timestamp,
                'isGoodverdict' => $isGoodVerdict,
            ];
        }

        return $grouped;
    }

    private function imagesForCarGuids(array $carGuids): array
    {
        if ($carGuids === []) {
            return [];
        }

        $rows = DB::table('car_image')
            ->whereIn('car_GUID', $carGuids)
            ->orderBy('ImageId')
            ->get(['car_GUID', 'ImageId']);

        $grouped = [];

        foreach ($rows as $row) {
            $guid = (string) $row->car_GUID;
            $grouped[$guid] ??= [];
            $grouped[$guid][] = [
                'id' => $row->ImageId,
            ];
        }

        return $grouped;
    }

    private function tableFor(string $source): string
    {
        return self::TABLE_MAP[$source] ?? throw new \InvalidArgumentException('Unknown verdict source.');
    }
}
