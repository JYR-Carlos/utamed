<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

/**
 * Recorta los espacios de los extremos de todos los campos de texto,
 * contraseñas incluidas.
 *
 * Laravel deja fuera `password`, `password_confirmation` y `current_password`.
 * Aquí no: al copiar y pegar una clave suele colarse un espacio al inicio o
 * al final, y el login fallaba sin que el usuario viera por qué (T54).
 *
 * Al pasar por el mismo middleware el login, el cambio de contraseña, el
 * restablecimiento y la confirmación, la regla es simétrica: la clave que se
 * guarda y la que se compara llegan recortadas igual, así que no hay
 * descalce de hash.
 */
class TrimStrings extends Middleware
{
    /**
     * Atributo de la request donde queda la contraseña tal como se escribió.
     */
    public const PASSWORD_SIN_RECORTAR = 'password_sin_recortar';

    /**
     * @var array<int, string>
     */
    protected $except = [];

    /**
     * Antes de recortar, guarda la contraseña original: las claves guardadas
     * antes de T54 pueden tener espacios en los extremos y el login necesita
     * compararlas tal cual para no dejar a esos usuarios fuera.
     */
    public function handle($request, Closure $next)
    {
        if (is_string($request->input('password'))) {
            $request->attributes->set(self::PASSWORD_SIN_RECORTAR, $request->input('password'));
        }

        return parent::handle($request, $next);
    }
}
