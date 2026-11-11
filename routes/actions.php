<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Newsletter\Http\Controllers\ActionsController;

// At /!/newsletter/...

// The sign-up form. Limited per visitor.
Route::post('subscribe', [ActionsController::class, 'subscribe'])->middleware('throttle:newsletter')->name('subscribe');

// The links in the emails. They're signed, so they can't be guessed or
// changed: confirming lasts a few days, unsubscribing doesn't expire.
// Each opens a page with a button, as mail apps and virus checkers open
// links in emails by themselves; only the button (a POST) does anything.
// The signature stands in for a CSRF token: the service provider exempts
// these from Laravel's CSRF check.
Route::get('confirm/{subscriber}', [ActionsController::class, 'confirmPage'])->name('confirm');
Route::post('confirm/{subscriber}', [ActionsController::class, 'confirm'])->name('confirm.post');

Route::middleware('signed')->group(function () {
    Route::get('unsubscribe/{subscriber}', [ActionsController::class, 'unsubscribePage'])->name('unsubscribe');

    // Mail apps' one-click unsubscribe (RFC 8058) posts here too.
    Route::post('unsubscribe/{subscriber}', [ActionsController::class, 'unsubscribe'])->name('unsubscribe.post');
});
