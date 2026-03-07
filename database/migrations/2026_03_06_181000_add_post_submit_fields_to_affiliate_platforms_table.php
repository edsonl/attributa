<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('affiliate_platforms', function (Blueprint $table) {
            $table->string('postback_url', 500)
                ->nullable()
                ->after('postback_additional_params');

            $table->string('api_post_key')
                ->nullable()
                ->after('postback_url');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_platforms', function (Blueprint $table) {
            $table->dropColumn(['postback_url', 'api_post_key']);
        });
    }
};
