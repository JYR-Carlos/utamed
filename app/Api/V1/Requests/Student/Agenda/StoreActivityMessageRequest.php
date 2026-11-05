<?php

namespace App\Api\V1\Requests\Student\Agenda;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de envío de mensaje al docente desde la agenda de actividad.
 */
class StoreActivityMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mensaje' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'mensaje.required' => 'El mensaje es obligatorio.',
            'mensaje.min' => 'El mensaje debe tener al menos 2 caracteres.',
            'mensaje.max' => 'El mensaje no puede exceder los 5000 caracteres.',
        ];
    }
}
