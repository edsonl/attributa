<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_lookup_cache', function (Blueprint $table) {

            // ID interno do cache de consulta
            $table->id();

            // ID da categoria de IP classificada
            $table->foreignId('ip_category_id')
                ->nullable()
                ->constrained('ip_categories')
                ->nullOnDelete();

            // =============================
            // Identificação
            // =============================

            $table->string('ip', 45)->unique();
            // 45 suporta IPv4 e IPv6

            // =============================
            // Classificação
            // =============================

            // Flags técnicas (opcional mas inteligente guardar)
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_datacenter')->default(false);
            $table->boolean('is_bot')->default(false);

            // Score antifraude (se API fornecer)
            $table->integer('fraud_score')->nullable();

            // =============================
            // Geolocalização
            // =============================

            $table->string('country_code', 2)->nullable()->index();
            $table->string('country_name')->nullable();
            $table->string('region_name')->nullable();
            $table->string('city')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('timezone')->nullable();

            // =============================
            // Dados técnicos adicionais
            // =============================

            $table->string('isp')->nullable();
            $table->string('organization')->nullable();

            // Resposta completa da API (debug / auditoria)
            $table->json('api_response')->nullable();

            // Controle de revalidação futura
            $table->timestamp('last_checked_at')->nullable();

            // Timestamps padrão do Laravel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_lookup_cache');
    }
};
