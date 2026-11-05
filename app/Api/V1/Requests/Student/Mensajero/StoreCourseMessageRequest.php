<?php

namespace App\Api\V1\Requests\Student\Mensajero;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para el envío de mensajes a un componente del curso desde la app móvil.
 */
class StoreCourseMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mensaje'             => ['required', 'string', 'min:2', 'max:2000'],
            'tema'                => ['nullable', 'string', 'max:150'],
            'id_usuario_receptor' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.required' => 'El mensaje es obligatorio.',
            'mensaje.min'      => 'El mensaje debe tener al menos 2 caracteres.',
            'mensaje.max'      => 'El mensaje no puede exceder los 2000 caracteres.',
            'tema.max'         => 'El tema no puede exceder los 150 caracteres.',
            'id_usuario_receptor.integer' => 'El identificador del docente receptor debe ser un número entero.',
        ];
    }
}
