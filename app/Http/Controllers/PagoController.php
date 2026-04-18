<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\User;
use App\Notifications\GenericNotification;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagoController extends Controller
{
    // Estudiante: ver sus propios pagos (con historial completo incluyendo rechazados)
    public function studentIndex(Request $request)
    {
        $query = Pago::with('curso')
            ->where('user_id', $request->user()->id)
            ->latest();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->curso_id);
        }

        return response()->json($query->paginate(10));
    }

    // Estudiante: enviar comprobante de pago para inscribirse
    public function store(Request $request)
    {
        $request->validate([
            'curso_id'     => 'required|exists:cursos,id',
            'referencia'   => 'required|string|max:100',
            'banco_origen' => 'nullable|string|max:100',
            'comprobante'  => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $user = $request->user();
        $curso = Curso::findOrFail($request->curso_id);

        if ($curso->cupos_restantes <= 0) {
            return response()->json([
                'message' => 'El curso no tiene cupos disponibles.',
            ], 422);
        }

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

        $uploadedFile = Cloudinary::uploadApi()->upload(
            $request->file('comprobante')->getRealPath(),
            ['folder' => 'imaf/comprobantes']
        );

        $pago = Pago::create([
            'user_id'      => $user->id,
            'curso_id'     => $request->curso_id,
            'referencia'   => $request->referencia,
            'banco_origen' => $request->banco_origen,
            'comprobante'  => $uploadedFile['public_id'],
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
    public function index(Request $request)
    {
        $query = Pago::with(['user', 'curso', 'estudiante'])->latest();

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->curso_id);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $pagos = $query->paginate(20);

        $pagos->getCollection()->transform(fn ($pago) => $this->formatPago($pago));

        return response()->json($pagos);
    }

    // Admin: aprobar o rechazar pago
    public function update(Request $request, string $id)
    {
        $request->validate([
            'estado'     => 'required|in:aprobado,rechazado',
            'nota_admin' => 'nullable|string|max:500',
        ]);

        $pago = Pago::with(['user', 'curso', 'estudiante'])->findOrFail($id);

        $cupoAgotado = false;

        DB::transaction(function () use ($pago, $request, &$cupoAgotado) {
            // Bloquear el curso para evitar aprobaciones simultáneas que excedan el cupo
            $curso = Curso::lockForUpdate()->findOrFail($pago->curso_id);

            if ($request->estado === 'aprobado') {
                $ocupados = Estudiante::where('curso_id', $curso->id)->count();
                if ($ocupados >= $curso->limite_cupo) {
                    $cupoAgotado = true;
                    return;
                }
            }

            $pago->update([
                'estado'     => $request->estado,
                'nota_admin' => $request->nota_admin,
            ]);

            $estudiante = Estudiante::where('user_id', $pago->user_id)->first();

            if ($estudiante) {
                if ($request->estado === 'aprobado') {
                    $estudiante->update([
                        'curso_id'    => $pago->curso_id,
                        'estado_pago' => 'aprobado',
                        'estado'      => 'activo',
                    ]);
                } elseif ($request->estado === 'rechazado') {
                    $estudiante->update([
                        'estado_pago' => 'reprobado',
                    ]);
                }
            }
        });

        if ($cupoAgotado) {
            return response()->json([
                'message' => 'No se puede aprobar: el curso ya no tiene cupos disponibles.',
            ], 422);
        }

        $pago->refresh();

        return response()->json([
            'message' => "Pago {$request->estado} con éxito.",
            'pago'    => $this->formatPago($pago),
        ]);
    }

    // Admin: eliminar pago (y su comprobante)
    public function destroy(string $id)
    {
        $pago = Pago::findOrFail($id);
        Cloudinary::uploadApi()->destroy($pago->comprobante);
        $pago->forceDelete();

        return response()->json(['message' => 'Pago eliminado.']);
    }

    private function formatPago(Pago $pago): array
    {
        $user       = $pago->user;
        $curso      = $pago->curso;
        $estudiante = $pago->estudiante;

        return [
            'id'              => $pago->id,
            'referencia'      => $pago->referencia,
            'banco_origen'    => $pago->banco_origen,
            'comprobante'     => $pago->comprobante,
            'comprobante_url' => $pago->comprobante_url,
            'estado'          => $pago->estado,
            'nota_admin'      => $pago->nota_admin,
            'created_at'      => $pago->created_at,
            'estudiante'      => $estudiante ? [
                'id'     => $estudiante->id,
                'nombre' => $estudiante->nombre,
                'cedula' => $estudiante->cedula,
                'user'   => $user ? [
                    'name'  => $user->name,
                    'email' => $user->email,
                ] : null,
            ] : null,
            'curso'           => $curso ? [
                'id'     => $pago->getRawOriginal('curso_id'),
                'nombre' => $curso->nombre,
                'codigo' => $curso->codigo,
            ] : null,
        ];
    }
}
