<?php

namespace App\Http\Controllers;

use App\Models\Curso;

/**
 * Datos públicos para la página de inicio (sin sesión).
 *
 * Solo lo que un visitante necesita para decidir si inscribirse: nada de
 * estudiantes, instructores ni cifras internas.
 */
class PublicoController extends Controller
{
    /** Cursos abiertos: activos y sin terminar, los que empiezan antes primero. */
    public function cursos()
    {
        $cursos = Curso::query()
            ->where('estado', 'activo')
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>', today()))
            ->orderByRaw('fecha_inicio IS NULL')
            ->orderBy('fecha_inicio')
            ->limit(6)
            ->get()
            ->map(fn (Curso $c) => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nombre' => $c->nombre,
                'descripcion' => $c->descripcion,
                'precio' => (float) $c->precio,
                'fecha_inicio' => $c->fecha_inicio?->toDateString(),
                'fecha_fin' => $c->fecha_fin?->toDateString(),
                'limite_cupo' => (int) $c->limite_cupo,
                'cupos_restantes' => $c->cupos_restantes,
                'modalidad' => $c->modalidad,
            ]);

        return response()->json(['data' => $cursos]);
    }
}
