<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaign_country', function (Blueprint $table) {

            // ID da campanha relacionada
            $table->unsignedBigInteger('campaign_id');

            // ID do país relacionado
            $table->unsignedBigInteger('country_id');

            // Timestamps padrão (quando o país foi associado à campanha)
            $table->timestamps();

            /*
             |--------------------------------------------------------------------------
             | Índices e chaves
             |--------------------------------------------------------------------------
             */

            // Evita duplicidade do mesmo país na mesma campanha
            $table->unique(['campaign_id', 'country_id']);

            // Índices para performance em joins e filtros
            $table->index('campaign_id');
            $table->index('country_id');

            /*
             |--------------------------------------------------------------------------
             | Foreign Keys
             |--------------------------------------------------------------------------
             | Definidas explicitamente para manter integridade referencial
             | (onDelete cascade evita lixo quando campanha ou país é removido)
             */

            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->onDelete('cascade');

            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_country');
    }
};
