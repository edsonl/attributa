<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversion_goals', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno da meta de conversão
            $table->id();

            // IDs relacionais
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('timezone_id')
                ->constrained('timezones');

            // Identificadores e credenciais da meta
            $table->string('user_slug_id', 32);
            $table->string('googleads_password', 25);

            // Código único da meta de conversão por usuário
            $table->string('goal_code', 30);

            // Controle de ativação da meta
            $table->boolean('active')->default(true);

            // Timestamps e soft delete para histórico
            $table->timestamps();
            $table->softDeletes();

            // Índices para filtros e busca
            $table->index('active');
            $table->index('user_id');
            $table->index('user_slug_id');
            $table->index('googleads_password');
            $table->index('timezone_id');
            $table->index('goal_code');
            $table->unique(['user_id', 'goal_code']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_goals');
    }
};
