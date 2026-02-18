<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_goal_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            // ID interno do log
            $table->id();

            // ID da meta de conversão relacionada
            $table->foreignId('goal_id')
                ->constrained('conversion_goals')
                ->cascadeOnDelete();

            // Mensagem curta do evento registrado
            $table->string('message', 255);
            // Status visual do log (usado para bolinha de cor na tela)
            $table->enum('status', ['success', 'warning', 'error', 'info'])->default('info');

            // Timestamps padrão do Laravel
            $table->timestamps();

            // Índices para consulta por meta e ordenação temporal
            $table->index('goal_id');
            $table->index('status');
            $table->index('created_at');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_goal_logs');
    }
};
