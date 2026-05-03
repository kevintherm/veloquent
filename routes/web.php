<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    return view('velo::app');
})->where('any', '^(?!(api|storage)(/|$)).*');
