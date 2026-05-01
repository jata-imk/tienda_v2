<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ImpuestosConfigController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\TipoIvaController;
use App\Http\Controllers\TipoMonedaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:api', 'es.administrador'])->group(function () {
    // Usuarios
    Route::apiResource('usuarios', UsuarioController::class);

    // Configuración
    Route::get('tipos-iva', [TipoIvaController::class, 'index']);
    Route::get('impuestos-config', [ImpuestosConfigController::class, 'show']);
    Route::put('impuestos-config', [ImpuestosConfigController::class, 'update']);
    Route::apiResource('tipos-moneda', TipoMonedaController::class);

    // Inventario
    Route::apiResource('categorias', CategoriaController::class);
    Route::apiResource('inventario', InventarioController::class);
});
