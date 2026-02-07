<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->dateTime('date')->nullable(); // default será tratado no modelo/controller
            $table->integer('time_minutes')->nullable(); // armazenar total em minutos (ex.: 90 = 1h30)
            $table->decimal('value', 10, 2)->nullable(); // valor em reais
            $table->boolean('paid')->default(false);
            $table->boolean('done')->default(false); //concluído
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_notes');
    }
};
