<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\Profesor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $periodo = $request->get('periodo', 'mensual');
        $periodo = array_key_exists($periodo, self::VENTANA) ? $periodo : 'mensual';

        $ingresos = $this->ingresosPorPeriodo($periodo);
        $pagosUsuario = $this->pagosPorUsuario();
        $pagosCurso = $this->pagosPorCurso();
        $resumen = $this->resumen();

        return response()->json([
            'ingresos' => $ingresos,
            'pagos_por_usuario' => $pagosUsuario,
            'pagos_por_curso' => $pagosCurso,
            'resumen' => $resumen,
            'periodo' => $this->resumenPeriodo($periodo),
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

    /** Cuántos períodos muestra la serie y la ventana del resumen. */
    private const VENTANA = ['semanal' => 12, 'mensual' => 12, 'anual' => 5];

    /**
     * Inicio del período que contiene `$fecha` (semana ISO, mes o año).
     */
    private function inicioPeriodo(Carbon $fecha, string $periodo): Carbon
    {
        return match ($periodo) {
            'semanal' => $fecha->copy()->startOfWeek(Carbon::MONDAY),
            'anual' => $fecha->copy()->startOfYear(),
            default => $fecha->copy()->startOfMonth(),
        };
    }

    private function sumarPeriodos(Carbon $fecha, string $periodo, int $n): Carbon
    {
        return match ($periodo) {
            'semanal' => $fecha->copy()->addWeeks($n),
            'anual' => $fecha->copy()->addYears($n),
            default => $fecha->copy()->addMonths($n),
        };
    }

    private function etiqueta(Carbon $fecha, string $periodo): string
    {
        return match ($periodo) {
            'semanal' => $fecha->format('o-\WW'),
            'anual' => $fecha->format('Y'),
            default => $fecha->format('Y-m'),
        };
    }

    /**
     * Serie continua de los últimos N períodos, con ceros donde no hubo pagos.
     *
     * Antes se agrupaba en SQL y los períodos sin pagos simplemente no
     * aparecían: el eje saltaba de marzo a junio sin avisar. Ahora se agrupa
     * aquí (el volumen es pequeño) y la misma lógica sirve en cualquier motor.
     */
    private function ingresosPorPeriodo(string $periodo): array
    {
        $n = self::VENTANA[$periodo];
        $actual = $this->inicioPeriodo(now(), $periodo);
        $desde = $this->sumarPeriodos($actual, $periodo, -($n - 1));

        $serie = [];
        for ($i = 0; $i < $n; $i++) {
            $inicio = $this->sumarPeriodos($desde, $periodo, $i);
            $serie[$this->etiqueta($inicio, $periodo)] = [
                'label' => $this->etiqueta($inicio, $periodo),
                'desde' => $inicio->toDateString(),
                'total' => 0.0,
                'cantidad' => 0,
                'aprobados' => 0,
                'pendientes' => 0,
                'rechazados' => 0,
            ];
        }

        foreach ($this->pagosDesde($desde) as $pago) {
            $clave = $this->etiqueta($this->inicioPeriodo(Carbon::parse($pago->created_at), $periodo), $periodo);
            if (! isset($serie[$clave])) {
                continue;
            }
            match ($pago->estado) {
                'aprobado' => $serie[$clave]['aprobados']++,
                'pendiente' => $serie[$clave]['pendientes']++,
                'rechazado' => $serie[$clave]['rechazados']++,
                default => null,
            };
            if ($pago->estado === 'aprobado') {
                $serie[$clave]['total'] += (float) $pago->precio;
                $serie[$clave]['cantidad']++;
            }
        }

        return array_values($serie);
    }

    /**
     * Resumen de la ventana actual (los mismos N períodos de la serie)
     * comparado con la ventana anterior de igual duración.
     *
     * El resumen de siempre es histórico; la pantalla lo presentaba como "del
     * período seleccionado" y no cambiaba al cambiar de período.
     */
    private function resumenPeriodo(string $periodo): array
    {
        $n = self::VENTANA[$periodo];
        $finActual = $this->sumarPeriodos($this->inicioPeriodo(now(), $periodo), $periodo, 1);
        $desdeActual = $this->sumarPeriodos($finActual, $periodo, -$n);
        $desdeAnterior = $this->sumarPeriodos($desdeActual, $periodo, -$n);

        $pagos = $this->pagosDesde($desdeAnterior);

        $agregar = function (Carbon $desde, Carbon $hasta) use ($pagos) {
            $tramo = $pagos->filter(fn ($p) => Carbon::parse($p->created_at)->gte($desde)
                && Carbon::parse($p->created_at)->lt($hasta));
            $aprobados = $tramo->where('estado', 'aprobado');

            return [
                'ingresos' => (float) $aprobados->sum(fn ($p) => (float) $p->precio),
                'total_pagos' => $tramo->count(),
                'aprobados' => $aprobados->count(),
                'pendientes' => $tramo->where('estado', 'pendiente')->count(),
                'rechazados' => $tramo->where('estado', 'rechazado')->count(),
            ];
        };

        $metodos = $pagos
            ->filter(fn ($p) => $p->estado === 'aprobado' && Carbon::parse($p->created_at)->gte($desdeActual))
            ->groupBy(fn ($p) => $p->metodo_pago ?: 'sin_especificar')
            ->map(fn ($grupo, $metodo) => [
                'metodo' => $metodo,
                'cantidad' => $grupo->count(),
                'ingresos' => (float) $grupo->sum(fn ($p) => (float) $p->precio),
            ])
            ->sortByDesc('ingresos')
            ->values()
            ->all();

        return [
            'desde' => $desdeActual->toDateString(),
            'hasta' => $finActual->copy()->subDay()->toDateString(),
            'actual' => $agregar($desdeActual, $finActual),
            'anterior' => $agregar($desdeAnterior, $desdeActual),
            'metodos_pago' => $metodos,
        ];
    }

    /** Pagos (sin borrar) desde una fecha, con el precio de su curso. */
    private function pagosDesde(Carbon $desde)
    {
        return Pago::query()
            ->join('cursos', 'pagos.curso_id', '=', 'cursos.id')
            ->whereNull('pagos.deleted_at')
            ->where('pagos.created_at', '>=', $desde)
            ->get(['pagos.estado', 'pagos.metodo_pago', 'pagos.created_at', 'cursos.precio']);
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
