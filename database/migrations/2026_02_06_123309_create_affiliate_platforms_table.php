<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('affiliate_platforms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

           // ID interno da plataforma de afiliado
            $table->id();

            // Nome legível da plataforma (ex: DrCash, Hotmart)
            $table->string('name')->comment('Nome legível da plataforma');

            // Slug técnico da plataforma (ex: dr-cash, hotmart)
            $table->string('slug')->unique()->comment('Slug técnico único da plataforma');

            // Tipo de integração da plataforma (ex: postback_get)
            $table->string('integration_type', 30)->default('postback_get')->comment('Tipo de integração (ex: postback_get)');

            // Mapeamento dos parâmetros de tracking (origem -> destino)
            $table->json('tracking_param_mapping')->nullable()->comment('Mapeamento de tracking: origem => retorno');

            // Mapeamento dos parâmetros usados para salvar a conversão (ex: valor e moeda)
            $table->json('conversion_param_mapping')->nullable()->comment('Mapeamento fixo de conversão: conversion_value/currency_code => parâmetro de retorno');

            // Parâmetros adicionais que a plataforma envia no postback
            $table->json('postback_additional_params')->nullable()->comment('Parâmetros adicionais esperados no postback');

            // Indica se a plataforma está ativa para seleção em campanhas
            $table->boolean('active')->default(true)->comment('Indica se a plataforma está ativa');

            // Timestamps padrão do Laravel
            $table->timestamps();

            $table->index('integration_type');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_platforms');
    }
};
