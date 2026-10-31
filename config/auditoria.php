<?php

return [

    /*
    | Activa o desactiva la bitácora. Desactivarla no borra lo registrado.
    */
    'habilitada' => env('AUDITORIA_HABILITADA', true),

    /*
    | Días que se conservan los registros. `bitacora:depurar` borra los más
    | antiguos (se programa a diario). 0 = conservar para siempre.
    */
    'retencion_dias' => (int) env('AUDITORIA_RETENCION_DIAS', 730),

    /*
    | Clave para la huella HMAC de cada registro. Si cambia, los registros
    | anteriores dejan de verificarse: guárdela aparte de APP_KEY si esta
    | puede rotar.
    */
    'clave' => env('AUDITORIA_CLAVE', env('APP_KEY')),

    /*
    | Campos cuyo valor nunca se guarda (se sustituye por "[oculto]"), en
    | cualquier nivel de `cambios` o `metadatos`.
    */
    'campos_ocultos' => [
        'password',
        'password_confirmation',
        'password_actual',
        'remember_token',
        'token',
        'current_password',
        'secret',
        'foto_cedula',
    ],

    /*
    | Campos que no cuentan como cambio (un `touch` no es un evento).
    */
    'campos_ignorados' => [
        'created_at',
        'updated_at',
        // Archivar y restaurar tienen su propio evento.
        'deleted_at',
    ],

    /*
    | Fallos de inicio de sesión desde una misma IP, en la ventana indicada,
    | a partir de los cuales el evento se marca como crítico.
    */
    'umbral_logins_fallidos' => (int) env('AUDITORIA_UMBRAL_LOGINS_FALLIDOS', 5),
    'ventana_logins_fallidos_minutos' => 15,

    /*
    | Tope de filas de una exportación CSV.
    */
    'max_exportacion' => 50000,

];
