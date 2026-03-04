<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pageviews') && Schema::hasColumn('pageviews', 'timestamp_ms') && !Schema::hasColumn('pageviews', 'occurred_at')) {
            DB::statement('ALTER TABLE pageviews ADD COLUMN occurred_at DATETIME(3) NULL AFTER timezone');
            DB::statement('UPDATE pageviews SET occurred_at = COALESCE(created_at, UTC_TIMESTAMP(3)) WHERE occurred_at IS NULL');
            DB::statement('ALTER TABLE pageviews DROP COLUMN timestamp_ms');
            DB::statement('ALTER TABLE pageviews ADD INDEX pageviews_occurred_at_index (occurred_at)');
        }

        if (Schema::hasTable('pageview_events') && Schema::hasColumn('pageview_events', 'event_ts_ms') && !Schema::hasColumn('pageview_events', 'event_at')) {
            DB::statement('ALTER TABLE pageview_events ADD COLUMN event_at DATETIME(3) NULL AFTER form_has_user_data');
            DB::statement('UPDATE pageview_events SET event_at = COALESCE(created_at, UTC_TIMESTAMP(3)) WHERE event_at IS NULL');
            DB::statement('ALTER TABLE pageview_events DROP COLUMN event_ts_ms');
        }

        if (
            Schema::hasTable('campaign_visitors')
            && Schema::hasColumn('campaign_visitors', 'first_seen_at')
            && Schema::hasColumn('campaign_visitors', 'last_seen_at')
            && !$this->isDateTimeColumn('campaign_visitors', 'first_seen_at')
        ) {
            if (Schema::hasColumn('campaign_visitors', 'first_seen_at_tmp')) {
                DB::statement('ALTER TABLE campaign_visitors DROP COLUMN first_seen_at_tmp');
            }
            if (Schema::hasColumn('campaign_visitors', 'last_seen_at_tmp')) {
                DB::statement('ALTER TABLE campaign_visitors DROP COLUMN last_seen_at_tmp');
            }

            DB::statement('ALTER TABLE campaign_visitors ADD COLUMN first_seen_at_tmp DATETIME(3) NULL AFTER visitor_id');
            DB::statement('ALTER TABLE campaign_visitors ADD COLUMN last_seen_at_tmp DATETIME(3) NULL AFTER first_seen_at_tmp');
            DB::statement('UPDATE campaign_visitors SET first_seen_at_tmp = COALESCE(created_at, UTC_TIMESTAMP(3)), last_seen_at_tmp = COALESCE(updated_at, created_at, UTC_TIMESTAMP(3))');

            if ($this->hasIndex('campaign_visitors', 'campaign_visitors_last_seen_at_index')) {
                DB::statement('ALTER TABLE campaign_visitors DROP INDEX campaign_visitors_last_seen_at_index');
            }

            DB::statement('ALTER TABLE campaign_visitors DROP COLUMN first_seen_at');
            DB::statement('ALTER TABLE campaign_visitors DROP COLUMN last_seen_at');
            DB::statement('ALTER TABLE campaign_visitors CHANGE first_seen_at_tmp first_seen_at DATETIME(3) NULL');
            DB::statement('ALTER TABLE campaign_visitors CHANGE last_seen_at_tmp last_seen_at DATETIME(3) NULL');
            DB::statement('ALTER TABLE campaign_visitors ADD INDEX campaign_visitors_last_seen_at_index (last_seen_at)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pageviews') && Schema::hasColumn('pageviews', 'occurred_at') && !Schema::hasColumn('pageviews', 'timestamp_ms')) {
            if ($this->hasIndex('pageviews', 'pageviews_occurred_at_index')) {
                DB::statement('ALTER TABLE pageviews DROP INDEX pageviews_occurred_at_index');
            }
            DB::statement('ALTER TABLE pageviews ADD COLUMN timestamp_ms BIGINT NULL AFTER timezone');
            DB::statement('ALTER TABLE pageviews DROP COLUMN occurred_at');
        }

        if (Schema::hasTable('pageview_events') && Schema::hasColumn('pageview_events', 'event_at') && !Schema::hasColumn('pageview_events', 'event_ts_ms')) {
            DB::statement('ALTER TABLE pageview_events ADD COLUMN event_ts_ms BIGINT NULL AFTER form_has_user_data');
            DB::statement('ALTER TABLE pageview_events DROP COLUMN event_at');
        }

        if (Schema::hasTable('campaign_visitors') && Schema::hasColumn('campaign_visitors', 'first_seen_at') && Schema::hasColumn('campaign_visitors', 'last_seen_at')) {
            if ($this->hasIndex('campaign_visitors', 'campaign_visitors_last_seen_at_index')) {
                DB::statement('ALTER TABLE campaign_visitors DROP INDEX campaign_visitors_last_seen_at_index');
            }

            DB::statement('ALTER TABLE campaign_visitors MODIFY COLUMN first_seen_at BIGINT NULL');
            DB::statement('ALTER TABLE campaign_visitors MODIFY COLUMN last_seen_at BIGINT NULL');
            DB::statement('ALTER TABLE campaign_visitors ADD INDEX campaign_visitors_last_seen_at_index (last_seen_at)');
        }
    }

    protected function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    protected function isDateTimeColumn(string $table, string $column): bool
    {
        $database = DB::getDatabaseName();

        $type = DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->value('data_type');

        return in_array(strtolower((string) $type), ['datetime', 'timestamp'], true);
    }
};
