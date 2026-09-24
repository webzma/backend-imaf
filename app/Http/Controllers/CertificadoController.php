<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CertificadoController extends Controller
{
    public function download(Request $request, string $id)
    {
        $estudiante = Estudiante::with('user')->findOrFail($id);

        return $this->buildCertificadoResponse($request, $estudiante);
    }

    public function downloadMe(Request $request)
    {
        $estudiante = Estudiante::with('user')
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return $this->buildCertificadoResponse($request, $estudiante);
    }

    /**
     * El certificado es de un curso concreto: el de `?curso_id=` o, sin él, el
     * curso actual. La aprobación se lee de la inscripción de ese curso.
     */
    private function buildCertificadoResponse(Request $request, Estudiante $estudiante)
    {
        $cursoId = $request->filled('curso_id')
            ? (int) $request->curso_id
            : $estudiante->curso_id;

        $curso = $cursoId
            ? $estudiante->cursos()->with('instructor.user')->whereKey($cursoId)->first()
            : null;

        if ($curso && $curso->pivot->estado_aprobacion_curso !== 'aprobado') {
            return response()->json([
                'message' => 'El estudiante aún no ha aprobado el curso, por lo que no puede generar el certificado.',
            ], 422);
        }

        $profesor = $curso?->instructor;

        if (! $curso || ! $profesor) {
            return response()->json([
                'message' => 'Datos del curso incompletos.',
            ], 422);
        }

        try {
            $pdf = Pdf::loadView('certificado', compact('estudiante', 'curso', 'profesor'))
                ->setPaper('a4', 'landscape');

            $filename = 'certificado_'.str($estudiante->nombre)->slug().'.pdf';

            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('Error generando certificado: '.$e->getMessage());

            return response()->json([
                'message' => 'Error al generar el certificado: '.$e->getMessage(),
            ], 500);
        }
    }
}
