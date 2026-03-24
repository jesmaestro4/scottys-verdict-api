<?php

namespace App\Repositories;

use App\Exceptions\CarCacheException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CarRepository
{
    public function findByIdentity(string $make, string $model, ?int $year): ?array
    {
        $query = DB::table('car_car')
            ->leftJoin('car_make', 'car_make.MakeId', '=', 'car_car.MakeId')
            ->leftJoin('car_vehicletype', 'car_vehicletype.VehicleTypeId', '=', 'car_car.VehicleTypeId')
            ->whereRaw('LOWER(car_make.MakeName) = ?', [strtolower($make)])
            ->whereRaw('LOWER(car_car.Model) = ?', [strtolower($model)]);

        if ($year !== null) {
            $query->where('car_car.ModelYear', (string) $year);
        }

        $row = $query
            ->select('car_car.*', 'car_make.MakeName', 'car_vehicletype.VehicleTypeName', 'car_vehicletype.CustomVehicleTypeName')
            ->first();

        return $row ? (array) $row : null;
    }

    public function findByGuid(string $guid): ?array
    {
        $row = DB::table('car_car')
            ->leftJoin('car_make', 'car_make.MakeId', '=', 'car_car.MakeId')
            ->leftJoin('car_vehicletype', 'car_vehicletype.VehicleTypeId', '=', 'car_car.VehicleTypeId')
            ->where('car_car.Guid', $guid)
            ->select('car_car.*', 'car_make.MakeName', 'car_vehicletype.VehicleTypeName', 'car_vehicletype.CustomVehicleTypeName')
            ->first();

        return $row ? (array) $row : null;
    }

    public function ensureMake(string $make): int
    {
        $existing = DB::table('car_make')
            ->whereRaw('LOWER(MakeName) = ?', [strtolower($make)])
            ->value('MakeId');

        if ($existing !== null) {
            return (int) $existing;
        }

        $nextId = (int) DB::table('car_make')->max('MakeId') + 1;

        DB::table('car_make')->insert([
            'MakeId' => $nextId,
            'MakeName' => strtoupper($make),
        ]);

        return $nextId;
    }

    public function resolveVehicleTypeId(?string $vehicleTypeName): ?int
    {
        if ($vehicleTypeName === null || $vehicleTypeName === '') {
            return null;
        }

        $normalized = strtolower($vehicleTypeName);
        $resolved = null;

        $directMatch = DB::table('car_vehicletype')
            ->whereRaw('LOWER(VehicleTypeName) = ?', [$normalized])
            ->orWhereRaw('LOWER(CustomVehicleTypeName) = ?', [$normalized])
            ->value('VehicleTypeId');

        if ($directMatch !== null) {
            $resolved = (int) $directMatch;
        }

        if ($resolved === null) {
            $fallbacks = [
                'motorcycle' => 1,
                'car' => 2,
                'truck' => 3,
                'bus' => 5,
                'minivan' => 7,
                'mpv' => 7,
            ];

            foreach ($fallbacks as $needle => $vehicleTypeId) {
                if (str_contains($normalized, $needle)) {
                    $resolved = $vehicleTypeId;
                    break;
                }
            }
        }

        return $resolved;
    }

    public function createCar(array $attributes): array
    {
        $guid = (string) Str::uuid();

        DB::table('car_car')->insert([
            'Guid' => $guid,
            'MakeId' => $attributes['make_id'],
            'VehicleTypeId' => $attributes['vehicle_type_id'],
            'ManufacturerName' => $attributes['manufacturer_name'],
            'Model' => $attributes['model'],
            'ModelYear' => $attributes['model_year'] !== null ? (string) $attributes['model_year'] : null,
            'Trim' => $attributes['trim'],
            'BodyClass' => $attributes['body_class'],
            'DriveType' => $attributes['drive_type'],
            'FuelTypePrimary' => $attributes['fuel_type_primary'],
            'EngineNumberOfCylinders' => $attributes['engine_cylinders'],
            'TransmissionStyle' => $attributes['transmission_style'],
            'Doors' => $attributes['doors'],
            'Note' => $attributes['note'],
            'ErrorCode' => $attributes['error_code'],
            'AdditionalErrorText' => $attributes['additional_error_text'],
        ]);

        return $this->findByGuid($guid) ?? throw new CarCacheException('Unable to reload cached car.');
    }

    public function imagesForCar(string $carGuid): array
    {
        return DB::table('car_image')
            ->where('car_GUID', $carGuid)
            ->orderBy('ImageId')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    public function findImage(string $carGuid, string $imageId): ?array
    {
        $row = DB::table('car_image')
            ->where('car_GUID', $carGuid)
            ->where('ImageId', $imageId)
            ->first();

        return $row ? (array) $row : null;
    }

    public function updateImageUrl(string $carGuid, string $imageId, string $url): void
    {
        DB::table('car_image')
            ->where('car_GUID', $carGuid)
            ->where('ImageId', $imageId)
            ->update([
                'Url' => $url,
            ]);
    }

    public function syncImages(string $carGuid, array $urls): void
    {
        $existing = DB::table('car_image')
            ->where('car_GUID', $carGuid)
            ->pluck('Url')
            ->all();

        $pending = array_values(array_diff($urls, $existing));

        if ($pending === []) {
            return;
        }

        DB::table('car_image')->insert(array_map(fn (string $url): array => [
            'ImageId' => (string) Str::uuid(),
            'car_GUID' => $carGuid,
            'Url' => $url,
        ], $pending));
    }
}
