<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaign_visitors', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();
            // Mesmo visitor_id pode aparecer em campanhas diferentes.
            $table->bigInteger('visitor_id');
            $table->bigInteger('first_seen_at')->nullable();
            $table->bigInteger('last_seen_at')->nullable();
            $table->bigInteger('hits')->default(1);
            $table->timestamps();

            $table->unique(['campaign_id', 'visitor_id']);
            $table->index('campaign_id');
            $table->index('visitor_id');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_visitors');
    }
};
