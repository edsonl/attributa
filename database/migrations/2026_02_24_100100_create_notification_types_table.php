<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_category_id')
                ->constrained('notification_categories')
                ->cascadeOnDelete();

            $table->string('name', 140);
            $table->string('slug', 90)->unique();
            $table->string('description', 255)->nullable();
            $table->string('default_title', 180)->nullable();
            $table->text('default_message')->nullable();
            $table->string('severity', 20)->default('info');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['notification_category_id', 'active', 'sort_order'], 'notification_types_category_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};

