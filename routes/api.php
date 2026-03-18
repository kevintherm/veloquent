<?php

use App\Domain\Auth\Controllers\AuthController;
use App\Domain\Collections\Controllers\CollectionController;
use App\Domain\Realtime\Controllers\SubscribeController;
use App\Domain\Records\Controllers\RecordController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Http\Request;
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
    Route::post('/login', [AuthController::class, 'login'])->name('authenticate');
    Route::delete('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
});

Route::get('/onboarding/initialized', [OnboardingController::class, 'initialized'])->name('onboarding.initialized.status');
Route::post('/onboarding/superuser', [OnboardingController::class, 'createSuperuser'])->name('onboarding.superuser.create');
Route::get('/user', fn (Request $request) => $request->user());

Route::middleware('auth:api')->group(function () {
    Route::post('/collections/{collection}/subscribe', [SubscribeController::class, 'subscribe']);
    Route::delete('/collections/{collection}/subscribe', [SubscribeController::class, 'unsubscribe']);
});
