<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:api', 'es.administrador'])->group(function () {
    Route::apiResource('usuarios', UsuarioController::class);
});
