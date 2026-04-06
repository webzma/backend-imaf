<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\User;
use App\Models\Curso;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PagoController extends Controller
{
    public function studentIndex(Request $request)
    {
        $pagos = Pago::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($pagos);
    }

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
                'id' => $pago->id,
                'referencia' => $pago->referencia,
                'banco_origen' => $pago->banco_origen,
                'comprobante' => $pago->comprobante,
                'comprobante_url' => $pago->comprobante_url,
                'estado' => $pago->estado,
                'nota_admin' => $pago->nota_admin,
                'created_at' => $pago->created_at,
                'estudiante' => $estudiante ? [
                    'id' => $estudiante->id,
                    'nombre' => $estudiante->nombre,
                    'cedula' => $estudiante->cedula,
                    'user' => $user ? [
                        'name' => $user->name,
                        'email' => $user->email,
                    ] : null,
                ] : null,
                'curso' => $curso ? [
                    'id' => $pago->getRawOriginal('curso_id'),
                    'nombre' => $curso->nombre,
                    'codigo' => $curso->codigo,
                ] : null,
            ];
        });

        return response()->json($formattedPagos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'referencia' => 'required|string',
            'banco_origen' => 'required|string',
            'comprobante' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();
        $curso = Curso::findOrFail($request->curso_id);

        // Guardar archivo
        $file = $request->file('comprobante');
        $filename = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('public/comprobantes', $filename);

        $pago = Pago::create([
            'user_id' => $user->id,
            'curso_id' => $request->curso_id,
            'referencia' => $request->referencia,
            'banco_origen' => $request->banco_origen,
            'comprobante' => $filename,
            'estado' => 'pendiente',
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

        return response()->json($pago, 201);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'estado' => 'required|in:aprobado,rechazado',
            'nota_admin' => 'nullable|string',
        ]);

        $pago = Pago::with(['estudiante.user', 'curso'])->findOrFail($id);

        $pago->update([
            'estado' => $request->estado,
            'nota_admin' => $request->nota_admin,
        ]);

        // Cargar relaciones antes de retornar
        $pago->load(['estudiante.user', 'curso']);

        return response()->json([
            'message' => "Pago {$request->estado} con éxito.",
            'pago' => [
                'id' => $pago->id,
                'referencia' => $pago->referencia,
                'banco_origen' => $pago->banco_origen,
                'comprobante' => $pago->comprobante,
                'comprobante_url' => $pago->comprobante_url,
                'estado' => $pago->estado,
                'nota_admin' => $pago->nota_admin,
                'created_at' => $pago->created_at,
                'estudiante' => $pago->estudiante ? [
                    'id' => $pago->estudiante->id,
                    'nombre' => $pago->estudiante->nombre,
                    'cedula' => $pago->estudiante->cedula,
                    'user' => [
                        'name' => $pago->estudiante->user->name,
                        'email' => $pago->estudiante->user->email,
                    ],
                ] : null,
                'curso' => $pago->curso ? [
                    'id' => $pago->getRawOriginal('curso_id'),
                    'nombre' => $pago->curso->nombre,
                    'codigo' => $pago->curso->codigo,
                ] : null,
            ]
        ]);
    }
}
