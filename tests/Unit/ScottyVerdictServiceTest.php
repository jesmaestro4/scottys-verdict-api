<?php

namespace Tests\Unit;

use App\Repositories\VerdictRepository;
use App\Services\CarProfileService;
use App\Services\ScottyVerdictService;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScottyVerdictServiceTest extends TestCase
{
    #[Test]
    public function it_merges_video_mentions_from_good_and_bad_sources(): void
    {
        $repository = Mockery::mock(VerdictRepository::class);
        $cars = Mockery::mock(CarProfileService::class);

        $repository->shouldReceive('videoMentionsByRange')
            ->once()
            ->with('good', 'Audi', 'A4', 2011, 2011, 10)
            ->andReturn([
                [
                    'video_id' => 'shared-video',
                    'comment' => 'shared mention',
                    'video_title' => 'Shared Clip',
                    'timestamp' => '00:00:15',
                    'start_year' => 2011,
                    'end_year' => 2011,
                    'isGoodverdict' => true,
                ],
            ]);

        $repository->shouldReceive('videoMentionsByRange')
            ->once()
            ->with('bad', 'Audi', 'A4', 2011, 2011, 10)
            ->andReturn([
                [
                    'video_id' => 'shared-video',
                    'comment' => 'shared mention',
                    'video_title' => 'Shared Clip',
                    'timestamp' => '00:00:15',
                    'start_year' => 2011,
                    'end_year' => 2011,
                    'isGoodverdict' => false,
                ],
                [
                    'video_id' => 'bad-video',
                    'comment' => 'bad table mention',
                    'video_title' => 'Worst Vehicles Ever',
                    'timestamp' => '00:06:28',
                    'start_year' => 2011,
                    'end_year' => 2011,
                    'isGoodverdict' => false,
                ],
            ]);

        $service = new ScottyVerdictService($repository, $cars);

        $response = $service->videoMentions('Audi', 'A4', 2011, 2011, 10);

        $this->assertCount(2, $response['data']);
        $this->assertSame('shared-video', $response['data'][0]['video_id']);
        $this->assertTrue($response['data'][0]['isGoodverdict']);
        $this->assertSame('bad-video', $response['data'][1]['video_id']);
        $this->assertFalse($response['data'][1]['isGoodverdict']);
    }

    #[Test]
    public function top_verdicts_returns_lazy_image_url_without_db_image_lookup(): void
    {
        $repository = Mockery::mock(VerdictRepository::class);
        $cars = Mockery::mock(CarProfileService::class);

        $repository->shouldReceive('topGroupedVerdicts')
            ->once()
            ->with('good')
            ->andReturn(new Collection([
                [
                    'guid' => 'test-guid-123',
                    'make' => 'Toyota',
                    'model' => 'Camry',
                    'year' => 2020,
                    'start_year' => 2020,
                    'end_year' => 2020,
                    'vehicle_type_id' => 2,
                    'vehicle_type' => 'Car',
                    'manufacturer_name' => 'TOYOTA',
                    'trim' => null,
                    'body_class' => null,
                    'drive_type' => null,
                    'fuel_type_primary' => null,
                    'engine_cylinders' => null,
                    'transmission_style' => null,
                    'total_mentions' => 5,
                    'images' => [],
                    'videos' => [],
                ],
            ]));

        // Must NOT be called — images come from the lazy URL, no DB/backfill lookup.
        $cars->shouldNotReceive('resolveImagePayloadForCar');

        $service = new ScottyVerdictService($repository, $cars);
        $result = $service->topVerdicts('good', 1, 10, null);

        $this->assertCount(1, $result['data']);
        $images = $result['data'][0]['images'];
        $this->assertCount(1, $images);
        $this->assertSame('test-guid-123', $images[0]['id']);
        $this->assertStringContainsString('test-guid-123', $images[0]['url']);
        $this->assertStringContainsString('/api/images/', $images[0]['url']);
    }

    #[Test]
    public function top_verdicts_returns_empty_images_when_guid_is_null(): void
    {
        $repository = Mockery::mock(VerdictRepository::class);
        $cars = Mockery::mock(CarProfileService::class);

        $repository->shouldReceive('topGroupedVerdicts')
            ->once()
            ->with('bad')
            ->andReturn(new Collection([
                [
                    'guid' => null,
                    'make' => 'Pontiac',
                    'model' => 'Aztec',
                    'year' => 2003,
                    'start_year' => 2003,
                    'end_year' => 2003,
                    'vehicle_type_id' => null,
                    'vehicle_type' => null,
                    'manufacturer_name' => 'PONTIAC',
                    'trim' => null,
                    'body_class' => null,
                    'drive_type' => null,
                    'fuel_type_primary' => null,
                    'engine_cylinders' => null,
                    'transmission_style' => null,
                    'total_mentions' => 3,
                    'images' => [],
                    'videos' => [],
                ],
            ]));

        $cars->shouldNotReceive('resolveImagePayloadForCar');

        $service = new ScottyVerdictService($repository, $cars);
        $result = $service->topVerdicts('bad', 1, 10, null);

        $this->assertCount(1, $result['data']);
        $this->assertSame([], $result['data'][0]['images']);
    }
}
