<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\Profesor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $contexto = [
            'user_id' => $request->user()?->id,
            'metodo' => $request->method(),
            'ruta' => $request->path(),
            'query' => $request->query(),
            'ip' => $request->ip(),
        ];

        Log::info('DASHBOARD_REQUEST', $contexto);

        $paso = 'inicio';

        try {
            $paso = 'estudiantes';
            Log::info('DASHBOARD_DB_INICIO', ['paso' => $paso]);
            $inicio = microtime(true);
            $estudiantes = [
                'total' => Estudiante::count(),
                'activos' => Estudiante::where('estado', 'activo')->count(),
                'inactivos' => Estudiante::where('estado', 'inactivo')->count(),
                'graduados' => Estudiante::where('estado', 'graduado')->count(),
            ];
            Log::info('DASHBOARD_DB_FIN', [
                'paso' => $paso,
                'duracion_ms' => $this->milisegundos($inicio),
                'resultado' => $estudiantes,
            ]);

            $paso = 'pagos';
            Log::info('DASHBOARD_DB_INICIO', ['paso' => $paso]);
            $inicio = microtime(true);
            $pagos = [
                'total' => Pago::count(),
                'pendientes' => Pago::where('estado', 'pendiente')->count(),
                'aprobados' => Pago::where('estado', 'aprobado')->count(),
                'rechazados' => Pago::where('estado', 'rechazado')->count(),
            ];
            Log::info('DASHBOARD_DB_FIN', [
                'paso' => $paso,
                'duracion_ms' => $this->milisegundos($inicio),
                'resultado' => $pagos,
            ]);

            $paso = 'cursos';
            Log::info('DASHBOARD_DB_INICIO', ['paso' => $paso]);
            $inicio = microtime(true);
            $cursosTotal = Curso::count();
            $cursosActivos = Curso::where('estado', 'activo')->count();
            $cursosConCupo = Curso::where('estado', 'activo')
                ->withCount('estudiantes')
                ->get()
                ->filter(fn ($c) => $c->cupos_restantes > 0)
                ->count();
            Log::info('DASHBOARD_DB_FIN', [
                'paso' => $paso,
                'duracion_ms' => $this->milisegundos($inicio),
                'total' => $cursosTotal,
                'activos' => $cursosActivos,
                'con_cupo' => $cursosConCupo,
            ]);

            $paso = 'profesores';
            Log::info('DASHBOARD_DB_INICIO', ['paso' => $paso]);
            $inicio = microtime(true);
            $profesoresTotal = Profesor::count();
            Log::info('DASHBOARD_DB_FIN', [
                'paso' => $paso,
                'duracion_ms' => $this->milisegundos($inicio),
                'total' => $profesoresTotal,
            ]);

            $paso = 'ingresos_por_curso';
            Log::info('DASHBOARD_DB_INICIO', ['paso' => $paso]);
            $inicio = microtime(true);
            $ingresosPorCurso = Curso::withCount([
                'estudiantes',
                'estudiantes as pagos_aprobados_count' => fn ($q) => $q->whereHas('user.pagos', fn ($p) => $p->where('estado', 'aprobado')),
            ])
                ->where('estado', 'activo')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'precio' => $c->precio,
                    'estudiantes' => $c->estudiantes_count,
                    'cupos_restantes' => $c->cupos_restantes,
                ]);
            Log::info('DASHBOARD_DB_FIN', [
                'paso' => $paso,
                'duracion_ms' => $this->milisegundos($inicio),
                'cursos' => $ingresosPorCurso->count(),
            ]);

            return response()->json([
                'estudiantes' => $estudiantes,
                'pagos' => $pagos,
                'cursos' => [
                    'total' => $cursosTotal,
                    'activos' => $cursosActivos,
                    'con_cupo' => $cursosConCupo,
                ],
                'profesores' => [
                    'total' => $profesoresTotal,
                ],
                'ingresos_por_curso' => $ingresosPorCurso,
            ]);
        } catch (\Throwable $e) {
            Log::error('DASHBOARD_500', [
                'paso' => $paso,
                'contexto' => $contexto,
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'No se pudo cargar el dashboard.',
            ], 500);
        }
    }

    private function milisegundos(float $inicio): float
    {
        return round((microtime(true) - $inicio) * 1000, 2);
    }
}
