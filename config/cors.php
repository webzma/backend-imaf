<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| El default del framework es `allowed_origins => ['*']`, que deja la API
| abierta a cualquier origen. Aquí se fija la lista al dominio del frontend
| (`FRONTEND_URL`) más lo que se declare en `CORS_ALLOWED_ORIGINS`, separado
| por comas, para entornos de preview o dominios adicionales.
|
*/

$origenes = collect(explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))
    ->push(env('FRONTEND_URL'))
    ->map(fn ($origen) => rtrim(trim((string) $origen), '/'))
    ->filter()
    ->unique()
    ->values()
    ->all();

// En local se permiten los puertos habituales de `next dev` para no tener que
// declararlos a mano en cada máquina del equipo.
if (env('APP_ENV') === 'local') {
    $origenes = array_values(array_unique(array_merge($origenes, [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ])));
}

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origenes,

    'allowed_origins_patterns' => array_values(array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
    )),

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 86400,

    // La autenticación es por Bearer token, no por cookie de sesión: no hacen
    // falta credenciales en las peticiones cross-origin.
    'supports_credentials' => false,

];
