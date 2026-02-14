<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_goal_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('goal_id');
            $table->string('message', 255);
            $table->timestamps();

            $table->index('goal_id');
            $table->index('created_at');

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
