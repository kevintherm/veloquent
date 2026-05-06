<?php

use Veloquent\Core\Http\Controllers\StorageController;

Route::get('/storage/{path}', [StorageController::class, 'show'])->where('path', '.*');

Route::get('/{any}', function () {
    return view('velo::app');
})->where('any', '^(?!(api|storage)(/|$)).*');
