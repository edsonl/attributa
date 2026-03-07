<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('form_lead_active')
                ->default(false)
                ->after('stream_code');

            $table->string('google_ads_form_key', 50)
                ->nullable()
                ->after('form_lead_active');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['form_lead_active', 'google_ads_form_key']);
        });
    }
};
