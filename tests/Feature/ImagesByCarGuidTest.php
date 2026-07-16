<?php

namespace Tests\Feature;

use App\Repositories\CarRepository;
use App\Services\CarImagesClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ImagesByCarGuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_by_car_returns_404_when_car_not_found(): void
    {
        $repo = Mockery::mock(CarRepository::class);
        $repo->shouldReceive('findByGuid')->once()->with('missing-guid')->andReturn(null);
        $this->app->instance(CarRepository::class, $repo);

        $this->getJson('/api/images/missing-guid')
            ->assertNotFound();
    }

    public function test_show_by_car_returns_404_when_car_identity_incomplete(): void
    {
        $repo = Mockery::mock(CarRepository::class);
        $repo->shouldReceive('findByGuid')->once()->andReturn([
            'Guid' => 'car-guid-001',
            'MakeName' => '',
            'Model' => '',
            'ModelYear' => null,
        ]);
        $repo->shouldReceive('imagesForCar')->once()->andReturn([]);
        $this->app->instance(CarRepository::class, $repo);

        $carImages = Mockery::mock(CarImagesClient::class);
        $carImages->shouldNotReceive('fetchSignedImageUrls');
        $this->app->instance(CarImagesClient::class, $carImages);

        $this->getJson('/api/images/car-guid-001')
            ->assertNotFound();
    }

    public function test_show_by_car_returns_cached_disk_image_without_remote_fetch(): void
    {
        Storage::fake('local');

        $carGuid = 'car-cached-001';
        $imageId = 'img-cached-001';
        $path = 'car-images/'.$carGuid.'/'.$imageId;
        Storage::disk('local')->put($path, 'FAKEJPEGBYTES');

        $repo = Mockery::mock(CarRepository::class);
        $repo->shouldReceive('findByGuid')->once()->with($carGuid)->andReturn([
            'Guid' => $carGuid,
            'MakeName' => 'Toyota',
            'Model' => 'Corolla',
            'ModelYear' => '2020',
        ]);
        $repo->shouldReceive('imagesForCar')->once()->andReturn([
            ['ImageId' => $imageId, 'Url' => 'https://remote.example/img.jpg'],
        ]);
        $this->app->instance(CarRepository::class, $repo);

        $carImages = Mockery::mock(CarImagesClient::class);
        $carImages->shouldNotReceive('fetchSignedImageUrls');
        $this->app->instance(CarImagesClient::class, $carImages);

        $this->get('/api/images/'.$carGuid)->assertOk();
    }

    public function test_show_by_car_fetches_saves_and_returns_image_when_no_images_cached(): void
    {
        Storage::fake('local');

        $carGuid = 'car-fresh-001';
        $imageId = 'img-fresh-001';
        $remoteUrl = 'https://remote.example/fresh.jpg';

        $repo = Mockery::mock(CarRepository::class);
        $repo->shouldReceive('findByGuid')->once()->with($carGuid)->andReturn([
            'Guid' => $carGuid,
            'MakeName' => 'Honda',
            'Model' => 'Civic',
            'ModelYear' => '2019',
        ]);
        $repo->shouldReceive('imagesForCar')
            ->andReturn([], [['ImageId' => $imageId, 'Url' => $remoteUrl]]);
        $repo->shouldReceive('syncImages')
            ->once()
            ->with($carGuid, [$remoteUrl]);
        $this->app->instance(CarRepository::class, $repo);

        $carImages = Mockery::mock(CarImagesClient::class);
        $carImages->shouldReceive('fetchSignedImageUrls')
            ->once()
            ->with('Honda', 'Civic', 2019)
            ->andReturn([$remoteUrl]);
        $this->app->instance(CarImagesClient::class, $carImages);

        Http::fake([$remoteUrl => Http::response('FAKEHONDABYTES', 200)]);

        $response = $this->get('/api/images/'.$carGuid);
        $response->assertOk();

        Storage::disk('local')->assertExists('car-images/'.$carGuid.'/'.$imageId);
    }

    public function test_show_by_car_returns_404_when_provider_returns_no_urls(): void
    {
        Storage::fake('local');

        $carGuid = 'car-nourls-001';

        $repo = Mockery::mock(CarRepository::class);
        $repo->shouldReceive('findByGuid')->once()->andReturn([
            'Guid' => $carGuid,
            'MakeName' => 'Ford',
            'Model' => 'Pinto',
            'ModelYear' => '1976',
        ]);
        $repo->shouldReceive('imagesForCar')->once()->andReturn([]);
        $this->app->instance(CarRepository::class, $repo);

        $carImages = Mockery::mock(CarImagesClient::class);
        $carImages->shouldReceive('fetchSignedImageUrls')
            ->once()
            ->andReturn([]);
        $this->app->instance(CarImagesClient::class, $carImages);

        $this->getJson('/api/images/'.$carGuid)->assertNotFound();
    }

    public function test_show_by_car_downloads_image_when_in_db_but_not_on_disk(): void
    {
        Storage::fake('local');

        $carGuid = 'car-nodisk-001';
        $imageId = 'img-nodisk-001';
        $remoteUrl = 'https://remote.example/nodisk.jpg';

        $repo = Mockery::mock(CarRepository::class);
        $repo->shouldReceive('findByGuid')->once()->with($carGuid)->andReturn([
            'Guid' => $carGuid,
            'MakeName' => 'Ford',
            'Model' => 'Focus',
            'ModelYear' => '2015',
        ]);
        $repo->shouldReceive('imagesForCar')->once()->andReturn([
            ['ImageId' => $imageId, 'Url' => $remoteUrl],
        ]);
        $this->app->instance(CarRepository::class, $repo);

        $carImages = Mockery::mock(CarImagesClient::class);
        $carImages->shouldNotReceive('fetchSignedImageUrls');
        $this->app->instance(CarImagesClient::class, $carImages);

        Http::fake([$remoteUrl => Http::response('FAKEFOCUSBYTES', 200)]);

        $response = $this->get('/api/images/'.$carGuid);
        $response->assertOk();

        Storage::disk('local')->assertExists('car-images/'.$carGuid.'/'.$imageId);
    }
}
