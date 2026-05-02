<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    public function download(Request $request, string $estudianteId)
    {
        $estudiante = Estudiante::with(['curso.instructor.user', 'user'])
            ->findOrFail($estudianteId);

        if ($estudiante->estado_aprobacion_curso !== 'aprobado') {
            return response()->json([
                'message' => 'El estudiante no ha completado el curso.',
            ], 422);
        }

        $curso = $estudiante->curso;
        $profesor = $curso?->instructor;

        if (! $curso || ! $profesor) {
            return response()->json([
                'message' => 'Datos del curso incompletos.',
            ], 422);
        }

        $pdf = Pdf::loadView('certificado', compact('estudiante', 'curso', 'profesor'))
            ->setPaper('a4', 'landscape');

        $filename = 'certificado_'.str($estudiante->nombre)->slug().'.pdf';

        return $pdf->download($filename);
    }
}
