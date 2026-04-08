<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Pago;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PagoController extends Controller
{
    // Estudiante: ver sus propios pagos
    public function studentIndex(Request $request)
    {
        $pagos = Pago::with('curso')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($pagos);
    }

    // Estudiante: enviar comprobante de pago para inscribirse
    public function store(Request $request)
    {
        $request->validate([
            'curso_id'     => 'required|exists:cursos,id',
            'referencia'   => 'required|string|max:100',
            'banco_origen' => 'required|string|max:100',
            'comprobante'  => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $user = $request->user();
        $curso = Curso::findOrFail($request->curso_id);

        // No permitir doble envío si ya hay un pago pendiente o aprobado
        $existe = Pago::where('user_id', $user->id)
            ->where('curso_id', $request->curso_id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Ya tienes una solicitud de inscripción activa para este curso.',
            ], 422);
        }

        $file = $request->file('comprobante');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('public/comprobantes', $filename);

        $pago = Pago::create([
            'user_id'      => $user->id,
            'curso_id'     => $request->curso_id,
            'referencia'   => $request->referencia,
            'banco_origen' => $request->banco_origen,
            'comprobante'  => $filename,
            'estado'       => 'pendiente',
        ]);

        // Notificar a los administradores
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new GenericNotification(
                'Nueva Solicitud de Pago',
                "El estudiante {$user->name} ha solicitado inscribirse en {$curso->nombre}.",
                '/admin/pagos'
            ));
        }

        return response()->json($pago->load('curso'), 201);
    }

    // Admin: listar todos los pagos
    public function index()
    {
        $pagos = Pago::with(['estudiante.user', 'curso'])
            ->latest()
            ->get();

        $formattedPagos = $pagos->map(function ($pago) {
            $estudiante = $pago->estudiante;
            $user = $estudiante ? $estudiante->user : null;
            $curso = $pago->curso;

            return [
                'id'             => $pago->id,
                'referencia'     => $pago->referencia,
                'banco_origen'   => $pago->banco_origen,
                'comprobante'    => $pago->comprobante,
                'comprobante_url'=> $pago->comprobante_url,
                'estado'         => $pago->estado,
                'nota_admin'     => $pago->nota_admin,
                'created_at'     => $pago->created_at,
                'estudiante'     => $estudiante ? [
                    'id'     => $estudiante->id,
                    'nombre' => $estudiante->nombre,
                    'cedula' => $estudiante->cedula,
                    'user'   => $user ? [
                        'name'  => $user->name,
                        'email' => $user->email,
                    ] : null,
                ] : null,
                'curso' => $curso ? [
                    'id'     => $pago->getRawOriginal('curso_id'),
                    'nombre' => $curso->nombre,
                    'codigo' => $curso->codigo,
                ] : null,
            ];
        });

        return response()->json($formattedPagos);
    }

    // Admin: aprobar o rechazar pago
    public function update(Request $request, string $id)
    {
        $request->validate([
            'estado'     => 'required|in:aprobado,rechazado',
            'nota_admin' => 'nullable|string|max:500',
        ]);

        $pago = Pago::with(['estudiante.user', 'curso'])->findOrFail($id);

        $pago->update([
            'estado'     => $request->estado,
            'nota_admin' => $request->nota_admin,
        ]);

        $pago->load(['estudiante.user', 'curso']);

        return response()->json([
            'message' => "Pago {$request->estado} con éxito.",
            'pago'    => [
                'id'             => $pago->id,
                'referencia'     => $pago->referencia,
                'banco_origen'   => $pago->banco_origen,
                'comprobante'    => $pago->comprobante,
                'comprobante_url'=> $pago->comprobante_url,
                'estado'         => $pago->estado,
                'nota_admin'     => $pago->nota_admin,
                'created_at'     => $pago->created_at,
                'estudiante'     => $pago->estudiante ? [
                    'id'     => $pago->estudiante->id,
                    'nombre' => $pago->estudiante->nombre,
                    'cedula' => $pago->estudiante->cedula,
                    'user'   => $pago->estudiante->user ? [
                        'name'  => $pago->estudiante->user->name,
                        'email' => $pago->estudiante->user->email,
                    ] : null,
                ] : null,
                'curso' => $pago->curso ? [
                    'id'     => $pago->getRawOriginal('curso_id'),
                    'nombre' => $pago->curso->nombre,
                    'codigo' => $pago->curso->codigo,
                ] : null,
            ],
        ]);
    }

    // Admin: eliminar pago (y su comprobante)
    public function destroy(string $id)
    {
        $pago = Pago::findOrFail($id);
        Storage::disk('public')->delete('comprobantes/' . $pago->comprobante);
        $pago->delete();

        return response()->json(['message' => 'Pago eliminado.']);
    }
}
