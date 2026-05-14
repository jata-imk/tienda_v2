<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyInfoController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['jwt.authenticate', 'es.administrador'])->group(function () {
    Route::delete('/logout', [AuthController::class, 'logout']);

    // Users
    Route::apiResource('users', UserController::class);

    // Inventory
    Route::post('categories/query', [CategoryController::class, 'query']);
    Route::apiResource('categories', CategoryController::class);
    Route::post('products/query', [ProductController::class, 'query']);
    Route::apiResource('products', ProductController::class);

    // Configuration
    Route::put('company-info', [CompanyInfoController::class, 'update']);
    Route::patch('company-info', [CompanyInfoController::class, 'patch']);
    Route::apiResource('currencies', CurrencyController::class);
});
