<?php

namespace App\Api\V1\Requests\Auth;

use App\Models\Usuario\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Cambio de clave en la app móvil: solo cuando la política lo exige
 * (primer ingreso o clave vencida). No hay cambio voluntario en la app.
 */
class ChangeExpiredPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Usuario $user */
        $user = $this->user();

        return $user->debeCambiarPassword();
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Tu contraseña está vigente; el cambio desde la app solo está disponible cuando vence o en el primer ingreso.');
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.*' => 'La nueva contraseña debe tener al menos 8 caracteres, con una mayúscula, números y algún signo.',
        ];
    }
}
