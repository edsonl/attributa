<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pageviews', function (Blueprint $table) {
            // FK para categoria de IP
            $table->foreignId('ip_category_id')
                ->nullable()
                ->after('ip')
                ->constrained('ip_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pageviews', function (Blueprint $table) {
            $table->dropForeign(['ip_category_id']);
            $table->dropColumn('ip_category_id');
        });
    }
};
