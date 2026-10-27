<?php

namespace App\Enums;

enum LoginErrorCode: string
{
    /**
     * RUT inexistente o contraseña errónea: el mensaje es el mismo para no
     * revelar qué RUT tienen cuenta. El log del servidor sí distingue la causa.
     */
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    /** Sólo se informa después de comprobar la contraseña. */
    case USER_INACTIVE = 'USER_INACTIVE';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';

    public function message(): string
    {
        return match ($this) {
            self::INVALID_CREDENTIALS => 'Credenciales incorrectas.',
            self::USER_INACTIVE => 'Tu cuenta está desactivada. Para reactivarla, escribe a cite@gestion.uta.cl desde tu correo institucional.',
            self::RATE_LIMIT_EXCEEDED => 'Hubo varios intentos fallidos seguidos. Espera unos segundos e inténtalo de nuevo.',
        };
    }
}
