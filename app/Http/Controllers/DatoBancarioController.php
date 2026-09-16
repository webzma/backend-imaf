<?php

namespace App\Http\Controllers;

use App\Models\DatoBancario;
use Illuminate\Http\Request;

class DatoBancarioController extends Controller
{
    /**
     * Admin: listar todos los datos bancarios.
     */
    public function index()
    {
        $datos = DatoBancario::all();
        return response()->json($datos);
    }

    /**
     * Admin: guardar (crear o actualizar) datos bancarios por tipo.
     * Se envía un array con los datos y el tipo (pago_movil o transferencia).
     */
    public function store(Request $request)
    {
        $tipo = $request->tipo;

        if ($tipo === 'pago_movil') {
            $validated = $request->validate([
                'tipo' => 'required|in:pago_movil',
                'rif' => 'required|string|max:20',
                'banco' => 'required|string|max:100',
                'telefono' => 'required|string|max:20',
                'concepto' => 'required|string|max:255',
            ]);
        } elseif ($tipo === 'transferencia') {
            $validated = $request->validate([
                'tipo' => 'required|in:transferencia',
                'rif' => 'required|string|max:20',
                'banco' => 'required|string|max:100',
                'numero_cuenta' => 'required|string|max:30',
                'concepto' => 'required|string|max:255',
                'nombre_titular' => 'required|string|max:255',
            ]);
        } else {
            return response()->json([
                'message' => 'El tipo debe ser pago_movil o transferencia.',
            ], 422);
        }

        $dato = DatoBancario::where('tipo', $tipo)->first();

        if ($dato) {
            $dato->update($validated);
        } else {
            $dato = DatoBancario::create($validated);
        }

        return response()->json($dato);
    }

    /**
     * Pública: obtener datos bancarios por tipo (para que el estudiante los consuma).
     */
    public function show(string $tipo)
    {
        if (!in_array($tipo, ['pago_movil', 'transferencia'])) {
            return response()->json([
                'message' => 'Tipo no válido.',
            ], 422);
        }

        $dato = DatoBancario::where('tipo', $tipo)->first();

        if (!$dato) {
            return response()->json(null, 200);
        }

        return response()->json($dato);
    }

    /**
     * Pública: obtener todos los datos bancarios (para que el estudiante los consuma).
     */
    public function all()
    {
        $datos = DatoBancario::all();
        return response()->json($datos);
    }
}
