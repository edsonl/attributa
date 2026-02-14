<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pageviews', function (Blueprint $table) {
            // ID interno da visita
            $table->id();
            $table->unsignedBigInteger('user_id');

            // FK para campanha principal (nova estrutura)
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('campaigns')
                ->nullOnDelete();

            // vínculo com a campanha (via code)
            $table->string('campaign_code', 20)->index();

            // dados da visita
            $table->text('url');
            $table->text('referrer')->nullable();
            $table->string('user_agent')->nullable();

            // GCLID capturado na visita
            $table->string('gclid', 255)->nullable();
            // Identificador de campanha do Google Ads
            $table->string('gad_campaignid')->nullable();

            // dados técnicos
            $table->ipAddress('ip')->nullable();
            // Categoria de risco/classificação de IP
            $table->foreignId('ip_category_id')
                ->nullable()
                ->constrained('ip_categories')
                ->nullOnDelete();

            // Código do país no padrão ISO2
            $table->string('country_code', 2)->nullable()->index();
            // Nome do país detectado
            $table->string('country_name')->nullable();
            // Estado/região detectada
            $table->string('region_name')->nullable();
            // Cidade detectada
            $table->string('city')->nullable();
            // Latitude da visita
            $table->decimal('latitude', 10, 7)->nullable();
            // Longitude da visita
            $table->decimal('longitude', 10, 7)->nullable();
            // Timezone detectado
            $table->string('timezone')->nullable();

            // Timestamp original da visita em milissegundos
            $table->unsignedBigInteger('timestamp_ms')->nullable();
            // Flag indicando se a visita converteu
            $table->boolean('conversion')->default(false);

            // Timestamps padrão do Laravel
            $table->timestamps();

            // índice composto para análises futuras
            $table->index('user_id');
            $table->index(['campaign_code', 'created_at']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pageviews');
    }
};
