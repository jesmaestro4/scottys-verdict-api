<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ExportOpenApiYaml extends Command
{
    protected $signature = 'scotty:export-openapi';

    protected $description = 'Generate Swagger docs and export the OpenAPI YAML to public/openapi.yaml';

    public function handle(): int
    {
        Artisan::call('l5-swagger:generate');
        $this->output->write(Artisan::output());

        $source = storage_path('api-docs/api-docs.yaml');

        if (! File::exists($source)) {
            $this->error('OpenAPI YAML was not generated.');

            return self::FAILURE;
        }

        File::copy($source, public_path('openapi.yaml'));

        $this->info('Exported OpenAPI YAML to public/openapi.yaml');

        return self::SUCCESS;
    }
}
