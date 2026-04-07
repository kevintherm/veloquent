<?php

use App\Http\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;

if (config('velo.docs.enabled')) {
    $path = config('velo.docs.path', 'docs');

    Route::prefix($path)->group(function () use ($path) {
        Route::redirect('/', $path.'/getting-started/introduction', 302)->name('docs.home');
        Route::get('/search', [DocumentationController::class, 'search'])->name('docs.search');
        Route::get('/{file?}', [DocumentationController::class, 'show'])->name('docs.show')->where('file', '.*');
    });
}
