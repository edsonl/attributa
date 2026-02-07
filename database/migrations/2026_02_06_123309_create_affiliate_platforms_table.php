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

           // ID interno do canal
            $table->increments('id');

            // Nome legível do canal (ex: Dr Cash, Hotmart)
            $table->string('name');

            // Slug técnico do canal (ex: dr-cash, hotmart, etcc)
            $table->string('slug')->unique();

            // Indica se o canal está ativo para seleção em novas campanhas
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
