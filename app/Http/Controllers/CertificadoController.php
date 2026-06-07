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
        $estudiante = Estudiante::with(['curso.instructor.user', 'user'])
            ->findOrFail($id);

        return $this->buildCertificadoResponse($estudiante);
    }

    public function downloadMe(Request $request)
    {
        $estudiante = Estudiante::with(['curso.instructor.user', 'user'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return $this->buildCertificadoResponse($estudiante);
    }

    private function buildCertificadoResponse(Estudiante $estudiante)
    {
        $aproboCurso = $estudiante->estado_aprobacion_curso === 'aprobado';

        if (! $aproboCurso) {
            return response()->json([
                'message' => 'El estudiante aún no ha aprobado el curso, por lo que no puede generar el certificado.',
            ], 422);
        }

        $curso = $estudiante->curso;
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
