<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_source_categories', function (Blueprint $table) {
            // ID interno da categoria de tráfego
            $table->id();
            // Nome legível da categoria
            $table->string('name');
            // Slug técnico único
            $table->string('slug')->unique();
            // Nome do ícone exibido no front (opcional)
            $table->string('icon_name', 100)->nullable();
            // Cor hexadecimal para badge/indicador
            $table->string('color_hex', 7)->nullable();
            // Descrição opcional da categoria
            $table->text('description')->nullable();
            // Define se a categoria é padrão do sistema
            $table->boolean('is_system')->default(true);
            // Timestamps padrão do Laravel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_source_categories');
    }
};
