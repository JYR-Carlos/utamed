<?php

namespace App\Http\Requests\Settings;

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // Nombre completo en un solo campo (la pantalla de ajustes): se
            // reparte en las cuatro columnas con Usuario::separarNombreCompleto().
            'name' => ['sometimes', 'string', 'max:255', 'regex:/\S\s+\S/u'],
            // Las columnas son varchar(30).
            'nombre1' => ['sometimes', 'string', 'max:30'],
            'nombre2' => ['sometimes', 'nullable', 'string', 'max:30'],
            'apellido1' => ['sometimes', 'string', 'max:30'],
            'apellido2' => ['sometimes', 'nullable', 'string', 'max:30'],
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
            'name.regex' => 'Escribe tu nombre y al menos un apellido.',
            'name.*' => 'Ingresa tu nombre.',
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.max' => 'El correo no puede superar los 255 caracteres.',
            'email.unique' => 'Ese correo ya está registrado en otra cuenta.',
            'email.*' => 'Ingresa un correo electrónico válido, en minúsculas.',
        ];
    }

    /**
     * Cada trozo del nombre completo tiene que caber en su columna: sin esto,
     * un segundo nombre largo llegaba a Postgres y fallaba al guardar.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('name') || ! is_string($this->input('name'))) {
                    return;
                }

                $partes = $this->user()->separarNombreCompleto($this->input('name'));

                if (collect($partes)->contains(fn (?string $parte) => mb_strlen((string) $parte) > 30)) {
                    $validator->errors()->add('name', 'Cada nombre y apellido puede tener hasta 30 caracteres.');
                }
            },
        ];
    }

    /**
     * Lo que se escribe en el usuario. El nombre completo del formulario se
     * reparte en sus cuatro columnas; si llegan las columnas sueltas, mandan ellas.
     *
     * @return array<string, mixed>
     */
    public function datosUsuario(): array
    {
        $datos = $this->validated();

        if (isset($datos['name']) && ! isset($datos['nombre1'])) {
            $datos = [...$datos, ...$this->user()->separarNombreCompleto($datos['name'])];
        }
        unset($datos['name']);

        return $datos;
    }
}
