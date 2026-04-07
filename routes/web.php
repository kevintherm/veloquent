<?php

use App\Http\Controllers\FaqController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/faq', FaqController::class)->name('faq');
