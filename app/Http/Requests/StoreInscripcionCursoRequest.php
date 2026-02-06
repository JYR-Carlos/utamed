<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInscripcionCursoRequest extends FormRequest
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
            'id_curso' => [
                'required',
                'integer',
                'exists:Curso,id_curso'
            ],
            'id_estudiante' => [
                'required',
                'integer',
                'exists:Estudiante,id_estudiante',
                // Evitar duplicados: no puede haber dos inscripciones del mismo estudiante en el mismo curso
                Rule::unique('Inscripcion_Curso', 'id_estudiante')
                    ->where('id_curso', $this->input('id_curso'))
            ],
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
            'id_curso.required' => 'El curso es requerido.',
            'id_curso.exists' => 'El curso especificado no existe.',
            'id_estudiante.required' => 'El estudiante es requerido.',
            'id_estudiante.exists' => 'El estudiante especificado no existe.',
            'id_estudiante.unique' => 'Este estudiante ya está inscrito en este curso.',
            'fecha_inscripcion.date' => 'La fecha de inscripción debe ser una fecha válida.',
            'estado_inscripcion.in' => 'El estado de inscripción debe ser uno de: INSCRITO, RETIRADO, SUSPENDIDO, APROBADO, REPROBADO.',
            'num_intento.min' => 'El número de intento debe ser mayor a 0.',
        ];
    }
}
