<?php

use App\Http\Controllers\FaqController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/faq', FaqController::class)->name('faq');
