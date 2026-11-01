<?php

return [
    // Rutas a las utilerías nativas. Si no se definen, se buscan en el PATH y en ubicaciones comunes (XAMPP, Homebrew, Linux).
    'dump_binary' => env('DB_DUMP_BINARY'),
    'client_binary' => env('DB_CLIENT_BINARY'),

    // Directorio (dentro del disco local) donde se guardan los respaldos.
    'directory' => 'backups',

    // Tamaño máximo (KB) del archivo de restauración que se puede cargar.
    'max_upload_kb' => 40960,

    // Tiempo máximo (s) de cada operación de volcado o restauración.
    'timeout' => 300,
];
