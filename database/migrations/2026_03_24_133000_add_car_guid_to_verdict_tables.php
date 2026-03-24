<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['verdict_goodcars', 'verdict_badcars'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'car_GUID')) {
                DB::statement("ALTER TABLE {$table} ADD COLUMN car_GUID CHAR(36) NULL");
            }

            // Ensure index exists for fast grouping and joins.
            try {
                DB::statement("ALTER TABLE {$table} ADD INDEX idx_{$table}_car_guid (car_GUID)");
            } catch (Throwable) {
                // Index may already exist.
            }

            // Add FK when storage engine supports it.
            try {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT fk_{$table}_car_guid FOREIGN KEY (car_GUID) REFERENCES car_car(Guid) ON UPDATE CASCADE ON DELETE SET NULL");
            } catch (Throwable) {
                // Constraint may already exist or table engine may not support FK.
            }
        }
    }

    public function down(): void
    {
        foreach (['verdict_goodcars', 'verdict_badcars'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY fk_{$table}_car_guid");
            } catch (Throwable) {
                // Ignore when FK is missing.
            }

            try {
                DB::statement("ALTER TABLE {$table} DROP INDEX idx_{$table}_car_guid");
            } catch (Throwable) {
                // Ignore when index is missing.
            }

            if (Schema::hasColumn($table, 'car_GUID')) {
                DB::statement("ALTER TABLE {$table} DROP COLUMN car_GUID");
            }
        }
    }
};
