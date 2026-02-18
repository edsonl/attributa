<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timezones', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno do fuso horário
            $table->id();
            // Identificador IANA (ex: America/Sao_Paulo)
            $table->string('identifier', 64)->unique();
            // Nome legível para seleção em telas
            $table->string('label', 100);
            // Deslocamento UTC (ex: -03:00)
            $table->string('utc_offset', 6);
            // Define se o fuso está disponível para uso
            $table->boolean('active')->default(true);
            // Timestamps padrão do Laravel
            $table->timestamps();

            // Índices para filtros administrativos
            $table->index('active');
            $table->index('utc_offset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timezones');
    }
};
