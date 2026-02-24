<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('notification_type_id')
                ->nullable()
                ->constrained('notification_types')
                ->nullOnDelete();

            $table->string('title', 180);
            $table->text('message');
            $table->json('payload_json')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20)->default('unread');
            $table->dateTime('read_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('sent_email_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'notifications_user_status_created_idx');
            $table->index(['source_type', 'source_id'], 'notifications_source_idx');
            $table->index(['sent_email_at', 'created_at'], 'notifications_sent_email_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
