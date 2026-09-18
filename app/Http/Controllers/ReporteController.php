<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\Profesor;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $periodo = $request->get('periodo', 'mensual');

        $ingresos = $this->ingresosPorPeriodo($periodo);
        $pagosUsuario = $this->pagosPorUsuario();
        $pagosCurso = $this->pagosPorCurso();
        $resumen = $this->resumen();

        return response()->json([
            'ingresos' => $ingresos,
            'pagos_por_usuario' => $pagosUsuario,
            'pagos_por_curso' => $pagosCurso,
            'resumen' => $resumen,
            // Estos cuatro se calculaban en el navegador sobre la primera
            // página de cada lista: con más de diez registros, la pantalla de
            // reportes publicaba números que no eran ciertos.
            'totales' => $this->totales(),
            'cursos' => $this->cursos(),
            'estado_estudiantes' => $this->estadoEstudiantes(),
            'estado_cursos' => $this->estadoCursos(),
        ]);
    }

    /** @return array<string, int> */
    private function totales(): array
    {
        return [
            'estudiantes' => Estudiante::count(),
            'cursos' => Curso::count(),
            'instructores' => Profesor::count(),
        ];
    }

    /** Ocupación real de cada curso, ordenada de mayor a menor. */
    private function cursos(): array
    {
        return Curso::withCount('estudiantes')
            ->orderByDesc('estudiantes_count')
            ->get()
            ->map(fn ($curso) => [
                'id' => $curso->id,
                'nombre' => $curso->nombre,
                'codigo' => $curso->codigo,
                'estado' => $curso->estado,
                'limite_cupo' => (int) $curso->limite_cupo,
                'estudiantes' => (int) $curso->estudiantes_count,
            ])
            ->toArray();
    }

    /** @return array<string, int> */
    private function estadoEstudiantes(): array
    {
        return Estudiante::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->map(fn ($valor) => (int) $valor)
            ->toArray();
    }

    /** @return array<string, int> */
    private function estadoCursos(): array
    {
        return Curso::selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->map(fn ($valor) => (int) $valor)
            ->toArray();
    }

    private function ingresosPorPeriodo(string $periodo): array
    {
        $desde = match ($periodo) {
            'semanal' => now()->subWeeks(12),
            'anual' => now()->subYears(5),
            default => now()->subMonths(12),
        };

        $etiqueta = $this->expresionPeriodo($periodo);

        $query = Pago::query()
            ->join('cursos', 'pagos.curso_id', '=', 'cursos.id')
            ->where('pagos.estado', 'aprobado')
            ->whereNull('pagos.deleted_at')
            ->where('pagos.created_at', '>=', $desde)
            ->selectRaw("{$etiqueta} as label, SUM(cursos.precio) as total, COUNT(pagos.id) as cantidad")
            ->groupByRaw($etiqueta)
            ->orderByRaw($etiqueta);

        return $query->get()->map(fn ($row) => [
            'label' => (string) $row->label,
            'total' => (float) $row->total,
            'cantidad' => (int) $row->cantidad,
        ])->toArray();
    }

    /**
     * Expresión que agrupa por periodo, según el motor de base de datos.
     *
     * `DATE_FORMAT` solo existe en MySQL, así que esta pantalla devolvía un
     * error 500 en cualquier otro motor — incluido el SQLite en memoria de los
     * tests, que es la razón por la que el reporte no tenía ninguno.
     */
    private function expresionPeriodo(string $periodo): string
    {
        $driver = Pago::query()->getConnection()->getDriverName();
        $columna = 'pagos.created_at';

        return match ($driver) {
            'sqlite' => match ($periodo) {
                'semanal' => "strftime('%Y-W%W', {$columna})",
                'anual' => "strftime('%Y', {$columna})",
                default => "strftime('%Y-%m', {$columna})",
            },
            'pgsql' => match ($periodo) {
                'semanal' => "to_char({$columna}, 'IYYY\"-W\"IW')",
                'anual' => "to_char({$columna}, 'YYYY')",
                default => "to_char({$columna}, 'YYYY-MM')",
            },
            default => match ($periodo) {
                'semanal' => "DATE_FORMAT({$columna}, '%x-W%v')",
                'anual' => "YEAR({$columna})",
                default => "DATE_FORMAT({$columna}, '%Y-%m')",
            },
        };
    }

    private function pagosPorUsuario(): array
    {
        return Pago::query()
            ->join('users', 'pagos.user_id', '=', 'users.id')
            ->join('cursos', 'pagos.curso_id', '=', 'cursos.id')
            ->whereNull('pagos.deleted_at')
            ->selectRaw('
                users.id as user_id,
                users.name as nombre,
                COUNT(pagos.id) as total_pagos,
                SUM(CASE WHEN pagos.estado = "aprobado" THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN pagos.estado = "pendiente" THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN pagos.estado = "rechazado" THEN 1 ELSE 0 END) as rechazados,
                SUM(CASE WHEN pagos.estado = "aprobado" THEN cursos.precio ELSE 0 END) as total_ingreso
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_pagos')
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id,
                'nombre' => $row->nombre,
                'total_pagos' => (int) $row->total_pagos,
                'aprobados' => (int) $row->aprobados,
                'pendientes' => (int) $row->pendientes,
                'rechazados' => (int) $row->rechazados,
                'total_ingreso' => (float) $row->total_ingreso,
            ])
            ->toArray();
    }

    private function pagosPorCurso(): array
    {
        return Pago::query()
            ->join('cursos', 'pagos.curso_id', '=', 'cursos.id')
            ->whereNull('pagos.deleted_at')
            ->selectRaw('
                cursos.id as curso_id,
                cursos.nombre,
                cursos.codigo,
                cursos.precio,
                COUNT(pagos.id) as total_pagos,
                SUM(CASE WHEN pagos.estado = "aprobado" THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN pagos.estado = "pendiente" THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN pagos.estado = "rechazado" THEN 1 ELSE 0 END) as rechazados,
                SUM(CASE WHEN pagos.estado = "aprobado" THEN cursos.precio ELSE 0 END) as total_ingreso
            ')
            ->groupBy('cursos.id', 'cursos.nombre', 'cursos.codigo', 'cursos.precio')
            ->orderByDesc('total_ingreso')
            ->get()
            ->map(fn ($row) => [
                'curso_id' => $row->curso_id,
                'nombre' => $row->nombre,
                'codigo' => $row->codigo,
                'precio' => (float) $row->precio,
                'total_pagos' => (int) $row->total_pagos,
                'aprobados' => (int) $row->aprobados,
                'pendientes' => (int) $row->pendientes,
                'rechazados' => (int) $row->rechazados,
                'total_ingreso' => (float) $row->total_ingreso,
            ])
            ->toArray();
    }

    private function resumen(): array
    {
        $stats = Pago::query()
            ->join('cursos', 'pagos.curso_id', '=', 'cursos.id')
            ->whereNull('pagos.deleted_at')
            ->selectRaw('
                COUNT(pagos.id) as total_pagos,
                SUM(CASE WHEN pagos.estado = "aprobado" THEN 1 ELSE 0 END) as aprobados,
                SUM(CASE WHEN pagos.estado = "pendiente" THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN pagos.estado = "rechazado" THEN 1 ELSE 0 END) as rechazados,
                SUM(CASE WHEN pagos.estado = "aprobado" THEN cursos.precio ELSE 0 END) as total_ingresos
            ')
            ->first();

        return [
            'total_pagos' => (int) ($stats->total_pagos ?? 0),
            'aprobados' => (int) ($stats->aprobados ?? 0),
            'pendientes' => (int) ($stats->pendientes ?? 0),
            'rechazados' => (int) ($stats->rechazados ?? 0),
            'total_ingresos' => (float) ($stats->total_ingresos ?? 0),
        ];
    }
}
