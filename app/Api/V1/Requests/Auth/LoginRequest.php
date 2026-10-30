<?php

namespace App\Api\V1\Requests\Auth;

use App\Support\Rut;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Login móvil: exclusivamente RUT y contraseña (cualquier `email` se ignora).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['rut' => Rut::normalizar($this->input('rut'))]);
    }

    public function rules(): array
    {
        return [
            'rut' => ['required', 'string', fn ($attr, $value, $fail) => Rut::esValido($value) ?: $fail('El RUT no es válido.')],
            'password' => ['required', 'string'],
        ];
    }
}
