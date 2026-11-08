<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Tok3\SectionEngineNewsletter\Http\Controllers\ConfirmController;
use Tok3\SectionEngineNewsletter\Http\Controllers\SubscribeController;
use Tok3\SectionEngineNewsletter\Http\Controllers\UnsubscribeController;

Route::middleware('web')->prefix(trim(config('section-engine-newsletter.route_prefix', 'newsletter'), '/'))->name('section-engine-newsletter.')->group(function (): void {
    Route::get('unsubscribe/{subscription}', [UnsubscribeController::class, 'show'])->middleware('signed')->name('unsubscribe.show');
    Route::post('unsubscribe/{subscription}', [UnsubscribeController::class, 'unsubscribe'])->middleware('signed')->name('unsubscribe');
    Route::post('unsubscribe/{subscription}/one-click', [UnsubscribeController::class, 'oneClick'])
        ->middleware('signed')->withoutMiddleware([
            VerifyCsrfToken::class,
            ValidateCsrfToken::class,
            PreventRequestForgery::class,
        ])->name('unsubscribe.one-click');
    Route::get('confirm/{subscription}/{token}', [ConfirmController::class, 'show'])->name('confirm.show');
    Route::post('confirm/{subscription}/{token}', [ConfirmController::class, 'confirm'])->name('confirm');
    Route::post('subscribe', SubscribeController::class)->name('subscribe');
});
