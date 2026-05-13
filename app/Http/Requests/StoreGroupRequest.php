<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre_grupo' => 'nullable|string|max:100',
            'estudiantes' => 'required|array|min:1',
            'estudiantes.*' => 'required|integer|exists:usuario.estudiante,id_estudiante',
        ];
    }

    public function messages(): array
    {
        return [
            'estudiantes.required' => 'Debe seleccionar al menos un estudiante.',
            'estudiantes.array' => 'Los estudiantes deben ser una lista.',
            'estudiantes.min' => 'Debe seleccionar al menos un estudiante.',
            'estudiantes.*.exists' => 'Uno o más estudiantes no existen en el sistema.',
            'estudiantes.*.integer' => 'Los IDs de estudiantes deben ser números enteros.',
        ];
    }
}
