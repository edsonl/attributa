<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ads_conversions', function (Blueprint $table) {
            // ID interno da conversão
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');

            // FK para campanha relacionada
            $table->unsignedBigInteger('campaign_id');
            // FK para visita de origem
            $table->unsignedBigInteger('pageview_id');

            // Data/hora do evento de conversão
            $table->dateTime('conversion_event_time')
                ->default(DB::raw('CURRENT_TIMESTAMP'));

            // GCLID associado à conversão
            $table->string('gclid', 255)->nullable();

            // Nome da ação/conversão enviada
            $table->string('conversion_name')->nullable();

            // Valor monetário da conversão
            $table->decimal('conversion_value', 10, 2)
                ->default(1.00);

            // Moeda do valor da conversão
            $table->string('currency_code', 10)
                ->default('USD');

            // Status de envio da conversão para Google Ads
            $table->enum('google_upload_status', ['pending','processing','prossecing','success','exported','error'])
                ->default('pending');

            // Data/hora do último envio ao Google
            $table->dateTime('google_uploaded_at')->nullable();
            // Mensagem de erro do envio (se houver)
            $table->text('google_upload_error')->nullable();

            // Timestamps padrão do Laravel
            $table->timestamps();

            // Índice para filtros por nome de conversão
            $table->index('user_id');
            $table->index('conversion_name');

            // Integridade referencial da conversão
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('pageview_id')->references('id')->on('pageviews');
            $table->foreign('campaign_id')->references('id')->on('campaigns');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ads_conversions');
    }
};
