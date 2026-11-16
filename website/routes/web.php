<?php

use App\Http\Controllers\LandingController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');
Route::get('/'.config('localization.default'), RedirectController::class)
    ->defaults('destination', '/')
    ->defaults('status', 301);
Route::get('/{locale}', LandingController::class)
    ->whereIn('locale', array_values(array_diff(
        array_keys(config('localization.locales')), [config('localization.default')],
    )))
    ->name('home.localized');
