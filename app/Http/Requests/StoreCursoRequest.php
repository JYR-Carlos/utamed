<?php

namespace App\Http\Requests;

use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
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
            'agno_real'           => 'nullable|integer|min:2000|max:2100',
            'semestre_real'       => 'nullable|integer|in:1,2',
            'indice_grupo'        => [
                'nullable',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$value) {
                        return;
                    }

                    $asignacionPlan = AsignacionPlan::where('id_asignatura', $this->input('id_asignatura'))
                        ->where('id_plan', $this->input('id_plan'))
                        ->whereNull('fecha_eliminacion')
                        ->first();

                    if (!$asignacionPlan) {
                        return; // otra regla ya falla por asignatura/plan inválidos
                    }

                    $agnoReal = $this->input('agno_real');
                    $semestreReal = $this->input('semestre_real');

                    $query = Curso::where('id_asignacion_plan', $asignacionPlan->id_asignacion_plan)
                        ->where('indice_grupo', (int) $value)
                        ->whereNull('fecha_eliminacion');

                    $agnoReal === null ? $query->whereNull('agno_real') : $query->where('agno_real', $agnoReal);
                    $semestreReal === null ? $query->whereNull('semestre_real') : $query->where('semestre_real', $semestreReal);

                    if ($query->exists()) {
                        $fail('Ya existe un curso con esa letra de grupo para el mismo periodo.');
                    }
                },
            ],
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
            'tipos_componente_ids'              => 'nullable|array|min:1',
            'tipos_componente_ids.*'            => 'integer|exists:tipo_componente,id_tipo_componente',
            'docentes_por_componente'           => 'nullable|array',
            'docentes_por_componente.*'         => ['nullable', 'integer', Rule::exists('docente', 'id_docente')],
            'genera_acta_por_componente'         => 'nullable|array',
            'genera_acta_por_componente.*'       => 'boolean',
            'aprobacion_obligatoria'            => 'nullable|boolean',
            'porcentaje_aprobacion'             => 'nullable|numeric|min:0|max:100',
            'porcentaje_asistencia_obligatoria' => 'nullable|numeric|min:0|max:100',
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
