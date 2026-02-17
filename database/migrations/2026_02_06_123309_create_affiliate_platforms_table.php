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

           // ID interno da plataforma de afiliado
            $table->increments('id');

            // Nome legível da plataforma (ex: DrCash, Hotmart)
            $table->string('name');

            // Slug técnico da plataforma (ex: dr-cash, hotmart)
            $table->string('slug')->unique();

            // Indica se a plataforma está ativa para seleção em campanhas
            $table->boolean('active')->default(true);

            // Timestamps padrão do Laravel
            $table->timestamps();

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
