<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaign_country', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID da campanha relacionada
            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            // ID do país relacionado
            $table->foreignId('country_id')
                ->constrained('countries')
                ->cascadeOnDelete();

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

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_country');
    }
};
