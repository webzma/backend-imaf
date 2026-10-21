<?php

namespace App\Http\Middleware;

use App\Services\Auditoria;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Da a cada petición un id que comparten todos sus eventos de la bitácora y
 * lo devuelve en `X-Request-Id`: con él, soporte encuentra en la bitácora
 * exactamente lo que ocurrió en una petición concreta. Si el cliente ya envía
 * un UUID válido en esa cabecera, se respeta.
 */
class AsignarSolicitudId
{
    public function handle(Request $request, Closure $next): Response
    {
        $entrante = $request->headers->get('X-Request-Id');
        $request->attributes->set(
            Auditoria::ATRIBUTO_SOLICITUD,
            $entrante && Str::isUuid($entrante) ? $entrante : (string) Str::uuid(),
        );

        $response = $next($request);
        $response->headers->set('X-Request-Id', $request->attributes->get(Auditoria::ATRIBUTO_SOLICITUD));

        return $response;
    }
}
