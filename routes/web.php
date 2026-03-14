<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {

    $a = 'hello world';

    return view('app', compact('a'));
})->where('any', '^(?!api/).*');
