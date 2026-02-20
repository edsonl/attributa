<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pageview_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            $table->foreignId('pageview_id')
                ->constrained('pageviews')
                ->cascadeOnDelete();

            $table->string('event_type', 30);
            $table->text('target_url')->nullable();
            $table->string('element_id', 191)->nullable();
            $table->string('element_name', 191)->nullable();
            $table->string('element_classes', 500)->nullable();
            $table->unsignedInteger('form_fields_checked')->nullable();
            $table->unsignedInteger('form_fields_filled')->nullable();
            $table->boolean('form_has_user_data')->nullable();
            $table->unsignedBigInteger('event_ts_ms')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('pageview_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pageview_events');
    }
};
