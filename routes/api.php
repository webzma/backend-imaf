<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\ProfesorController;
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
        Route::apiResource('estudiantes', EstudianteController::class);
        Route::patch('estudiantes/{id}/estado-pago', [EstudianteController::class, 'updateEstadoPago']);
        Route::get('notificaciones', [NotificationController::class, 'index']);
        Route::get('notificaciones/count', [NotificationController::class, 'unreadCount']);
        Route::post('notificaciones/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notificaciones/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('notificaciones/send', [NotificationController::class, 'send']);
        
        // Pagos Admin
        Route::get('pagos', [PagoController::class, 'index']);
        Route::put('pagos/{id}', [PagoController::class, 'update']);

        Route::apiResource('profesores', ProfesorController::class);
        Route::apiResource('cursos', CursoController::class);
    });

    // Profesor y admin
    Route::middleware('role:admin,profesor')->group(function () {
        Route::get('cursos', [CursoController::class, 'index']);
        Route::get('cursos/{id}', [CursoController::class, 'show']);
        Route::get('estudiantes', [EstudianteController::class, 'index']);
        Route::get('estudiantes/{id}', [EstudianteController::class, 'show']);
    });

    // Solo profesor
    Route::middleware('role:profesor')->prefix('profesor')->group(function () {
        Route::put('perfil/{id}', [ProfesorController::class, 'update']);
        Route::patch('estudiantes/{id}/aprobacion-curso', [EstudianteController::class, 'updateAprobacionCurso']);
    });

    // Solo estudiante
    Route::middleware('role:estudiante')->prefix('estudiante')->group(function () {
        Route::get('cursos', [CursoController::class, 'index']);
        Route::get('cursos/{id}', [CursoController::class, 'show']);
        Route::post('cursos/{cursoId}/solicitar-pago', [EstudianteController::class, 'solicitarPagoCurso']);
        Route::get('perfil', [EstudianteController::class, 'showMe']);
        Route::put('perfil', [EstudianteController::class, 'updateMe']);
        Route::get('notificaciones', [NotificationController::class, 'index']);
        Route::get('notificaciones/count', [NotificationController::class, 'unreadCount']);
        Route::post('notificaciones/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notificaciones/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        
        // Pagos
        Route::get('pagos', [PagoController::class, 'studentIndex']);
        Route::post('pagos', [PagoController::class, 'store']);
    });
});
