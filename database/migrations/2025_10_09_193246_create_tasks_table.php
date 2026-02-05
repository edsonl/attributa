<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {

            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending','in_progress','done'])->default('pending');
            $table->enum('priority', ['low','medium','high'])->default('medium');
            $table->date('due_date')->nullable();

            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();

            //relação opcional com empresa
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status']);
            $table->index(['priority']);
            $table->index(['due_date']);
            $table->index(['assigned_to_id']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
