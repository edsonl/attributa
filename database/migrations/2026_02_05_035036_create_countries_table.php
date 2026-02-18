<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno do país
            $table->id();

            // Código ISO 2 do país (ex: BR, US, IT)
            $table->char('iso2', 2)->index();

            // Código ISO 3 do país (ex: BRA, USA, ITA)
            $table->char('iso3', 3)->index();

            // Nome do país (exibição principal no sistema)
            $table->string('name')->index();

            // Código da moeda padrão do país (ex: BRL, USD, EUR)
            $table->char('currency', 3)->index();

            // Símbolo da moeda (ex: R$, $, €)
            $table->string('currency_symbol', 5)->nullable();

            // Timezone padrão do país (ex: America/Sao_Paulo)
            $table->string('timezone_default');

            // Define se o país está ativo para seleção no sistema
            $table->boolean('active')->default(true);

            // Timestamps padrão do Laravel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
