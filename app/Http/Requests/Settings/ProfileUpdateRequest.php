<?php

namespace App\Http\Requests\Settings;

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'nombre1' => ['sometimes', 'string', 'max:255'],
            'nombre2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'apellido1' => ['sometimes', 'string', 'max:255'],
            'apellido2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(Usuario::class)->ignore($this->user()->id_usuario, 'id_usuario'),
            ],
        ];
    }

    /**
     * Mensajes en español a mano: el proyecto no trae `lang/es` (mismo criterio
     * que PasswordController::update). Las claves exactas van antes que el
     * comodín de su campo: Laravel se queda con la primera que coincide.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'name.*' => 'Ingresa tu nombre.',
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.max' => 'El correo no puede superar los 255 caracteres.',
            'email.unique' => 'Ese correo ya está registrado en otra cuenta.',
            'email.*' => 'Ingresa un correo electrónico válido, en minúsculas.',
        ];
    }
}
