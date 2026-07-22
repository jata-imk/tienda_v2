<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\CompanyInfoController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\SizeGroupController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['jwt.authenticate', 'es.administrador'])->group(function () {
    Route::delete('/logout', [AuthController::class, 'logout']);

    // Catalogs
    Route::get('catalogs', [CatalogController::class, 'index']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Users
    Route::apiResource('users', UserController::class);

    // Inventory
    Route::post('categories/query', [CategoryController::class, 'query']);
    Route::apiResource('categories', CategoryController::class);

    Route::post('size-groups/query', [SizeGroupController::class, 'query']);
    Route::apiResource('size-groups', SizeGroupController::class);

    Route::post('sizes/query', [SizeController::class, 'query']);
    Route::apiResource('sizes', SizeController::class);

    Route::post('colors/query', [ColorController::class, 'query']);
    Route::apiResource('colors', ColorController::class);

    Route::post('products/query', [ProductController::class, 'query']);
    Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
    Route::post('products/{product}/image', [ProductController::class, 'uploadImage']);
    Route::delete('products/{product}/image', [ProductController::class, 'deleteImage']);
    Route::apiResource('products', ProductController::class);

    Route::post('inventory/movements', [InventoryMovementController::class, 'store']);

    // Configuration
    Route::get('company-info', [CompanyInfoController::class, 'show']);
    Route::post('company-info', [CompanyInfoController::class, 'store']);
    Route::put('company-info', [CompanyInfoController::class, 'update']);
    Route::patch('company-info', [CompanyInfoController::class, 'patch']);
    Route::apiResource('currencies', CurrencyController::class);
});
