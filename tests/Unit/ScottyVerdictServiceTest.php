<?php

namespace Tests\Unit;

use App\Repositories\VerdictRepository;
use App\Services\CarProfileService;
use App\Services\ScottyVerdictService;
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
}
