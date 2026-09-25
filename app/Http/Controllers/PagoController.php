<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Profesor;
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

    // Estudiante: reportar un pago (transferencia, pago móvil o efectivo) para inscribirse
    public function store(Request $request)
    {
        $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'metodo_pago' => 'required|in:transferencia,pago_movil,efectivo',
            'referencia' => ['exclude_if:metodo_pago,efectivo', 'required', 'string', 'max:100', 'regex:/^[0-9]+$/'],
            'banco_origen' => 'nullable|string|max:100',
            'comprobante' => ['exclude_if:metodo_pago,efectivo', 'required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'referencia.regex' => 'El número de referencia solo puede contener dígitos numéricos.',
            'metodo_pago.in' => 'El método de pago debe ser transferencia, pago móvil o efectivo.',
        ]);

        $user = $request->user();
        $curso = Curso::findOrFail($request->curso_id);

        $finalizado = $curso->fecha_fin && $curso->fecha_fin->isPast();
        if ($curso->estado !== 'activo' || $finalizado) {
            return response()->json([
                'message' => 'El curso no está disponible para inscripciones.',
            ], 422);
        }

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

        $comprobanteId = null;
        if ($request->hasFile('comprobante')) {
            $uploadedFile = Cloudinary::uploadApi()->upload(
                $request->file('comprobante')->getRealPath(),
                ['folder' => 'imaf/comprobantes']
            );
            $comprobanteId = $uploadedFile['public_id'];
        }

        $esEfectivo = $request->metodo_pago === 'efectivo';

        $pago = Pago::create([
            'user_id' => $user->id,
            'curso_id' => $request->curso_id,
            'metodo_pago' => $request->metodo_pago,
            'referencia' => $esEfectivo ? null : $request->referencia,
            'banco_origen' => $esEfectivo ? null : $request->banco_origen,
            'comprobante' => $comprobanteId,
            'estado' => 'pendiente',
        ]);

        // Sin curso actual, el estudiante queda pendiente por pago hasta que el
        // admin verifique. Si ya cursa otro, su acceso a ese curso no cambia.
        Estudiante::where('user_id', $user->id)
            ->whereNull('curso_id')
            ->update(['estado_pago' => 'pendiente']);

        // Figura en el listado interno del curso desde que solicita, con el
        // pago pendiente; no ocupa cupo hasta que se apruebe.
        if ($estudiante = Estudiante::where('user_id', $user->id)->first()) {
            $inscripcion = Inscripcion::firstOrNew([
                'estudiante_id' => $estudiante->id,
                'curso_id' => $curso->id,
            ]);
            if ($inscripcion->estado_pago !== 'aprobado') {
                $inscripcion->estado_pago = 'pendiente';
                $inscripcion->fecha_inscripcion ??= now()->toDateString();
                $inscripcion->save();
            }
        }

        $metodoLabel = [
            'transferencia' => 'transferencia',
            'pago_movil' => 'pago móvil',
            'efectivo' => 'efectivo',
        ][$request->metodo_pago];

        // Notificar a los administradores
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new GenericNotification(
                'Nueva Solicitud de Pago',
                "El estudiante {$user->name} ha solicitado inscribirse en {$curso->nombre} (pago vía {$metodoLabel}).",
                '/admin/pagos'
            ));
        }

        return response()->json($pago->load('curso'), 201);
    }

    // Admin: listar todos los pagos
    /**
     * Orden por defecto: lo que hay que atender primero.
     *
     * Un pendiente es trabajo sin hacer; un aprobado o rechazado es historial.
     * Esta prioridad estaba en el cliente, ordenando solo los 20 registros que
     * tenía en memoria, así que un pendiente antiguo nunca subía a la vista.
     */
    private const ORDEN_PAGOS = [
        'prioridad' => null,
        'fecha' => 'created_at',
        'estado' => 'estado',
    ];

    public function index(Request $request)
    {
        $query = Pago::with(['user', 'curso', 'estudiante']);

        if ($search = $this->terminoBusqueda($request)) {
            $query->where(function ($q) use ($search) {
                $q->where('referencia', 'like', "%{$search}%")
                    ->orWhereHas('estudiante', fn ($e) => $e->where('nombre', 'like', "%{$search}%")
                        ->orWhere('cedula', 'like', "%{$search}%"))
                    ->orWhereHas('curso', fn ($c) => $c->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%"));
            });
        }

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

        $sort = (string) $request->query('sort', 'prioridad');

        if ($sort === 'prioridad' || ! array_key_exists($sort, self::ORDEN_PAGOS)) {
            $query->orderByRaw("CASE estado WHEN 'pendiente' THEN 0 WHEN 'aprobado' THEN 1 ELSE 2 END")
                ->latest();
        } else {
            $this->aplicarOrden($query, $request, array_filter(self::ORDEN_PAGOS), 'fecha', 'desc');
        }

        // `per_page` fijo en 20 ignoraba lo que pedía el cliente, así que la UI
        // recibía 20 filas, las partía en dos páginas de 10 propias y jamás
        // llegaba a la tercera.
        $pagos = $query->paginate($this->registrosPorPagina($request));

        $pagos->getCollection()->transform(fn ($pago) => $this->formatPago($pago));

        return response()->json($pagos);
    }

    /** Totales por estado sobre la tabla completa, para las tarjetas de resumen. */
    public function resumen()
    {
        return response()->json([
            'total' => Pago::count(),
            'pendiente' => Pago::where('estado', 'pendiente')->count(),
            'aprobado' => Pago::where('estado', 'aprobado')->count(),
            'rechazado' => Pago::where('estado', 'rechazado')->count(),
        ]);
    }

    // Admin: aprobar o rechazar pago
    public function update(Request $request, string $id)
    {
        $request->validate([
            'estado' => 'required|in:aprobado,rechazado',
            'nota_admin' => 'nullable|string|max:500',
        ]);

        $pago = Pago::with(['user', 'curso', 'estudiante'])->findOrFail($id);

        $resultado = $this->procesarPago($pago, $request->estado, $request->nota_admin);

        if (! $resultado['ok']) {
            return response()->json(['message' => $resultado['message']], 422);
        }

        return response()->json([
            'message' => $resultado['message'],
            'pago' => $this->formatPago($pago),
        ]);
    }

    /**
     * Aprueba o rechaza varios pagos de una vez.
     *
     * Revisar la cola de pagos era el trabajo más repetitivo del panel: doce
     * pagos pendientes eran doce recorridos de abrir ficha, confirmar y
     * cerrar. Cada pago se procesa por separado y con su propia transacción,
     * así que uno que falle por cupo agotado no arrastra a los demás; la
     * respuesta dice cuáles quedaron fuera y por qué.
     */
    public function updateMasivo(Request $request)
    {
        $datos = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer|exists:pagos,id',
            'estado' => 'required|in:aprobado,rechazado',
            'nota_admin' => 'nullable|string|max:500',
        ]);

        $procesados = 0;
        $fallidos = [];

        foreach (Pago::with(['user', 'curso', 'estudiante'])->whereIn('id', $datos['ids'])->get() as $pago) {
            $resultado = $this->procesarPago($pago, $datos['estado'], $datos['nota_admin'] ?? null);

            if ($resultado['ok']) {
                $procesados++;
            } else {
                $fallidos[] = ['id' => $pago->id, 'message' => $resultado['message']];
            }
        }

        return response()->json([
            'procesados' => $procesados,
            'fallidos' => $fallidos,
        ]);
    }

    /**
     * Aplica la decisión sobre un pago: cupo, estado del estudiante y aviso al
     * instructor. Es el único sitio donde se decide qué implica aprobar.
     *
     * @return array{ok: bool, message: string}
     */
    private function procesarPago(Pago $pago, string $estado, ?string $nota): array
    {
        $cupoAgotado = false;

        DB::transaction(function () use ($pago, $estado, $nota, &$cupoAgotado) {
            // Bloquear el curso para evitar aprobaciones simultáneas que excedan el cupo
            $curso = Curso::lockForUpdate()->findOrFail($pago->curso_id);

            $estudiante = Estudiante::where('user_id', $pago->user_id)->first();

            if ($estado === 'aprobado') {
                $yaInscrito = $estudiante && $curso->estudiantes()->whereKey($estudiante->id)->exists();
                if (! $yaInscrito && $curso->estudiantes()->count() >= $curso->limite_cupo) {
                    $cupoAgotado = true;

                    return;
                }
            }

            $pago->update([
                'estado' => $estado,
                'nota_admin' => $nota,
            ]);

            if ($estudiante) {
                if ($estado === 'aprobado') {
                    // El curso pagado pasa a ser el actual; el anterior queda
                    // en `inscripciones` (ver Estudiante::booted).
                    $estudiante->update([
                        'curso_id' => $pago->curso_id,
                        'estado_pago' => 'aprobado',
                        'estado' => 'activo',
                    ]);
                    // Si ya era su curso actual, el observer no toca la
                    // inscripción: se marca aquí.
                    Inscripcion::updateOrCreate(
                        ['estudiante_id' => $estudiante->id, 'curso_id' => $pago->curso_id],
                        ['estado_pago' => 'aprobado'],
                    );
                } else {
                    $this->rechazarInscripcion($estudiante, $pago);
                }
            }
        });

        if ($cupoAgotado) {
            return [
                'ok' => false,
                'message' => 'No se puede aprobar: el curso ya no tiene cupos disponibles.',
            ];
        }

        $pago->refresh();

        $this->notificarEstudiante($pago, $estado, $nota);

        // Si el pago fue aprobado, notificar al profesor del curso
        if ($estado === 'aprobado') {
            $curso = Curso::find($pago->curso_id);
            if ($curso && $curso->profesor_id) {
                $profesor = Profesor::with('user')->find($curso->profesor_id);
                if ($profesor && $profesor->user) {
                    $estudianteNombre = $pago->user->name ?? 'Un estudiante';
                    $profesor->user->notify(new GenericNotification(
                        'Nueva inscripción aprobada',
                        "El estudiante {$estudianteNombre} ha sido aprobado para el curso: {$curso->nombre}.",
                        "/profesor/cursos/{$curso->id}"
                    ));
                }
            }
        }

        return ['ok' => true, 'message' => "Pago {$estado} con éxito."];
    }

    /**
     * Con el pago rechazado el estudiante deja de estar inscrito en ese curso,
     * aunque antes se hubiera aprobado o el admin lo hubiera asignado a mano.
     * La inscripción se conserva marcada como `reprobado` para que siga en el
     * listado interno del curso, pero ya no ocupa cupo ni la ve el estudiante.
     * Si era su curso actual, vuelve al último curso pagado que le quede.
     */
    private function rechazarInscripcion(Estudiante $estudiante, Pago $pago): void
    {
        $otroPagoAprobado = Pago::where('user_id', $pago->user_id)
            ->where('curso_id', $pago->curso_id)
            ->where('estado', 'aprobado')
            ->whereKeyNot($pago->id)
            ->exists();

        if ($otroPagoAprobado) {
            return;
        }

        $estudiante->retirarDeCurso((int) $pago->curso_id, 'reprobado');
    }

    /** Avisa al estudiante de la decisión sobre su pago. */
    private function notificarEstudiante(Pago $pago, string $estado, ?string $nota): void
    {
        $nombreCurso = $pago->curso->nombre ?? 'el curso';

        if ($estado === 'aprobado') {
            $pago->user?->notify(new GenericNotification(
                '¡Pago Aprobado!',
                "Tu pago para el curso {$nombreCurso} ha sido aprobado. Ya estás inscrito en el curso.",
                "/estudiante/curso?id={$pago->curso_id}"
            ));

            return;
        }

        $motivo = filled($nota) ? " Motivo: {$nota}" : '';
        $pago->user?->notify(new GenericNotification(
            'Pago Rechazado',
            "Tu pago para el curso {$nombreCurso} ha sido rechazado.{$motivo}",
            '/estudiante/cursos'
        ));
    }

    // Admin: eliminar pago (y su comprobante)
    public function destroy(string $id)
    {
        $pago = Pago::findOrFail($id);
        if (! empty($pago->comprobante)) {
            Cloudinary::uploadApi()->destroy($pago->comprobante);
        }
        $pago->forceDelete();

        return response()->json(['message' => 'Pago eliminado.']);
    }

    private function formatPago(Pago $pago): array
    {
        $user = $pago->user;
        $curso = $pago->curso;
        $estudiante = $pago->estudiante;

        return [
            'id' => $pago->id,
            'metodo_pago' => $pago->metodo_pago,
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
                'foto' => $estudiante->foto,
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
    }
}
