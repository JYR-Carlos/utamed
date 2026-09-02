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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SGEQ — Sistema de Registro y Préstamos de equipos
    |--------------------------------------------------------------------------
    |
    | UTAmed le entrega la identidad del usuario a SGEQ con un token firmado.
    | Ver App\Services\Sso\SgeqSsoService para quién puede pasar y con qué rol.
    |
    | 'carreras' son los IDs de administrativo.carrera cuyos estudiantes pueden
    | abrir SGEQ. Vacío = ningún estudiante entra (lado seguro del error).
    |
    */
    'sgeq' => [
        'url' => env('SGEQ_URL'),
        'issuer' => env('SGEQ_SSO_ISSUER', 'utamed'),
        'audience' => env('SGEQ_SSO_AUDIENCE', 'sgeq'),
        'private_key_path' => env('SGEQ_SSO_PRIVATE_KEY_PATH', storage_path('app/private/sso/sgeq-sso-private.pem')),

        // Segundos de vigencia del token. Sólo tiene que alcanzar para un redirect.
        'ttl' => env('SGEQ_SSO_TTL', 60),

        'carreras' => array_filter(explode(',', (string) env('SGEQ_SSO_CARRERAS', ''))),
    ],

];
