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
            $table->bigIncrements('id');

            // Dono da conta dentro do Attributa
            $table->unsignedBigInteger('user_id');

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

            $table->timestamps();

            // Índices
            $table->unique(['user_id', 'google_ads_customer_id']);
            $table->index('google_ads_customer_id');

            // FK
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
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
