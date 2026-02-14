<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timezones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identifier', 64)->unique();
            $table->string('label', 100);
            $table->string('utc_offset', 6);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
            $table->index('utc_offset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timezones');
    }
};
