<?php

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatoBancarioController;
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
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Rutas protegidas (requieren token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'actualizarPerfil']);

    // Solo admin
    Route::middleware('role:admin')
        ->prefix('admin')
        ->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);
            // Los `resumen` van antes de sus `apiResource`: si no, Laravel
            // casa `estudiantes/resumen` con `estudiantes/{id}` y busca el id
            // "resumen".
            Route::get('estudiantes/resumen', [
                EstudianteController::class,
                'resumen',
            ]);
            Route::patch('estudiantes/estado-masivo', [
                EstudianteController::class,
                'estadoMasivo',
            ]);
            Route::apiResource('estudiantes', EstudianteController::class);
            Route::patch('estudiantes/{id}/estado-pago', [
                EstudianteController::class,
                'updateEstadoPago',
            ]);
            Route::get('notificaciones', [
                NotificationController::class,
                'index',
            ]);
            Route::get('notificaciones/count', [
                NotificationController::class,
                'unreadCount',
            ]);
            Route::post('notificaciones/{id}/read', [
                NotificationController::class,
                'markAsRead',
            ]);
            Route::post('notificaciones/mark-all-read', [
                NotificationController::class,
                'markAllAsRead',
            ]);
            Route::post('notificaciones/send', [
                NotificationController::class,
                'send',
            ]);

            Route::get('profesores/resumen', [
                ProfesorController::class,
                'resumen',
            ]);
            Route::apiResource('profesores', ProfesorController::class);
            Route::get('tipo-contratos', [
                ProfesorController::class,
                'getTipoContratos',
            ]);
            Route::get('especialidades', [
                ProfesorController::class,
                'getEspecialidades',
            ]);
            Route::get('departamentos', [
                ProfesorController::class,
                'getDepartamentos',
            ]);
            Route::get('titulos', [ProfesorController::class, 'getTitulos']);

            // Cursos (antes de las rutas genéricas {slug} para evitar conflicto)
            Route::get('cursos/resumen', [CursoController::class, 'resumen']);
            Route::apiResource('cursos', CursoController::class);
            Route::post('cursos/{id}/estudiantes', [
                CursoController::class,
                'inscribirEstudiante',
            ]);
            Route::delete('cursos/{id}/estudiantes/{estudianteId}', [
                CursoController::class,
                'quitarEstudiante',
            ]);

            // Temario y sesiones de cursos
            Route::get('cursos/{cursoId}/temario', [
                TemarioController::class,
                'index',
            ]);
            Route::post('cursos/{cursoId}/temario', [
                TemarioController::class,
                'store',
            ]);
            Route::put('cursos/{cursoId}/temario/{id}', [
                TemarioController::class,
                'update',
            ]);
            Route::delete('cursos/{cursoId}/temario/{id}', [
                TemarioController::class,
                'destroy',
            ]);

            Route::get('cursos/{cursoId}/sesiones', [
                SesionController::class,
                'index',
            ]);
            Route::post('cursos/{cursoId}/sesiones', [
                SesionController::class,
                'store',
            ]);
            Route::put('cursos/{cursoId}/sesiones/{id}', [
                SesionController::class,
                'update',
            ]);
            Route::delete('cursos/{cursoId}/sesiones/{id}', [
                SesionController::class,
                'destroy',
            ]);

            // Horario / Calendario (sesiones globales)
            Route::get('horario', [SesionController::class, 'horario']);
            Route::post('sesiones', [SesionController::class, 'storeGlobal']);
            Route::put('sesiones/{id}', [
                SesionController::class,
                'updateGlobal',
            ]);
            Route::delete('sesiones/{id}', [
                SesionController::class,
                'destroyGlobal',
            ]);

            // Asistencia por sesión
            Route::get('sesiones/{sesionId}/asistencia', [
                AsistenciaController::class,
                'show',
            ]);
            Route::post('sesiones/{sesionId}/asistencia', [
                AsistenciaController::class,
                'store',
            ]);

            // Pagos Admin
            Route::get('pagos', [PagoController::class, 'index']);
            Route::get('pagos/resumen', [PagoController::class, 'resumen']);
            Route::patch('pagos/masivo', [PagoController::class, 'updateMasivo']);
            Route::put('pagos/{id}', [PagoController::class, 'update']);
            Route::delete('pagos/{id}', [PagoController::class, 'destroy']);

            // Datos Bancarios (admin)
            Route::get('datos-bancarios', [
                DatoBancarioController::class,
                'index',
            ]);
            Route::post('datos-bancarios', [
                DatoBancarioController::class,
                'store',
            ]);
            // Certificados
            Route::get('estudiantes/{id}/certificado', [
                CertificadoController::class,
                'download',
            ]);

            // Reportes
            Route::get('reportes', [ReporteController::class, 'index']);

            // Catálogos CRUD (DEBE ser la última ruta del grupo admin, después de todas las rutas explícitas)
            Route::get('{slug}', [CatalogoController::class, 'index']);
            Route::post('{slug}', [CatalogoController::class, 'store']);
            Route::put('{slug}/{id}', [CatalogoController::class, 'update']);
            Route::delete('{slug}/{id}', [
                CatalogoController::class,
                'destroy',
            ]);
        });

    // Profesor y admin
    Route::middleware('role:admin,profesor')->group(function () {
        Route::get('cursos', [CursoController::class, 'index']);
        Route::get('cursos/{id}', [CursoController::class, 'show']);
        Route::get('estudiantes', [EstudianteController::class, 'index']);
        Route::get('estudiantes/{id}', [EstudianteController::class, 'show']);

        // Asistencia accesible por admin y profesor (con autorización interna por curso)
        Route::get('sesiones/{sesionId}/asistencia', [
            AsistenciaController::class,
            'show',
        ]);
        Route::post('sesiones/{sesionId}/asistencia', [
            AsistenciaController::class,
            'store',
        ]);
    });

    // Solo profesor
    Route::middleware('role:profesor')
        ->prefix('profesor')
        ->group(function () {
            Route::get('cursos', [CursoController::class, 'misCursos']);
            Route::put('perfil/{id}', [ProfesorController::class, 'update']);
            Route::post('perfil/foto', [
                ProfesorController::class,
                'uploadFotoMe',
            ]);

            // Cronograma / Horario del instructor (solo sus sesiones)
            Route::get('horario', [SesionController::class, 'horarioProfesor']);
            Route::patch('estudiantes/{id}/aprobacion-curso', [
                EstudianteController::class,
                'updateAprobacionCurso',
            ]);
            Route::get('notificaciones', [
                NotificationController::class,
                'index',
            ]);
            Route::get('notificaciones/count', [
                NotificationController::class,
                'unreadCount',
            ]);
            Route::post('notificaciones/{id}/read', [
                NotificationController::class,
                'markAsRead',
            ]);
            Route::post('notificaciones/mark-all-read', [
                NotificationController::class,
                'markAllAsRead',
            ]);
        });

    // Datos bancarios (público para estudiantes logueados)
    Route::get('datos-bancarios', [DatoBancarioController::class, 'all']);
    Route::get('datos-bancarios/{tipo}', [
        DatoBancarioController::class,
        'show',
    ]);

    // Solo estudiante
    Route::middleware('role:estudiante')
        ->prefix('estudiante')
        ->group(function () {
            Route::get('cursos', [CursoController::class, 'indexActivos']);
            Route::get('cursos/{id}', [CursoController::class, 'show']);
            Route::get('curso', [EstudianteController::class, 'miCurso']);
            Route::get('mis-cursos', [EstudianteController::class, 'misCursos']);
            Route::get('certificado', [
                CertificadoController::class,
                'downloadMe',
            ]);
            Route::get('perfil', [EstudianteController::class, 'showMe']);
            Route::put('perfil', [EstudianteController::class, 'updateMe']);
            Route::post('perfil/foto', [
                EstudianteController::class,
                'uploadFotoMe',
            ]);
            Route::get('notificaciones', [
                NotificationController::class,
                'index',
            ]);
            Route::get('notificaciones/count', [
                NotificationController::class,
                'unreadCount',
            ]);
            Route::post('notificaciones/{id}/read', [
                NotificationController::class,
                'markAsRead',
            ]);
            Route::post('notificaciones/mark-all-read', [
                NotificationController::class,
                'markAllAsRead',
            ]);

            // Pagos
            Route::get('pagos', [PagoController::class, 'studentIndex']);
            Route::post('pagos', [PagoController::class, 'store']);
        });
});
