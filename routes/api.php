<?php

use App\Domain\Auth\Controllers\AuthController;
use App\Domain\Collections\Controllers\CollectionController;
use App\Domain\Realtime\Controllers\SubscribeController;
use App\Domain\Records\Controllers\RecordController;
use App\Domain\SchemaManagement\Controllers\OrphanTableController;
use App\Domain\SchemaManagement\Controllers\SchemaRecoveryController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\OAuthProviderController;
use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\SuperuserOnly;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Collection Management
|--------------------------------------------------------------------------
|
| Public endpoints to manage collection resources. Namespaced route names
| use the `collections.*` convention for easier referencing.
|
*/
Route::prefix('collections')->group(function () {
    Route::get('/', [CollectionController::class, 'index'])->name('collections.index');
    Route::post('/', [CollectionController::class, 'store'])->name('collections.store');
    Route::get('/{collection}', [CollectionController::class, 'show'])->name('collections.show');
    Route::match(['PUT', 'PATCH'], '/{collection}', [CollectionController::class, 'update'])->name('collections.update');
    Route::delete('/{collection}/truncate', [CollectionController::class, 'truncate'])->name('collections.truncate');
    Route::delete('/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');
});

/*
|--------------------------------------------------------------------------
| Record Management (Per-Collection)
|--------------------------------------------------------------------------
|
| CRUD endpoints for records scoped to a collection. Controllers should
| perform authorization and validation; route model binding is used for
| `collection` and `record` parameters.
|
*/
Route::prefix('collections/{collection}/records')->group(function () {
    Route::get('/', [RecordController::class, 'index'])->name('records.index');
    Route::post('/', [RecordController::class, 'store'])->name('records.store');
    Route::get('/{record}', [RecordController::class, 'show'])->name('records.show');
    Route::match(['PUT', 'PATCH'], '/{record}', [RecordController::class, 'update'])->name('records.update');
    Route::delete('/{record}', [RecordController::class, 'destroy'])->name('records.destroy');
});

/*
|--------------------------------------------------------------------------
| Per-Collection Authentication
|--------------------------------------------------------------------------
|
| Authentication endpoints that operate within the scope of a collection.
| Some routes are throttled (OTP flows) and others are protected by the
| `auth:api` middleware to require a valid API token.
|
*/
Route::prefix('collections/{collection}/auth')->name('collections.auth.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('authenticate');

    Route::post('/password-reset/request', [AuthController::class, 'requestPasswordReset'])->middleware('throttle:otp')->name('password-reset.request');
    Route::post('/password-reset/confirm', [AuthController::class, 'confirmPasswordReset'])->name('password-reset.confirm');

    Route::middleware('auth:api')->group(function () {
        Route::delete('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::delete('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::post('/email-verification/request', [AuthController::class, 'requestEmailVerification'])->middleware('throttle:otp')->name('email-verification.request');
        Route::post('/email-verification/confirm', [AuthController::class, 'confirmEmailVerification'])->name('email-verification.confirm');

        Route::post('/email-change/request', [AuthController::class, 'requestEmailChange'])->middleware('throttle:otp')->name('email-change.request');
        Route::post('/email-change/confirm', [AuthController::class, 'confirmEmailChange'])->name('email-change.confirm');
    });
});

/*
|--------------------------------------------------------------------------
| OAuth2 Integration
|--------------------------------------------------------------------------
|
| Endpoints used by third-party OAuth providers: redirect helper, callback
| handler and token exchange. Keep provider-specific logic inside the
| corresponding controller.
|
*/
Route::prefix('oauth2')->group(function () {
    Route::post('/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
    Route::post('/exchange', [OAuthController::class, 'exchange'])->name('oauth.exchange');
});

/*
|--------------------------------------------------------------------------
| Onboarding
|--------------------------------------------------------------------------
|
| Routes used during initial setup for the application.
| These endpoints are used to check if the application is initialized and
| to create a superuser account. Creating superuser account is only allowed
| during the initial setup process, once.
|
*/
Route::post('/onboarding/initialized', [OnboardingController::class, 'initialized'])->name('onboarding.initialized.check');
Route::post('/onboarding/superuser', [OnboardingController::class, 'createSuperuser'])->name('onboarding.superuser.create');

/*
|--------------------------------------------------------------------------
| Authenticated Endpoints
|--------------------------------------------------------------------------
|
| Routes that require a valid API token. Keep them grouped together and
| ensure controllers perform fine-grained authorization.
|
*/
Route::middleware('auth:api')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/collections/{collection}/subscribe', [SubscribeController::class, 'subscribe']);
    Route::delete('/collections/{collection}/subscribe', [SubscribeController::class, 'unsubscribe']);
});

/*
|--------------------------------------------------------------------------
| Superuser / Admin Routes
|--------------------------------------------------------------------------
|
| These routes are restricted to superusers and expose administrative
| functionality such as schema recovery, orphan detection, log viewing,
| email template management, and OAuth provider configuration.
|
*/
Route::middleware(['auth:api', SuperuserOnly::class])->group(function () {
    Route::get('/schema/corrupt', [SchemaRecoveryController::class, 'index'])->name('schema.corrupt.index');
    Route::post('/collections/{collection}/recover', [SchemaRecoveryController::class, 'recover'])->name('collections.recover');
    Route::get('/schema/orphans', [OrphanTableController::class, 'index'])->name('schema.orphans.index');
    Route::delete('/schema/orphans', [OrphanTableController::class, 'destroyAll'])->name('schema.orphans.destroy-all');
    Route::delete('/schema/orphans/{table_name}', [OrphanTableController::class, 'destroy'])->name('schema.orphans.destroy');

    Route::post('/collections/{collection}/auth/impersonate/{recordId}', [AuthController::class, 'impersonate'])->name('collections.auth.impersonate');

    Route::get('/logs/dates', [LogViewerController::class, 'getDates'])->name('logs.dates');
    Route::get('/logs', [LogViewerController::class, 'index'])->name('logs.index');

    Route::get('/collections/{collection}/email-templates/{action}', [EmailTemplateController::class, 'show'])->name('email-templates.show');
    Route::put('/collections/{collection}/email-templates/{action}', [EmailTemplateController::class, 'update'])->name('email-templates.update');

    Route::get('/collections/{collection}/oauth-providers', [OAuthProviderController::class, 'index'])->name('oauth-providers.index');
    Route::post('/collections/{collection}/oauth-providers', [OAuthProviderController::class, 'store'])->name('oauth-providers.store');
    Route::match(['PUT', 'PATCH'], '/collections/{collection}/oauth-providers/{oauthProvider}', [OAuthProviderController::class, 'update'])->name('oauth-providers.update');
    Route::delete('/collections/{collection}/oauth-providers/{oauthProvider}', [OAuthProviderController::class, 'destroy'])->name('oauth-providers.destroy');
});
