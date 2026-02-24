<?php

use App\Http\Controllers\GoogleAdsConversionsController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\ConversionCallbackPlatformController;
use App\Http\Controllers\LeadCallbackPlatformController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConversionCallbackController;

Route::post('/tracking/collect', [TrackingController::class, 'collect'])
    ->middleware('throttle:tracking')
->name('tracking.collect');

Route::post('/tracking/event', [TrackingController::class, 'event'])
    ->middleware('throttle:tracking')
    ->name('tracking.event');

Route::get('/tracking/script.js', [TrackingController::class, 'script'])->name('tracking.script');

Route::get(
    '/google-ads/conversions/{userSlugId}/{goalCode}.csv',
    [GoogleAdsConversionsController::class, 'goalExport']
)->name('api.google-ads.conversions.goal');


//Resposta de conversão plataforma de afiliado
Route::get('/callback/conversion', [ConversionCallbackController::class, 'handle']);

Route::get(
    '/callback-platform/{platformSlug}/{userCode}',
    [ConversionCallbackPlatformController::class, 'handle']
)->name('api.callback-platform.handle');

Route::get(
    '/get/platform-lead/{platformSlug}/{userCode}',
    [LeadCallbackPlatformController::class, 'handle']
)->name('api.get.platform-lead.handle');
