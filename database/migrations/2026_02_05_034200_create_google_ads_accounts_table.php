<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('google_ads_accounts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno da conta conectada
            $table->id();

            // Dono da conta dentro do Attributa
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // ID da conta Google Ads (ex: 123-456-7890)
            $table->string('google_ads_customer_id', 32);

            // Apenas informativo (UX)
            $table->string('email')->nullable();

            // OAuth
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();

            // Status da integração
            $table->boolean('active')->default(true);

            // Timestamps padrão do Laravel
            $table->timestamps();

            // Índices
            $table->unique(['user_id', 'google_ads_customer_id']);
            $table->index('google_ads_customer_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_ads_accounts');
    }
};
