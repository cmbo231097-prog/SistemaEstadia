<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
    ],

    // Correo del Líder que recibe las solicitudes de estudiantes para ser profesor (solo 1 destinatario).
    // Si no se define, se usa el Líder activo más antiguo.
    'solicitudes_profesor' => [
        'correo' => env('SOLICITUDES_PROFESOR_EMAIL'),
    ],

    // Intérprete de PHP que se usa para lanzar el procesador de la cola en segundo plano (envío de lotes a la IA).
    'php_binary' => env('PHP_BIN', PHP_BINARY),

    // Intérprete con pypdf instalado (scripts/requirements.txt). Si existe, se usa el entorno virtual .venv del proyecto.
    'python' => [
        'binary' => env('PYTHON_BIN', is_file(base_path('.venv/bin/python')) ? base_path('.venv/bin/python') : 'python3'),
        'sanitizer_script' => base_path('scripts/pdf_sanitizer.py'),
        'timeout' => (int) env('PYTHON_TIMEOUT', 60),
    ],

    // Motor comparativo: margen de error porcentual (frente al docente) a partir del cual se marca una alerta.
    'comparativa' => [
        'umbral_alerta' => (float) env('COMPARATIVA_UMBRAL', 10),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
