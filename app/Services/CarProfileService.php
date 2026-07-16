<?php

namespace App\Services;

use App\Repositories\CarRepository;
use App\Repositories\ListingRepository;
use Throwable;

class CarProfileService
{
    public function __construct(
        private readonly CarRepository $cars,
        private readonly ListingRepository $listings,
        private readonly VpicClient $vpic,
        private readonly MarketCheckClient $marketCheck,
        private readonly CarImagesClient $carImages,
    ) {
    }

    public function ensureCachedCar(string $make, string $model, ?int $year): array
    {
        $car = $this->cars->findByIdentity($make, $model, $year);

        if ($car !== null) {
            return $car;
        }

        try {
            $profile = $this->vpic->fetchVehicleProfile($make, $model, $year);
        } catch (Throwable) {
            $profile = [
                'manufacturer_name' => strtoupper($make),
                'model' => $model,
                'model_year' => $year,
                'vehicle_type_name' => null,
                'body_class' => null,
                'drive_type' => null,
                'fuel_type_primary' => null,
                'engine_cylinders' => null,
                'transmission_style' => null,
                'trim' => null,
                'doors' => null,
                'note' => 'Created from verdict data fallback because VPIC was unavailable.',
                'error_code' => null,
                'additional_error_text' => null,
            ];
        }

        $makeId = $this->cars->ensureMake($make);
        $vehicleTypeId = $this->cars->resolveVehicleTypeId($profile['vehicle_type_name'] ?? null);

        return $this->cars->createCar([
            'make_id' => $makeId,
            'vehicle_type_id' => $vehicleTypeId,
            'manufacturer_name' => $profile['manufacturer_name'] ?? strtoupper($make),
            'model' => $profile['model'] ?? $model,
            'model_year' => $profile['model_year'] ?? $year,
            'trim' => $profile['trim'] ?? null,
            'body_class' => $profile['body_class'] ?? null,
            'drive_type' => $profile['drive_type'] ?? null,
            'fuel_type_primary' => $profile['fuel_type_primary'] ?? null,
            'engine_cylinders' => $profile['engine_cylinders'] ?? null,
            'transmission_style' => $profile['transmission_style'] ?? null,
            'doors' => $profile['doors'] ?? null,
            'note' => $profile['note'] ?? null,
            'error_code' => $profile['error_code'] ?? null,
            'additional_error_text' => $profile['additional_error_text'] ?? null,
        ]);
    }

    public function resolveCarObject(string $make, string $model, ?int $year): array
    {
        $car = $this->ensureCachedCar($make, $model, $year);
        $images = $this->resolveImagePayloadForCar($car['Guid'], $make, $model, $year);

        $priceRange = $this->listings->priceRangeForCar($car['Guid']);

        if ($priceRange['min'] === null && $priceRange['max'] === null) {
            try {
                $payload = $this->marketCheck->searchPrivatePartyListings([
                    'make' => $make,
                    'model' => $model,
                    'year' => $year,
                    'rows' => 20,
                    'stats' => 'price',
                ]);

                $cachedListings = $this->mapRemoteListingsToRows($car['Guid'], $payload);

                if ($cachedListings !== []) {
                    $this->listings->cacheListings($cachedListings);
                }

                $priceRange = $this->marketCheck->extractPriceRange($payload);
            } catch (Throwable) {
                // Keep null price range if pricing provider is unavailable.
            }
        }

        return [
            'guid' => $car['Guid'],
            'make' => $car['MakeName'] ?? strtoupper($make),
            'model' => $car['Model'] ?? $model,
            'year' => $car['ModelYear'] !== null ? (int) $car['ModelYear'] : $year,
            'vehicle_type_id' => $car['VehicleTypeId'] !== null ? (int) $car['VehicleTypeId'] : null,
            'vehicle_type' => $car['CustomVehicleTypeName'] ?? $car['VehicleTypeName'] ?? null,
            'manufacturer_name' => $car['ManufacturerName'] ?? null,
            'trim' => $car['Trim'] ?? null,
            'body_class' => $car['BodyClass'] ?? null,
            'drive_type' => $car['DriveType'] ?? null,
            'fuel_type_primary' => $car['FuelTypePrimary'] ?? null,
            'engine_cylinders' => $car['EngineNumberOfCylinders'] ?? null,
            'transmission_style' => $car['TransmissionStyle'] ?? null,
            'images' => $images,
            'price_range' => $priceRange,
        ];
    }

