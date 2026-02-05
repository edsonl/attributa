<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // Nome (obrigatório)
            $table->string('corporate_name')->nullable(); // Razão social (opcional)
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();    // email (opcional)
            $table->string('site')->nullable();     // site (opcional)
            $table->string('cnpj',20)->nullable();    // cnpj (opcional)
            $table->string('notes', 255)->nullable(); // Observações (até 255)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
            $table->index(['corporate_name']);
            $table->index(['email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
