<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:api', 'es.administrador'])->group(function () {
    Route::delete('/logout', [AuthController::class, 'logout']);

    // Users
    Route::apiResource('users', UserController::class);

    // Inventory
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);

    // Configuration
    Route::apiResource('currencies', CurrencyController::class);
});
