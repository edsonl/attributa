<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversion_goals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('user_slug_id', 32);
            $table->string('googleads_password', 25);
            $table->unsignedBigInteger('timezone_id');
            $table->string('goal_code',60);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('active');
            $table->index('user_id');
            $table->index('user_slug_id');
            $table->index('googleads_password');
            $table->index('timezone_id');
            $table->index('goal_code');
            $table->unique(['user_id', 'goal_code']);

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
