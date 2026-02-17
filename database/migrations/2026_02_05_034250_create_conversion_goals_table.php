<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversion_goals', function (Blueprint $table) {
            // ID interno da meta de conversão
            $table->bigIncrements('id');

            // IDs relacionais
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('timezone_id');

            // Identificadores e credenciais da meta
            $table->string('user_slug_id', 32);
            $table->string('googleads_password', 25);

            // Código único da meta de conversão por usuário
            $table->string('goal_code',60);

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

            // Integridade referencial
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('timezone_id')
                ->references('id')
                ->on('timezones');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_goals');
    }
};
