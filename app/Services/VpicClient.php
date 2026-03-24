<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VpicClient
{
    public function fetchVehicleProfile(string $make, string $model, ?int $year): array
    {
        $modelResponse = $year !== null
            ? $this->get('/vehicles/GetModelsForMakeYear/make/'.rawurlencode($make).'/modelyear/'.$year, ['format' => 'json'])
            : $this->get('/vehicles/GetModelsForMake/'.rawurlencode($make), ['format' => 'json']);

        $models = data_get($modelResponse, 'Results', []);
        $matchedModel = collect($models)->first(fn (array $row): bool => $this->matches(data_get($row, 'Model_Name'), $model));

        $vehicleTypeResponse = $this->get('/vehicles/GetVehicleTypesForMake/'.rawurlencode($make), ['format' => 'json']);
        $vehicleTypes = collect(data_get($vehicleTypeResponse, 'Results', []))
            ->pluck('VehicleTypeName')
            ->filter()
            ->values();

        $vehicleTypeName = $vehicleTypes->first();

        return [
            'manufacturer_name' => data_get($matchedModel, 'Make_Name', strtoupper($make)),
            'model' => data_get($matchedModel, 'Model_Name', $model),
            'model_year' => $year,
            'body_class' => $vehicleTypeName,
            'vehicle_type_name' => $vehicleTypeName,
            'drive_type' => null,
            'fuel_type_primary' => null,
            'engine_cylinders' => null,
            'transmission_style' => null,
            'trim' => null,
            'doors' => null,
            'note' => 'Cached from NHTSA vPIC.',
            'error_code' => null,
            'additional_error_text' => null,
        ];
    }

    private function get(string $path, array $query): array
    {
        return Http::baseUrl(rtrim((string) config('services.vpic.base_url'), '/'))
            ->acceptJson()
            ->timeout(20)
            ->withOptions(['verify' => (bool) config('services.vpic.verify_ssl', true)])
            ->get($path, $query)
            ->throw()
            ->json();
    }

    private function matches(?string $left, string $right): bool
    {
        if ($left === null) {
            return false;
        }

        $normalize = static fn (string $value): string => preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';

        return $normalize($left) === $normalize($right);
    }
}
