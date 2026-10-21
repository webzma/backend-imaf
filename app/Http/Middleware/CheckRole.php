<?php

namespace App\Http\Middleware;

use App\Services\Auditoria;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles)) {
            Auditoria::registrar(
                'seguridad.acceso_denegado',
                'Acceso denegado a '.$request->method().' /'.$request->path(),
                [
                    'nivel' => 'aviso',
                    'metadatos' => ['roles_requeridos' => $roles, 'rol' => $request->user()?->role],
                ],
            );

            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return $next($request);
    }
}
