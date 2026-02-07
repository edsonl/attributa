<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pageviews', function (Blueprint $table) {
            $table->id();

            // vínculo com a campanha (via code)
            $table->string('campaign_code', 20)->index();

            // dados da visita
            $table->text('url');
            $table->text('referrer')->nullable();
            $table->string('user_agent')->nullable();

            // dados técnicos
            $table->ipAddress('ip')->nullable();
            $table->unsignedBigInteger('timestamp_ms')->nullable();

            $table->timestamps();

            // índice composto para análises futuras
            $table->index(['campaign_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pageviews');
    }
};
