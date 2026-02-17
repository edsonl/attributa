<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_goal_logs', function (Blueprint $table) {
            // ID interno do log
            $table->bigIncrements('id');

            // ID da meta de conversão relacionada
            $table->unsignedBigInteger('goal_id');

            // Mensagem curta do evento registrado
            $table->string('message', 255);

            // Timestamps padrão do Laravel
            $table->timestamps();

            // Índices para consulta por meta e ordenação temporal
            $table->index('goal_id');
            $table->index('created_at');

            // Integridade referencial do log
            $table->foreign('goal_id')
                ->references('id')
                ->on('conversion_goals')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_goal_logs');
    }
};
