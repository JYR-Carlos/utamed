<?php

namespace App\Enums;

enum LoginErrorCode: string
{
    case RUT_NOT_FOUND = 'RUT_NOT_FOUND';
    case PASSWORD_INCORRECT = 'PASSWORD_INCORRECT';
    case USER_INACTIVE = 'USER_INACTIVE';
    case EMAIL_NOT_VERIFIED = 'EMAIL_NOT_VERIFIED';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';

    public function message(): string
    {
        return match ($this) {
            self::RUT_NOT_FOUND => 'El RUT no existe en el sistema.',
            self::PASSWORD_INCORRECT => 'La contraseña es incorrecta.',
            self::USER_INACTIVE => 'Tu cuenta ha sido desactivada. Contacta con soporte.',
            self::EMAIL_NOT_VERIFIED => 'Debes verificar tu email antes de continuar.',
            self::RATE_LIMIT_EXCEEDED => 'Demasiados intentos fallidos. Intenta más tarde.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RUT_NOT_FOUND => 'alert-circle',
            self::PASSWORD_INCORRECT => 'lock',
            self::USER_INACTIVE => 'ban',
            self::EMAIL_NOT_VERIFIED => 'mail',
            self::RATE_LIMIT_EXCEEDED => 'clock',
        };
    }
}
