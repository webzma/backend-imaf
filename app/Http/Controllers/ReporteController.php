<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ]);
    }

    private function ingresosPorPeriodo(string $periodo): array
    {
        $query = Pago::query()
            ->join('cursos', 'pagos.curso_id', '=', 'cursos.id')
            ->where('pagos.estado', 'aprobado')
            ->whereNull('pagos.deleted_at');

        switch ($periodo) {
            case 'semanal':
                $query->where('pagos.created_at', '>=', now()->subWeeks(12))
                    ->selectRaw("DATE_FORMAT(pagos.created_at, '%x-W%v') as label, SUM(cursos.precio) as total, COUNT(pagos.id) as cantidad")
                    ->groupByRaw("DATE_FORMAT(pagos.created_at, '%x-W%v')")
                    ->orderByRaw("DATE_FORMAT(pagos.created_at, '%x-W%v')");
                break;

            case 'anual':
                $query->where('pagos.created_at', '>=', now()->subYears(5))
                    ->selectRaw("YEAR(pagos.created_at) as label, SUM(cursos.precio) as total, COUNT(pagos.id) as cantidad")
                    ->groupByRaw('YEAR(pagos.created_at)')
                    ->orderByRaw('YEAR(pagos.created_at)');
                break;

            default: // mensual
                $query->where('pagos.created_at', '>=', now()->subMonths(12))
                    ->selectRaw("DATE_FORMAT(pagos.created_at, '%Y-%m') as label, SUM(cursos.precio) as total, COUNT(pagos.id) as cantidad")
                    ->groupByRaw("DATE_FORMAT(pagos.created_at, '%Y-%m')")
                    ->orderByRaw("DATE_FORMAT(pagos.created_at, '%Y-%m')");
                break;
        }

        return $query->get()->map(fn ($row) => [
            'label' => (string) $row->label,
            'total' => (float) $row->total,
            'cantidad' => (int) $row->cantidad,
        ])->toArray();
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
