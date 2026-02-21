<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pageviews') && Schema::hasColumn('pageviews', 'timestamp_ms')) {
            DB::statement('ALTER TABLE `pageviews` MODIFY `timestamp_ms` BIGINT UNSIGNED NULL');
        }

        if (Schema::hasTable('pageview_events') && Schema::hasColumn('pageview_events', 'event_ts_ms')) {
            DB::statement('ALTER TABLE `pageview_events` MODIFY `event_ts_ms` BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // Mantido sem rollback destrutivo para evitar perda de dados por overflow ao reduzir o tipo.
    }
};
