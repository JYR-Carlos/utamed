<?php

namespace App\Http\Requests;

use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Plan;
use App\Models\Usuario\Docente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCursoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // TODO: Implement authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id_asignatura' => ['required', Rule::exists(Asignatura::class, 'id_asignatura')],
            'id_plan' => ['required', Rule::exists(Plan::class, 'id_plan')],
            'cod_curso' => 'required|integer|min:1|max:999999999|unique:curso,cod_curso',
            'nombre' => 'nullable|string|max:255',
            'fecha_inicio'        => 'nullable|date',
            'indice_grupo'        => 'nullable|integer|min:1',
            'id_docente_sugerido'               => [
                'required',
                'integer',
                Rule::exists('docente', 'id_docente'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$value) {
                        return;
                    }

                    $docenteValido = Docente::query()
                        ->where('id_docente', (int) $value)
                        ->whereHas('usuario', function ($q) {
                            $q->where('esta_activo', true);
                        })
                        ->exists();

                    if (!$docenteValido) {
                        $fail('El docente seleccionado no tiene un usuario activo valido.');
                    }
                },
            ],
            'id_tipo_componente_principal'      => 'required|integer|exists:tipo_componente,id_tipo_componente',
            'jefe_imparte_clases'               => 'nullable|boolean',
            'genera_acta'                       => 'nullable|boolean',
            'aprobacion_obligatoria'            => 'nullable|boolean',
            'porcentaje_aprobacion'             => 'nullable|numeric|min:0|max:100',
            'porcentaje_asistencia_obligatoria' => 'nullable|numeric|min:0|max:100',
            'es_colegiado'                      => 'nullable|boolean',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'id_asignatura.required' => 'La asignatura es requerida',
            'id_asignatura.exists' => 'La asignatura seleccionada no existe',
            'id_plan.required' => 'El plan es requerido',
            'id_plan.exists' => 'El plan seleccionado no existe',
            'cod_curso.required' => 'El código de curso es requerido',
            'cod_curso.integer' => 'El código de curso debe ser un número',
            'cod_curso.min'     => 'El código de curso debe ser mayor a 0',
            'cod_curso.max'     => 'El código de curso no puede superar 999.999.999',
            'cod_curso.unique'  => 'Ya existe un curso con ese código',
        ];
    }
}
