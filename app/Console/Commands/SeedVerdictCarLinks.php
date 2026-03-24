<?php

namespace App\Console\Commands;

use App\Repositories\CarRepository;
use App\Repositories\VerdictRepository;
use App\Services\CarProfileService;
use Illuminate\Console\Command;
use Throwable;

class SeedVerdictCarLinks extends Command
{
    protected $signature = 'scotty:seed-verdict-car-links {--limit=5000 : Max distinct make/model groups to process}';

    protected $description = 'Backfill verdict_* car_GUID by make/model (ignoring year), creating missing car_car rows from VPIC data.';

    public function handle(
        VerdictRepository $verdicts,
        CarRepository $cars,
        CarProfileService $profiles,
    ): int {
        $limit = max((int) $this->option('limit'), 1);
        $groups = $verdicts->distinctMakeModelMissingCarGuid($limit);

        if ($groups->isEmpty()) {
            $this->info('No missing verdict car links found.');

            return self::SUCCESS;
        }

        $processed = 0;
        $linkedRows = 0;
        $created = 0;
        $skipped = 0;

        foreach ($groups as $group) {
            $make = (string) $group['make'];
            $model = (string) $group['model'];

            try {
                $car = $cars->findByIdentity($make, $model, null);

                if ($car === null) {
                    $car = $profiles->ensureCachedCar($make, $model, null);
                    $created++;
                }

                $guid = $car['Guid'] ?? null;

                if (! is_string($guid) || $guid === '') {
                    $skipped++;
                    $this->warn("Skipped {$make} {$model}: no Guid resolved.");
                    continue;
                }

                $affected = $verdicts->attachCarGuidForMakeModel($make, $model, $guid);
                $linkedRows += $affected;
                $processed++;
            } catch (Throwable $e) {
                $skipped++;
                $this->warn("Skipped {$make} {$model}: {$e->getMessage()}");
            }
        }

        $this->info("Processed groups: {$processed}");
        $this->info("Created car_car rows: {$created}");
        $this->info("Linked verdict rows: {$linkedRows}");
        $this->info("Skipped groups: {$skipped}");

        return self::SUCCESS;
    }
}
