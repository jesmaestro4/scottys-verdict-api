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
