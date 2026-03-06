<?php

use App\Domain\Collections\Controllers\CollectionController;
use App\Domain\Records\Controllers\RecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SuperuserController;
use Illuminate\Support\Facades\Route;

Route::prefix('collections')->group(function () {
    Route::get('/', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::put('/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::patch('/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::delete('/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
});

Route::prefix('collections/{collection}/records')->group(function () {
    Route::get('/', [RecordController::class, 'index'])->name('records.index');
    Route::post('/', [RecordController::class, 'store'])->name('records.store');
    Route::get('/{record}', [RecordController::class, 'show'])->name('records.show');
    Route::put('/{record}', [RecordController::class, 'update'])->name('records.update');
    Route::patch('/{record}', [RecordController::class, 'update'])->name('records.update');
    Route::delete('/{record}', [RecordController::class, 'destroy'])->name('records.destroy');
});

Route::prefix('collections/superusers')->name('superusers.')->group(function () {
    Route::get('/', [SuperuserController::class, 'index'])->middleware('jwt.auth')->name('index');
    Route::get('/{superuser}', [SuperuserController::class, 'show'])->middleware('jwt.auth')->name('show');
    Route::post('/', [SuperuserController::class, 'store'])->name('store');
    Route::put('/{superuser}', [SuperuserController::class, 'update'])->name('update');
    Route::patch('/{superuser}', [SuperuserController::class, 'update'])->name('update');
    Route::delete('/{superuser}', [SuperuserController::class, 'destroy'])->name('destroy');

    Route::prefix('auth')->group(function () {
        Route::post('/login', [SuperuserController::class, 'login'])->name('login');
        Route::post('/logout', [SuperuserController::class, 'logout'])->middleware('jwt.auth')->name('logout');
        Route::post('/refresh', [SuperuserController::class, 'refresh'])->middleware('jwt.auth')->name('refresh');
    });
});

Route::prefix('collection/{collection}/auth')->name('collection.auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('jwt.auth')->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->middleware('jwt.auth')->name('me');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('jwt.auth')->name('refresh');
});
