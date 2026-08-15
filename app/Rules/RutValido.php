<?php

namespace App\Rules;

use App\Support\Rut;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El valor tiene forma de RUT: 7 u 8 dígitos más su dígito verificador.
 *
 * Acepta cualquier escritura (con puntos, sin ellos, K en minúscula) porque quien
 * valida ya normalizó el dato con {@see Rut::normalizar()}; lo que esta regla
 * corta es lo que no es un RUT — y que, guardado como texto libre, terminaría
 * duplicando a la persona.
 *
 * No comprueba el dígito verificador: los RUT que llegan de la intranet traen el
 * suyo desde el sistema de origen y rechazarlos aquí dejaría alumnos sin crear.
 */
class RutValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Rut::esValido(is_scalar($value) ? $value : null)) {
            $fail('El :attribute debe tener 7 u 8 dígitos y su dígito verificador (por ejemplo, 12345678-9).');
        }
    }
}
