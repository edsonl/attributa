<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pageview_events') && !Schema::hasColumn('pageview_events', 'event_reason')) {
            DB::statement('ALTER TABLE pageview_events ADD COLUMN event_reason VARCHAR(64) NULL AFTER element_id');
        }

        if (Schema::hasTable('pageview_events') && Schema::hasColumn('pageview_events', 'event_reason')) {
            DB::statement("
                UPDATE pageview_events
                SET event_reason = CASE
                    WHEN event_type = 'page_engaged' AND element_id LIKE 'engagement_reason:%' THEN SUBSTRING_INDEX(element_id, ':', -1)
                    WHEN event_type = 'navigation_reload' AND event_reason IS NULL THEN 'reload'
                    ELSE event_reason
                END
                WHERE event_reason IS NULL OR event_reason = ''
            ");

            DB::statement("
                UPDATE pageview_events
                SET element_name = CASE
                    WHEN event_type = 'page_engaged' THEN 'Engajamento da página'
                    WHEN event_type = 'navigation_reload' THEN 'Recarregamento da página'
                    ELSE element_name
                END
            ");

            DB::statement("
                UPDATE pageview_events
                SET element_id = NULL
                WHERE event_type IN ('page_engaged', 'navigation_reload')
            ");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pageview_events') && Schema::hasColumn('pageview_events', 'event_reason')) {
            DB::statement('ALTER TABLE pageview_events DROP COLUMN event_reason');
        }
    }
};
