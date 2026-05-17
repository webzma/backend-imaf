<?php

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\ProfesorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\TemarioController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Solo admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::apiResource('estudiantes', EstudianteController::class);
        Route::patch('estudiantes/{id}/estado-pago', [EstudianteController::class, 'updateEstadoPago']);
        Route::get('notificaciones', [NotificationController::class, 'index']);
        Route::get('notificaciones/count', [NotificationController::class, 'unreadCount']);
        Route::post('notificaciones/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notificaciones/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('notificaciones/send', [NotificationController::class, 'send']);

        Route::apiResource('profesores', ProfesorController::class);
        Route::get('tipo-contratos', [ProfesorController::class, 'getTipoContratos']);
        Route::apiResource('cursos', CursoController::class);

        // Temario y sesiones de cursos
        Route::get('cursos/{cursoId}/temario', [TemarioController::class, 'index']);
        Route::post('cursos/{cursoId}/temario', [TemarioController::class, 'store']);
        Route::put('cursos/{cursoId}/temario/{id}', [TemarioController::class, 'update']);
        Route::delete('cursos/{cursoId}/temario/{id}', [TemarioController::class, 'destroy']);

        Route::get('cursos/{cursoId}/sesiones', [SesionController::class, 'index']);
        Route::post('cursos/{cursoId}/sesiones', [SesionController::class, 'store']);
        Route::put('cursos/{cursoId}/sesiones/{id}', [SesionController::class, 'update']);
        Route::delete('cursos/{cursoId}/sesiones/{id}', [SesionController::class, 'destroy']);

        // Horario / Calendario (sesiones globales)
        Route::get('horario', [SesionController::class, 'horario']);
        Route::post('sesiones', [SesionController::class, 'storeGlobal']);
        Route::put('sesiones/{id}', [SesionController::class, 'updateGlobal']);
        Route::delete('sesiones/{id}', [SesionController::class, 'destroyGlobal']);

        // Asistencia por sesión
        Route::get('sesiones/{sesionId}/asistencia', [AsistenciaController::class, 'show']);
        Route::post('sesiones/{sesionId}/asistencia', [AsistenciaController::class, 'store']);

        // Pagos Admin
        Route::get('pagos', [PagoController::class, 'index']);
        Route::put('pagos/{id}', [PagoController::class, 'update']);
        Route::delete('pagos/{id}', [PagoController::class, 'destroy']);

        // Certificados
        Route::get('estudiantes/{id}/certificado', [CertificadoController::class, 'download']);

        // Reportes
        Route::get('reportes', [ReporteController::class, 'index']);
    });

    // Profesor y admin
    Route::middleware('role:admin,profesor')->group(function () {
        Route::get('cursos', [CursoController::class, 'index']);
        Route::get('cursos/{id}', [CursoController::class, 'show']);
        Route::get('estudiantes', [EstudianteController::class, 'index']);
        Route::get('estudiantes/{id}', [EstudianteController::class, 'show']);

        // Asistencia accesible por admin y profesor (con autorización interna por curso)
        Route::get('sesiones/{sesionId}/asistencia', [AsistenciaController::class, 'show']);
        Route::post('sesiones/{sesionId}/asistencia', [AsistenciaController::class, 'store']);
    });

    // Solo profesor
    Route::middleware('role:profesor')->prefix('profesor')->group(function () {
        Route::put('perfil/{id}', [ProfesorController::class, 'update']);
        Route::patch('estudiantes/{id}/aprobacion-curso', [EstudianteController::class, 'updateAprobacionCurso']);
    });

    // Solo estudiante
    Route::middleware('role:estudiante')->prefix('estudiante')->group(function () {
        Route::get('cursos', [CursoController::class, 'indexActivos']);
        Route::get('cursos/{id}', [CursoController::class, 'show']);
        Route::get('curso', [EstudianteController::class, 'miCurso']);
        Route::get('certificado', [CertificadoController::class, 'downloadMe']);
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
