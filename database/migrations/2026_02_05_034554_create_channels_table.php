<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno do canal
            $table->id();

            // Nome legível do canal (ex: Google Ads, Meta Ads, WhatsApp)
            $table->string('name');

            // Slug técnico do canal (ex: google_ads, meta_ads)
            $table->string('slug')->unique();

            // Indica se o canal está ativo para seleção em novas campanhas
            $table->boolean('active')->default(true);

            // Timestamps padrão do Laravel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
