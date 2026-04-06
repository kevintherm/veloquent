<?php

use Illuminate\Support\Facades\Route;

Route::get('/dashboard.png', function () {
    return response()->file(base_path('docs/dashboard.png'));
})->name('dashboard-image');

Route::get('/landing', function () {
    return file_get_contents(base_path('docs/welcome.html'));
})->name('landing');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!(api|'.config('velo.docs.path', 'docs').')(/|$)).*');
