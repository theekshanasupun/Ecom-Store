<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->group(function () {
    Route::get('/', [InstallController::class, 'requirements']);
    Route::get('database', [InstallController::class, 'database']);
    Route::post('database', [InstallController::class, 'databasePost']);
    Route::get('site', [InstallController::class, 'site']);
    Route::post('site', [InstallController::class, 'sitePost']);
    Route::get('review', [InstallController::class, 'review']);
    Route::post('run', [InstallController::class, 'run']);
    Route::get('done', [InstallController::class, 'done']);
});
