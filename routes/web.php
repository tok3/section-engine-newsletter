<?php

use Illuminate\Support\Facades\Route;
use Tok3\SectionEngineNewsletter\Http\Controllers\SubscribeController;

Route::middleware('web')->prefix(trim(config('section-engine-newsletter.route_prefix', 'newsletter'), '/'))->name('section-engine-newsletter.')->group(function (): void {
    Route::post('subscribe', SubscribeController::class)->name('subscribe');
});
