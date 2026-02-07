<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ads_conversions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('pageview_id');

            $table->dateTime('conversion_event_time')
                ->useCurrent();

            $table->string('gclid', 255)
                ->nullable();

            $table->string('conversion_name')
                ->nullable();

            $table->decimal('conversion_value', 10, 2)
                ->default(1.00);

            $table->string('currency_code', 10)
                ->default('USD');

            $table->timestamps();

            $table->foreign('pageview_id')->references('id')->on('pageviews');
            $table->foreign('campaign_id')->references('id')->on('campaigns');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_conversions');
    }
};
