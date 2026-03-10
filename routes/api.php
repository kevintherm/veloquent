<?php

use App\Domain\Auth\Controllers\AuthController;
use App\Domain\Collections\Controllers\CollectionController;
use App\Domain\Records\Controllers\RecordController;
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

Route::prefix('collections/{collection}/auth')->name('collections.auth.')->group(function () {
    Route::post('/authenticate', [AuthController::class, 'login'])->name('authenticate');
    Route::delete('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
});

Route::post('onboarding/superuser', [\App\Http\Controllers\OnboardingController::class, 'createSuperuser'])->name('onboarding.superuser.create');
