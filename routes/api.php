<?php

use App\Domain\Auth\Controllers\AuthController;
use App\Domain\Collections\Controllers\CollectionController;
use App\Domain\Realtime\Controllers\SubscribeController;
use App\Domain\Records\Controllers\RecordController;
use App\Domain\SchemaManagement\Controllers\OrphanTableController;
use App\Domain\SchemaManagement\Controllers\SchemaRecoveryController;
use App\Http\Controllers\EmailTemplateController;
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

    Route::post('/password-reset/request', [AuthController::class, 'requestPasswordReset'])->name('password-reset.request');
    Route::post('/password-reset/confirm', [AuthController::class, 'confirmPasswordReset'])->name('password-reset.confirm');

    Route::middleware('auth:api')->group(function () {
        Route::delete('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::delete('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::post('/email-verification/request', [AuthController::class, 'requestEmailVerification'])->name('email-verification.request');
        Route::post('/email-verification/confirm', [AuthController::class, 'confirmEmailVerification'])->name('email-verification.confirm');
    });
});

Route::post('/onboarding/initialized', [OnboardingController::class, 'initialized'])->name('onboarding.initialized.check');
Route::post('/onboarding/superuser', [OnboardingController::class, 'createSuperuser'])->name('onboarding.superuser.create');

Route::middleware('auth:api')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/collections/{collection}/subscribe', [SubscribeController::class, 'subscribe']);
    Route::delete('/collections/{collection}/subscribe', [SubscribeController::class, 'unsubscribe']);
});

Route::middleware(['auth:api', SuperuserOnly::class])->group(function () {
    Route::get('/schema/corrupt', [SchemaRecoveryController::class, 'index'])->name('schema.corrupt.index');
    Route::post('/collections/{collection}/recover', [SchemaRecoveryController::class, 'recover'])->name('collections.recover');
    Route::get('/schema/orphans', [OrphanTableController::class, 'index'])->name('schema.orphans.index');
    Route::delete('/schema/orphans', [OrphanTableController::class, 'destroyAll'])->name('schema.orphans.destroy-all');
    Route::delete('/schema/orphans/{table_name}', [OrphanTableController::class, 'destroy'])->name('schema.orphans.destroy');

    Route::get('/logs/dates', [LogViewerController::class, 'getDates'])->name('logs.dates');
    Route::get('/logs', [LogViewerController::class, 'index'])->name('logs.index');

    Route::get('/collections/{collection}/email-templates/{action}', [EmailTemplateController::class, 'show'])->name('email-templates.show');
    Route::put('/collections/{collection}/email-templates/{action}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
});
