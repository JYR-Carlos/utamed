<?php

namespace App\Http\Requests;

use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Services\CursoService;
use App\Services\IntranetService;
use App\Support\LetraGrupo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreCursoRequest extends FormRequest
{
    /**
     * Cache en memoria de los tipos de componente respaldados por Intranet para este request.
     * Null = aún no consultado; array = nombres normalizados respaldados ('CATEDRA', 'TALLER', 'LABORATORIO').
     *
     * @var array<string>|null
     */
    protected ?array $componentesCertificadasIntranet = null;
    /**
     * La autorización se maneja en el controlador vía CursoPolicy
     * (CursoController@store llama $this->authorize('create', Curso::class)),
     * igual que StoreInscripcionCursoRequest.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La validación del FormRequest corre antes de que el controlador reciba
     * la petición, así que sin esto un 422 aquí nunca aparece en los
     * Log::info de CursoController@store.
     */
    protected function failedValidation(Validator $validator): void
    {
        Log::warning('[StoreCursoRequest] Validación fallida al crear curso', [
            'errors' => $validator->errors()->toArray(),
            'data' => $this->all(),
        ]);

        parent::failedValidation($validator);
    }

    /**
     * Antes cada componente llevaba un solo docente (un id suelto); se sigue
     * aceptando así y se envuelve en lista para validar un único formato.
     */
    protected function prepareForValidation(): void
    {
        $porComponente = $this->input('docentes_por_componente');
        if (!is_array($porComponente)) {
            return;
        }

        $this->merge([
            'docentes_por_componente' => array_map(
                fn ($docentes) => is_array($docentes) ? array_values(array_filter($docentes)) : array_filter([$docentes]),
                $porComponente
            ),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id_asignatura' => ['required', Rule::exists(Asignatura::class, 'id_asignatura')],
            'id_plan' => ['required', Rule::exists(Plan::class, 'id_plan')],
            // Código interno de UTAMED: el wizard ya no lo pide y CursoService asigna
            // el siguiente correlativo. Se sigue aceptando explícito (seeders, tests).
            'cod_curso' => 'nullable|integer|min:1|max:999999999|unique:curso,cod_curso',
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
                        ->where('es_plantilla', false)
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
            'id_tipo_componente_principal'      => [
                'required',
                'integer',
                'exists:tipo_componente,id_tipo_componente',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->validarComponentePermitida($value, $fail);
                },
            ],
            'tipos_componente_ids'              => [
                'nullable',
                'array',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_array($value)) {
                        return;
                    }
                    foreach ($value as $tipoId) {
                        $this->validarComponentePermitida($tipoId, $fail);
                    }
                },
            ],
            'tipos_componente_ids.*'            => [
                'integer',
                'exists:tipo_componente,id_tipo_componente',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $this->validarComponentePermitida($value, $fail);
                },
            ],
            // id_tipo_componente => lista de id_docente (2+ en un componente = colegiado).
            'docentes_por_componente'           => 'nullable|array',
            'docentes_por_componente.*'         => 'array',
            'docentes_por_componente.*.*'       => ['integer', Rule::exists('docente', 'id_docente')],
            'genera_acta_por_componente'         => 'nullable|array',
            'genera_acta_por_componente.*'       => 'boolean',
            'cur_codigos_por_componente'         => 'nullable|array',
            'cur_codigos_por_componente.*'       => 'nullable|integer|min:1',
            'porcentaje_aprobacion_por_componente'   => 'nullable|array',
            'porcentaje_aprobacion_por_componente.*' => 'numeric|min:0|max:100',
            'porcentaje_asistencia_por_componente'   => 'nullable|array',
            'porcentaje_asistencia_por_componente.*' => 'numeric|min:0|max:100',
            'aprobacion_obligatoria_por_componente'  => 'nullable|array',
            'aprobacion_obligatoria_por_componente.*'=> 'boolean',
            'aprobacion_obligatoria'            => 'nullable|boolean',
            'porcentaje_aprobacion'             => 'nullable|numeric|min:0|max:100',
            'porcentaje_asistencia_obligatoria' => 'nullable|numeric|min:0|max:100',
            'inscribir_automaticamente'         => 'nullable|boolean',
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
            'cod_curso.integer' => 'El código de curso debe ser un número',
            'cod_curso.min'     => 'El código de curso debe ser mayor a 0',
            'cod_curso.max'     => 'El código de curso no puede superar 999.999.999',
            'cod_curso.unique'  => 'Ya existe un curso con ese código',
        ];
    }

    /**
     * Valida que un tipo de componente tenga horas > 0 en el plan de estudios (Asignatura)
     * o cuente con respaldo explícito en la oferta de Intranet (Oracle).
     */
    protected function validarComponentePermitida(mixed $idTipoComponente, \Closure $fail): void
    {
        if (empty($idTipoComponente)) {
            return;
        }

        $tipo = TipoComponente::find((int) $idTipoComponente);
        if (!$tipo) {
            return; // Manejado por regla exists
        }

        $idAsignatura = $this->input('id_asignatura');
        if (!$idAsignatura) {
            return; // Manejado por regla required
        }

        $asignatura = Asignatura::find((int) $idAsignatura);
        if (!$asignatura) {
            return; // Manejado por regla exists
        }

        $tipoNormalizado = $this->normalizarTipoComponente($tipo->tipo ?? '');

        // 1. Verificar si el plan de estudios contempla horas > 0
        $horas = match ($tipoNormalizado) {
            'CATEDRA'     => (float) ($asignatura->horas_catedra ?? 0),
            'TALLER'      => (float) ($asignatura->horas_taller ?? 0),
            'LABORATORIO' => (float) ($asignatura->horas_laboratorio ?? 0),
            default       => 0.0,
        };

        if ($horas > 0) {
            return; // Válido por plan de estudios
        }

        // 2. Si horas <= 0, verificar si la oferta de Intranet lo certifica explícitamente
        if ($this->componentesCertificadasIntranet === null) {
            $this->componentesCertificadasIntranet = $this->obtenerComponentesCertificadasIntranet();
        }

        if (in_array($tipoNormalizado, $this->componentesCertificadasIntranet, true)) {
            return; // Válido por oferta de Intranet
        }

        $fail("La componente '{$tipo->tipo}' no tiene horas asignadas en el plan de estudios ni cuenta con respaldo en la oferta de Intranet.");
    }

    /**
     * Consulta la oferta oficial de Intranet para certificar qué componentes existen en Oracle.
     *
     * @return array<string>
     */
    protected function obtenerComponentesCertificadasIntranet(): array
    {
        $idAsignatura = (int) $this->input('id_asignatura');
        $idPlan = (int) $this->input('id_plan');
        $agnoReal = $this->input('agno_real');
        $semestreReal = (int) ($this->input('semestre_real') ?? 2);

        if (!$idAsignatura || !$idPlan || !$agnoReal) {
            return [];
        }

        $letraGrupo = '';
        $indiceGrupo = $this->input('indice_grupo');
        if (!empty($indiceGrupo)) {
            $letraGrupo = LetraGrupo::fromIndice((int) $indiceGrupo);
        } else {
            $asignacionPlan = AsignacionPlan::where('id_asignatura', $idAsignatura)
                ->where('id_plan', $idPlan)
                ->whereNull('fecha_eliminacion')
                ->first();
            if ($asignacionPlan) {
                $nextIndice = app(CursoService::class)->nextIndiceGrupo(
                    $asignacionPlan->id_asignacion_plan,
                    (int) $agnoReal,
                    $semestreReal
                );
                $letraGrupo = LetraGrupo::fromIndice($nextIndice);
            }
        }

        try {
            /** @var IntranetService $intranetService */
            $intranetService = app(IntranetService::class);
            $oferta = $intranetService->resolverComponentesDesdeParametros(
                $idAsignatura,
                $idPlan,
                (int) $agnoReal,
                $semestreReal,
                $letraGrupo
            );

            $certificados = [];
            foreach ($oferta as $compData) {
                $codigo = $compData->curso_tipo_asig->value; // 'C', 'T', 'L'
                match ($codigo) {
                    'C' => $certificados['CATEDRA'] = true,
                    'T' => $certificados['TALLER'] = true,
                    'L' => $certificados['LABORATORIO'] = true,
                    default => null,
                };
            }
            return array_keys($certificados);
        } catch (\Throwable $e) {
            Log::warning('[StoreCursoRequest] Error al consultar oferta en Intranet para validar componentes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Normaliza el nombre del tipo de componente a mayúsculas sin tildes.
     */
    protected function normalizarTipoComponente(string $nombre): string
    {
        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú'],
            ['A', 'E', 'I', 'O', 'U'],
            mb_strtoupper(trim($nombre))
        );
    }
}
