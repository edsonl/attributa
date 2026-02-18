<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno da campanha
            $table->id();

            // IDs relacionais
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            // Default 2 = status "active" (seed em campaign_statuses)
            $table->foreignId('campaign_status_id')
                ->default(2)
                ->constrained('campaign_statuses');
            $table->foreignId('conversion_goal_id')
                ->nullable()
                ->constrained('conversion_goals');

            // Conta Google Ads vinculada (opcional)
            $table->foreignId('google_ads_account_id')
                ->nullable()
                ->constrained('google_ads_accounts');

            // Canal principal da campanha (ex: Google Ads, Meta Ads, WhatsApp)
            $table->foreignId('channel_id');

            // Plataforma de afiliado da campanha (ex: DrCash, Hotmart)
            $table->foreignId('affiliate_platform_id');

            // Código único da campanha baseado em hashid (margem para crescimento futuro)
            $table->string('code', 32)->unique();

            // Nome da campanha / produto (exibição interna)
            $table->string('name');

            // URL do produto autorizada para envio do tracking (origem canônica)
            $table->string('product_url', 255)->nullable();

            // ID da campanha na plataforma externa (Google Ads, Meta, etc.)
            $table->string('external_campaign_id')->nullable();

            // Data e hora de início da campanha (timezone-aware)
            $table->dateTime('starts_at')->nullable();

            // Data e hora de término da campanha (timezone-aware)
            $table->dateTime('ends_at')->nullable();

            // Orçamento total da campanha (valor monetário, ex: BRL)
            $table->decimal('budget_total', 15, 2)->nullable();

            // Valor de comissão associado à campanha (lead, venda ou afiliado)
            $table->decimal('commission_value', 15, 2)->nullable();

            // Timezone principal da campanha (ex: America/Sao_Paulo)
            $table->string('timezone')->default(config('app.timezone'));

            // Metadados flexíveis para integrações e configurações específicas
            $table->json('metadata')->nullable();

            // Timestamps padrão do Laravel
            $table->timestamps();

            // Soft delete para preservar histórico da campanha
            $table->softDeletes();

            // Índices auxiliares para filtros frequentes
            $table->index('user_id');
            $table->index('campaign_status_id');
            $table->index('channel_id');
            $table->index('external_campaign_id');
            $table->index('affiliate_platform_id');
            $table->index('conversion_goal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
