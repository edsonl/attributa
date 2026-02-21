<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pageviews', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno da visita
            $table->id();

            // IDs relacionais
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('campaigns')
                ->nullOnDelete();
            $table->foreignId('traffic_source_category_id')
                ->nullable()
                ->constrained('traffic_source_categories')
                ->nullOnDelete();
            $table->foreignId('device_category_id')
                ->nullable()
                ->constrained('device_categories')
                ->nullOnDelete();
            $table->foreignId('browser_id')
                ->nullable()
                ->constrained('browsers')
                ->nullOnDelete();
            $table->foreignId('ip_category_id')
                ->nullable()
                ->constrained('ip_categories')
                ->nullOnDelete();

            // dados da visita
            $table->text('url');
            $table->text('landing_url')->nullable();
            $table->text('referrer')->nullable();
            $table->text('user_agent')->nullable();

            // dados de aquisição e identificação de tráfego
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            // GCLID capturado na visita
            $table->string('gclid', 150)->nullable();
            // Identificador de campanha do Google Ads
            $table->string('gad_campaignid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('ttclid')->nullable();
            $table->string('msclkid')->nullable();
            $table->string('wbraid')->nullable();
            $table->string('gbraid')->nullable();

            // categoria de origem de tráfego
            $table->string('traffic_source_reason')->nullable();

            // dispositivo / navegador
            $table->string('device_type')->nullable();
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();
            $table->string('browser_name')->nullable();
            $table->string('browser_version')->nullable();

            // dimensões de tela e viewport
            $table->unsignedInteger('screen_width')->nullable();
            $table->unsignedInteger('screen_height')->nullable();
            $table->unsignedInteger('viewport_width')->nullable();
            $table->unsignedInteger('viewport_height')->nullable();
            $table->decimal('device_pixel_ratio', 6, 2)->nullable();
            $table->string('platform')->nullable();
            $table->string('language', 20)->nullable();

            // dados técnicos
            $table->string('ip', 45)->nullable();

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
            $table->index('traffic_source_category_id');
            $table->index('device_category_id');
            $table->index('browser_id');
            $table->index('utm_source');
            $table->index('utm_medium');
            $table->index('campaign_id');
            $table->index('gclid');
            $table->index('created_at');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pageviews');
    }
};
