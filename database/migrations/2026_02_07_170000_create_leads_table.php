<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno do lead
            $table->id();

            // Contexto do dono dos dados
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Campanha vinculada ao lead
            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            // Pageview relacionada (pode nao existir em alguns cenarios)
            $table->foreignId('pageview_id')
                ->nullable()
                ->constrained('pageviews')
                ->nullOnDelete();

            // Plataforma de afiliado que enviou o callback
            $table->foreignId('affiliate_platform_id')
                ->constrained('affiliate_platforms')
                ->cascadeOnDelete();

            // Identificador externo do lead na plataforma (uid/order_id/etc)
            $table->string('platform_lead_id', 120)->nullable();

            // Status canonico interno: processing, rejected, trash, approved, cancelled, refunded, chargeback
            $table->string('lead_status', 30)->default('processing');
            // Status bruto recebido da plataforma
            $table->string('status_raw', 80)->nullable();

            // Valor monetario informado no callback
            $table->decimal('payout_amount', 12, 2)->default(0);
            // Moeda do valor monetario
            $table->string('currency_code', 3)->default('USD');
            // Identificador inteiro da oferta na plataforma (ex.: offer)
            $table->unsignedBigInteger('offer_id')->nullable();

            // Momento do evento informado pela plataforma (quando houver)
            $table->dateTime('occurred_at')->nullable();

            // Payload bruto do callback para auditoria/debug
            $table->json('payload_json')->nullable();

            $table->timestamps();

            // Indices principais de leitura
            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('affiliate_platform_id');
            $table->index('lead_status');
            $table->index('offer_id');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['campaign_id', 'created_at']);
            $table->index(['affiliate_platform_id', 'created_at']);

            // Evita duplicidade por plataforma quando houver ID externo
            $table->unique(['affiliate_platform_id', 'platform_lead_id'], 'leads_platform_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
