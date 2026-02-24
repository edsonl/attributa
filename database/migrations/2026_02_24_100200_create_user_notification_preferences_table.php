<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('notification_type_id')
                ->constrained('notification_types')
                ->cascadeOnDelete();

            $table->boolean('enabled_in_app')->default(true);
            $table->boolean('enabled_email')->default(false);
            $table->boolean('enabled_push')->default(false);
            $table->string('frequency', 20)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notification_type_id'], 'user_notification_preferences_unique');
            $table->index(['user_id', 'enabled_in_app'], 'user_notification_preferences_user_in_app_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};

