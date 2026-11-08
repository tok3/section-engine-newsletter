<?php

use Illuminate\Support\Facades\Route;
use Tok3\SectionEngineNewsletter\Http\Controllers\ConfirmController;
use Tok3\SectionEngineNewsletter\Http\Controllers\SubscribeController;

Route::middleware('web')->prefix(trim(config('section-engine-newsletter.route_prefix', 'newsletter'), '/'))->name('section-engine-newsletter.')->group(function (): void {
    Route::get('confirm/{subscription}/{token}', [ConfirmController::class, 'show'])->name('confirm.show');
    Route::post('confirm/{subscription}/{token}', [ConfirmController::class, 'confirm'])->name('confirm');
    Route::post('subscribe', SubscribeController::class)->name('subscribe');
});
