<?php

use Illuminate\Support\Facades\Route;
use Veloquent\Core\Support\Http\Controllers\StorageController;

Route::get('/storage/{path}', [StorageController::class, 'show'])->where('path', '.*');

Route::get('/{any}', function () {
    return view('velo::app');
})->where('any', '^(?!(api|storage)(/|$)).*');
