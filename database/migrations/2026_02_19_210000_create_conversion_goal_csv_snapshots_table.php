<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversion_goal_csv_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')
                ->constrained('conversion_goals')
                ->cascadeOnDelete();
            $table->unsignedInteger('rows_count')->default(0);
            $table->json('snapshot_json');
            $table->timestamps();

            $table->unique('goal_id');
            $table->index('rows_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_goal_csv_snapshots');
    }
};
