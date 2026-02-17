<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browsers', function (Blueprint $table) {
            // ID interno do navegador
            $table->id();
            // Nome legível do navegador
            $table->string('name');
            // Slug técnico único
            $table->string('slug')->unique();
            // Nome do ícone exibido no front (opcional)
            $table->string('icon_name', 100)->nullable();
            // Cor hexadecimal para badge/indicador
            $table->string('color_hex', 7)->nullable();
            // Descrição opcional do navegador
            $table->text('description')->nullable();
            // Define se o registro é padrão do sistema
            $table->boolean('is_system')->default(true);
            // Timestamps padrão do Laravel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browsers');
    }
};
