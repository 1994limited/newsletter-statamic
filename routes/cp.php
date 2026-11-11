<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\Newsletter\Http\Controllers\CP\SubscribersController;

Route::prefix('newsletter')->name('newsletter.')->group(function () {
    Route::get('/', [SubscribersController::class, 'index'])->name('index');
    Route::get('export', [SubscribersController::class, 'export'])->name('export');
    Route::delete('subscribers/{subscriber}', [SubscribersController::class, 'destroy'])->name('destroy');
});
