<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\CarRepository;
use App\Services\CarImagesClient;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;
use Throwable;

class ImageProxyController extends Controller
{
    private const ERROR_SCHEMA = '#/components/schemas/ErrorResponse';

    public function __construct(
        private readonly CarRepository $cars,
        private readonly CarImagesClient $carImages,
    ) {
    }

    #[OA\Get(
        path: '/api/images/{carGuid}/{imageId}',
        summary: 'Fetch and cache a car image with automatic signed URL refresh.',
        security: [['sanctumCookieAuth' => [], 'xsrfHeader' => []], ['bearerToken' => []]],
        tags: ['Images'],
        parameters: [
            new OA\Parameter(name: 'carGuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'imageId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Image binary data.'),
            new OA\Response(response: 401, description: 'Unauthenticated.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
            new OA\Response(response: 404, description: 'Image not found.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
            new OA\Response(response: 502, description: 'Unable to load remote image.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function show(string $carGuid, string $imageId): Response
    {
        $image = $this->cars->findImage($carGuid, $imageId);

        abort_if($image === null, 404, 'Image not found.');

        $path = 'car-images/'.$carGuid.'/'.$imageId;
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            $downloaded = $this->downloadToDisk($image['Url'], $path, $disk);

            if (! $downloaded) {
                $refreshedUrl = $this->refreshSignedImageUrl($carGuid, $imageId);

                if ($refreshedUrl !== null) {
                    $downloaded = $this->downloadToDisk($refreshedUrl, $path, $disk);
                }
            }

            if (! $downloaded) {
                abort(502, 'Unable to load remote image.');
            }
        }

        return response($disk->get($path), 200, [
            'Content-Type' => mime_content_type($disk->path($path)) ?: 'application/octet-stream',
        ]);
    }

    #[OA\Get(
        path: '/api/images/{carGuid}',
        summary: 'Lazy-load, cache and return the first available image for a car by GUID.',
        tags: ['Images'],
        parameters: [
            new OA\Parameter(name: 'carGuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Image binary data.'),
            new OA\Response(response: 404, description: 'Car or image not found.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
            new OA\Response(response: 502, description: 'Unable to load remote image.', content: new OA\JsonContent(ref: self::ERROR_SCHEMA)),
        ]
    )]
    public function showByCar(string $carGuid): Response
    {
        $car = $this->cars->findByGuid($carGuid);

        abort_if($car === null, 404, 'Car not found.');

        $disk = Storage::disk('local');
        $images = $this->cars->imagesForCar($carGuid);

        if ($images !== []) {
            $first = $images[0];
            $path = 'car-images/'.$carGuid.'/'.$first['ImageId'];

            if ($disk->exists($path)) {
                return response($disk->get($path), 200, [
                    'Content-Type' => mime_content_type($disk->path($path)) ?: 'application/octet-stream',
                ]);
            }

            if ($this->downloadToDisk($first['Url'], $path, $disk)) {
                return response($disk->get($path), 200, [
                    'Content-Type' => mime_content_type($disk->path($path)) ?: 'application/octet-stream',
                ]);
            }
        }

        // No cached images — fetch from provider, persist to DB and disk, then serve.
        $make = (string) ($car['MakeName'] ?? '');
        $model = (string) ($car['Model'] ?? '');
        $year = $car['ModelYear'] !== null ? (int) $car['ModelYear'] : null;

        abort_if($make === '' || $model === '', 404, 'Car identity incomplete.');

        try {
            $urls = $this->carImages->fetchSignedImageUrls($make, $model, $year);
        } catch (Throwable) {
            abort(502, 'Unable to fetch car images from provider.');
        }

        abort_if($urls === [], 404, 'No images available for this car.');

        $this->cars->syncImages($carGuid, $urls);

        $fresh = $this->cars->imagesForCar($carGuid);

        abort_if($fresh === [], 502, 'Image sync failed.');

        $first = $fresh[0];
        $path = 'car-images/'.$carGuid.'/'.$first['ImageId'];

        abort_unless($this->downloadToDisk($first['Url'], $path, $disk), 502, 'Unable to download car image.');

        return response($disk->get($path), 200, [
            'Content-Type' => mime_content_type($disk->path($path)) ?: 'application/octet-stream',
        ]);
    }

    private function downloadToDisk(string $url, string $path, \Illuminate\Contracts\Filesystem\Filesystem $disk): bool
    {
        try {
            $response = Http::timeout(20)
                ->withOptions(['verify' => (bool) config('services.car_images.verify_ssl', true)])
                ->get($url)
                ->throw();

            $disk->put($path, $response->body());

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function refreshSignedImageUrl(string $carGuid, string $imageId): ?string
    {
        $car = $this->cars->findByGuid($carGuid);
        $newUrl = null;

        if ($car !== null) {
            $make = (string) ($car['MakeName'] ?? '');
            $model = (string) ($car['Model'] ?? '');

            if ($make !== '' && $model !== '') {
                try {
                    $urls = $this->carImages->fetchSignedImageUrls(
                        $make,
                        $model,
                        $car['ModelYear'] !== null ? (int) $car['ModelYear'] : null,
                    );

                    $candidate = $urls[0] ?? null;

                    if (is_string($candidate) && $candidate !== '') {
                        $this->cars->updateImageUrl($carGuid, $imageId, $candidate);
                        $newUrl = $candidate;
                    }
                } catch (Throwable) {
                    $newUrl = null;
                }
            }
        }

        return $newUrl;
    }
}
