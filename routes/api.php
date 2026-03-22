<?php

use App\Domain\Auth\Controllers\AuthController;
use App\Domain\Collections\Controllers\CollectionController;
use App\Domain\Realtime\Controllers\SubscribeController;
use App\Domain\Records\Controllers\RecordController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\SuperuserOnly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('collections')->group(function () {
    Route::get('/', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::match(['PUT', 'PATCH'], '/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::delete('/{collection}/truncate', [CollectionController::class, 'truncate'])->name('collections.truncate');
    Route::delete('/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
});

Route::prefix('collections/{collection}/records')->group(function () {
    Route::get('/', [RecordController::class, 'index'])->name('records.index');
    Route::post('/', [RecordController::class, 'store'])->name('records.store');
    Route::get('/{record}', [RecordController::class, 'show'])->name('records.show');
    Route::match(['PUT', 'PATCH'], '/{record}', [RecordController::class, 'update'])->name('records.update');
    Route::delete('/{record}', [RecordController::class, 'destroy'])->name('records.destroy');
});

Route::prefix('collections/{collection}/auth')->name('collections.auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('authenticate');
    Route::delete('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::delete('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
});

Route::post('/onboarding/initialized', [OnboardingController::class, 'initialized'])->name('onboarding.initialized.check');
Route::post('/onboarding/superuser', [OnboardingController::class, 'createSuperuser'])->name('onboarding.superuser.create');

Route::middleware('auth:api')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/collections/{collection}/subscribe', [SubscribeController::class, 'subscribe']);
    Route::delete('/collections/{collection}/subscribe', [SubscribeController::class, 'unsubscribe']);
});

Route::middleware(['auth:api', SuperuserOnly::class])->group(function () {
    Route::get('/logs/dates', [LogViewerController::class, 'getDates'])->name('logs.dates');
    Route::get('/logs', [LogViewerController::class, 'index'])->name('logs.index');
});
