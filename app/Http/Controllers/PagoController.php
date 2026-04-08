<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PagoController extends Controller
{
    // Estudiante: enviar comprobante de pago para inscribirse
    public function store(Request $request)
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();

        $data = $request->validate([
            'curso_id'    => 'required|exists:cursos,id',
            'referencia'  => 'required|string|max:100',
            'banco_origen'=> 'nullable|string|max:100',
            'comprobante' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        // No permitir doble envío si ya hay un pago pendiente o aprobado
        $existe = Pago::where('estudiante_id', $estudiante->id)
            ->where('curso_id', $data['curso_id'])
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya tienes una solicitud de inscripción activa para este curso.',
            ], 422);
        }

        $path = $request->file('comprobante')->store('comprobantes', 'public');

        $pago = Pago::create([
            'estudiante_id' => $estudiante->id,
            'curso_id'      => $data['curso_id'],
            'referencia'    => $data['referencia'],
            'banco_origen'  => $data['banco_origen'] ?? null,
            'comprobante'   => $path,
            'estado'        => 'pendiente',
        ]);

        return response()->json($pago->load('curso'), 201);
    }

    // Estudiante: ver sus propios pagos
    public function misPagos()
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();

        $pagos = Pago::with('curso')
            ->where('estudiante_id', $estudiante->id)
            ->latest()
            ->get();

        return response()->json($pagos);
    }

    // Admin: listar todos los pagos
    public function index()
    {
        $pagos = Pago::with('estudiante.user', 'curso')
            ->latest()
            ->get()
            ->map(fn ($p) => $this->formatPago($p));

        return response()->json($pagos);
    }

    // Admin: aprobar o rechazar pago
    public function update(Request $request, string $id)
    {
        $pago = Pago::with('estudiante')->findOrFail($id);

        $data = $request->validate([
            'estado'     => 'required|in:aprobado,rechazado',
            'nota_admin' => 'nullable|string|max:500',
        ]);

        $pago->update($data);

        // Si se aprueba, asignar el estudiante al curso
        if ($data['estado'] === 'aprobado') {
            $pago->estudiante->update(['curso_id' => $pago->curso_id]);
        }

        return response()->json($this->formatPago($pago->fresh(['estudiante.user', 'curso'])));
    }

    // Admin: eliminar pago (y su comprobante)
    public function destroy(string $id)
    {
        $pago = Pago::findOrFail($id);
        Storage::disk('public')->delete($pago->comprobante);
        $pago->delete();

        return response()->json(['message' => 'Pago eliminado.']);
    }

    private function formatPago(Pago $pago): array
    {
        $data = $pago->toArray();
        $data['comprobante_url'] = Storage::disk('public')->url($pago->comprobante);

        return $data;
    }
}
