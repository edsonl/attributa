<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pageviews', function (Blueprint $table) {

            // =============================
            // Geolocalização do visitante
            // =============================

            $table->string('country_code', 2)
                ->nullable()
                ->index()
                ->after('ip_category_id');
            // ISO2 (BR, US, AR...)

            $table->string('country_name')
                ->nullable()
                ->after('country_code');

            $table->string('region_name')
                ->nullable()
                ->after('country_name');
            // Estado / Região

            $table->string('city')
                ->nullable()
                ->after('region_name');

            $table->decimal('latitude', 10, 7)
                ->nullable()
                ->after('city');
            // Precisão adequada para mapas

            $table->decimal('longitude', 10, 7)
                ->nullable()
                ->after('latitude');

            $table->string('timezone')
                ->nullable()
                ->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('pageviews', function (Blueprint $table) {

            $table->dropIndex(['country_code']);

            $table->dropColumn([
                'country_code',
                'country_name',
                'region_name',
                'city',
                'latitude',
                'longitude',
                'timezone',
            ]);
        });
    }
};
