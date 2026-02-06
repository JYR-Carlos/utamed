<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInscripcionCursoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // La autorización se maneja en el controlador usando la política
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'cod_inscripcion_uta' => [
                'nullable',
                'string',
                'max:255'
            ],
            'fecha_inscripcion' => [
                'nullable',
                'date'
            ],
            'estado_inscripcion' => [
                'nullable',
                'string',
                'in:INSCRITO,RETIRADO,SUSPENDIDO,APROBADO,REPROBADO'
            ],
            'num_intento' => [
                'nullable',
                'integer',
                'min:1'
            ],
            'promedio_parcial' => [
                'nullable',
                'numeric',
                'min:0',
                'max:7'
            ],
        ];
    }

    /**
     * Obtiene los mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_inscripcion.date' => 'La fecha de inscripción debe ser una fecha válida.',
            'estado_inscripcion.in' => 'El estado de inscripción debe ser uno de: INSCRITO, RETIRADO, SUSPENDIDO, APROBADO, REPROBADO.',
            'num_intento.min' => 'El número de intento debe ser mayor a 0.',
            'promedio_parcial.min' => 'El promedio parcial no puede ser menor a 0.',
            'promedio_parcial.max' => 'El promedio parcial no puede ser mayor a 7.',
            'promedio_parcial.numeric' => 'El promedio parcial debe ser un número válido.',
        ];
    }
}
