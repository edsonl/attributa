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
        Schema::create('ip_categories', function (Blueprint $table) {
            // ID interno da categoria
            $table->id();

            // Nome visível da categoria
            $table->string('name');

            // Slug único para uso interno
            $table->string('slug')->unique();

            // Cor em hexadecimal (ex: #16A34A)
            $table->string('color_hex', 7);

            // Descrição opcional
            $table->text('description')->nullable();

            // Controle para categorias internas do sistema
            $table->boolean('is_system')->default(true);

            // Timestamps padrão do Laravel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_categories');
    }
};
