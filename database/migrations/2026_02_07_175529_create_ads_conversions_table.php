<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ads_conversions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno da conversão
            $table->id();

            // IDs relacionais da conversão
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('campaigns')
                ->nullOnDelete();
            $table->foreignId('pageview_id')
                ->nullable()
                ->constrained('pageviews')
                ->nullOnDelete();

            // Data/hora do evento de conversão
            $table->dateTime('conversion_event_time')
                ->default(DB::raw('CURRENT_TIMESTAMP'));

            // Identificadores de clique para importação no Google Ads
            $table->string('gclid', 150)->nullable();
            $table->string('gbraid', 150)->nullable();
            $table->string('wbraid', 150)->nullable();

            // Metadados de contexto da conversão
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Nome da ação/conversão enviada
            $table->string('conversion_name')->nullable();

            // Valor monetário da conversão
            $table->decimal('conversion_value', 10, 2)
                ->default(1.00);

            // Moeda do valor da conversão
            $table->string('currency_code', 10)
                ->default('USD');

            // Status de envio da conversão para Google Ads
            $table->unsignedTinyInteger('google_upload_status')
                ->default(0)
                ->comment('0=pending,1=processing,2=processing_export,3=success,4=exported,5=error');

            // Data/hora do último envio ao Google
            $table->dateTime('google_uploaded_at')->nullable();
            // Mensagem de erro do envio (se houver)
            $table->text('google_upload_error')->nullable();
            $table->boolean('is_manual')->default(false);

            // Timestamps padrão do Laravel
            $table->timestamps();

            // Índice para filtros por nome de conversão
            $table->index('user_id');
            $table->index('conversion_name');
            $table->index('campaign_id');
            $table->index('gclid');
            $table->index('gbraid');
            $table->index('wbraid');
            $table->index('ip_address');
            $table->index('is_manual');
            $table->index('created_at');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ads_conversions');
    }
};
