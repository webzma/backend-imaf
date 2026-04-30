<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\Profesor;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'estudiantes' => [
                'total' => Estudiante::count(),
                'activos' => Estudiante::where('estado', 'activo')->count(),
                'inactivos' => Estudiante::where('estado', 'inactivo')->count(),
                'graduados' => Estudiante::where('estado', 'graduado')->count(),
            ],
            'pagos' => [
                'total' => Pago::count(),
                'pendientes' => Pago::where('estado', 'pendiente')->count(),
                'aprobados' => Pago::where('estado', 'aprobado')->count(),
                'rechazados' => Pago::where('estado', 'rechazado')->count(),
            ],
            'cursos' => [
                'total' => Curso::count(),
                'activos' => Curso::where('estado', 'activo')->count(),
                'con_cupo' => Curso::where('estado', 'activo')
                    ->withCount('estudiantes')
                    ->get()
                    ->filter(fn ($c) => $c->cupos_restantes > 0)
                    ->count(),
            ],
            'profesores' => [
                'total' => Profesor::count(),
            ],
            'ingresos_por_curso' => Curso::withCount([
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
                ]),
        ]);
    }
}
