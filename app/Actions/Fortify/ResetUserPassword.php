<?php

namespace App\Actions\Fortify;

use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(Usuario $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // `cambiarPassword()`: la tabla guarda el hash en `passhash` (no hay
        // columna `password`) y además deja registrada la fecha del cambio.
        $user->cambiarPassword($input['password']);
    }
}