    public function resolveImagePayloadForCar(string $carGuid, string $make, string $model, ?int $year): array
    {
        $images = $this->resolveOrBackfillImages($carGuid, $make, $model, $year);

        return array_map(fn (array $image): array => [
            'id' => $image['ImageId'],
            'url' => route('api.images.show', ['carGuid' => $carGuid, 'imageId' => $image['ImageId']]),
        ], $images);
    }

    private function resolveOrBackfillImages(string $carGuid, string $make, string $model, ?int $year): array
    {
        $images = $this->cars->imagesForCar($carGuid);

        if ($images !== []) {
            return $images;
        }

        try {
            $this->cars->syncImages($carGuid, $this->carImages->fetchSignedImageUrls($make, $model, $year));
        } catch (Throwable) {
            // Degrade gracefully when image provider is unavailable.
        }

        return $this->cars->imagesForCar($carGuid);
    }

    public function resolveCachedCarObject(string $make, string $model, ?int $year): array
    {
        $car = $this->cars->findByIdentity($make, $model, $year);

        if ($car === null) {
            return [
                'guid' => null,
                'make' => strtoupper($make),
                'model' => $model,
                'year' => $year,
                'vehicle_type_id' => null,
                'vehicle_type' => null,
                'manufacturer_name' => strtoupper($make),
                'trim' => null,
                'body_class' => null,
                'drive_type' => null,
                'fuel_type_primary' => null,
                'engine_cylinders' => null,
                'transmission_style' => null,
                'images' => [],
                'price_range' => ['min' => null, 'max' => null, 'listing_count' => 0, 'currency' => 'USD'],
            ];
        }

        return [
            'guid' => $car['Guid'],
            'make' => $car['MakeName'] ?? strtoupper($make),
            'model' => $car['Model'] ?? $model,
            'year' => $car['ModelYear'] !== null ? (int) $car['ModelYear'] : $year,
            'vehicle_type_id' => $car['VehicleTypeId'] !== null ? (int) $car['VehicleTypeId'] : null,
            'vehicle_type' => $car['CustomVehicleTypeName'] ?? $car['VehicleTypeName'] ?? null,
            'manufacturer_name' => $car['ManufacturerName'] ?? null,
            'trim' => $car['Trim'] ?? null,
            'body_class' => $car['BodyClass'] ?? null,
            'drive_type' => $car['DriveType'] ?? null,
            'fuel_type_primary' => $car['FuelTypePrimary'] ?? null,
            'engine_cylinders' => $car['EngineNumberOfCylinders'] ?? null,
            'transmission_style' => $car['TransmissionStyle'] ?? null,
            'images' => [],
            'price_range' => ['min' => null, 'max' => null, 'listing_count' => 0, 'currency' => 'USD'],
        ];
    }

    private function mapRemoteListingsToRows(string $carGuid, array $payload): array
    {
        return collect($this->marketCheck->extractListings($payload))
            ->map(function (array $listing) use ($carGuid): array {
                return [
                    'ListingId' => (string) (data_get($listing, 'id') ?? data_get($listing, 'listing_id')),
                    'car_GUID' => $carGuid,
                    'Vin' => data_get($listing, 'vin'),
                    'Heading' => data_get($listing, 'heading'),
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
                    'BuildYear' => data_get($listing, 'build.year') ?? data_get($listing, 'year'),
                    'BuildMake' => data_get($listing, 'build.make') ?? data_get($listing, 'make'),
                    'BuildModel' => data_get($listing, 'build.model') ?? data_get($listing, 'model'),
                    'BuildTrim' => data_get($listing, 'build.trim') ?? data_get($listing, 'trim'),
                    'BuildVehicleType' => data_get($listing, 'build.vehicle_type') ?? data_get($listing, 'vehicle_type'),
                ];
            })
            ->filter(fn (array $row): bool => ! empty($row['ListingId']))
            ->values()
            ->all();
    }
}
