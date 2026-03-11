<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Solo admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Aquí van las rutas del admin
    });

    // Solo profesor
    Route::middleware('role:profesor')->prefix('profesor')->group(function () {
        // Aquí van las rutas del profesor
    });

    // Solo estudiante
    Route::middleware('role:estudiante')->prefix('estudiante')->group(function () {
        // Aquí van las rutas del estudiante
    });

    // Profesor y admin
    Route::middleware('role:admin,profesor')->group(function () {
        // Aquí van rutas compartidas entre admin y profesor
    });
});
