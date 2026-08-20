<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CursoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Cargar programa actual del curso si existe
        $programa = $this->programas()
            ->where('es_actual', true)
            ->first();

        // Docente del componente Cátedra (para mostrar en tabla)
        $componenteCatedra = $this->relationLoaded('componentes')
            ? $this->componentes->first(fn($c) => optional($c?->tipoComponente)->tipo === 'Cátedra')
            : null;
        $docenteTitular = $this->relationLoaded('docenteTitular') ? $this->docenteTitular : null;
        $docenteUser = $docenteTitular?->usuario;

        return [
            'id_curso' => $this->id_curso,
            'cod_curso' => $this->cod_curso,
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'indice_grupo' => $this->indice_grupo,
            'letra_grupo' => $this->letra_grupo,
            'estado_interno' => $this->estado_interno,
            'estado_acta' => $this->estado_acta,
            'es_plantilla' => $this->es_plantilla,
            'id_asignacion_plan' => $this->id_asignacion_plan,
            'id_contexto' => $this->id_contexto,
            'id_docente_titular' => $this->id_docente_titular,
            'id_curso_padre' => $this->id_curso_padre,
            'version_plantilla' => $this->version_plantilla,
            'fecha_creacion' => $this->fecha_creacion,
            'fecha_modificacion' => $this->fecha_modificacion,
            
            // Computed properties for table display
            'asignatura_nombre'    => $this->asignacionPlan?->asignatura?->nombre,
            'carrera_nombre'       => $this->asignacionPlan?->plan?->carrera?->nombre,
            'id_carrera'           => $this->asignacionPlan?->plan?->carrera?->id_carrera,
            'numero_semestre'      => $this->asignacionPlan?->semestre_planificado,
            'docente_nombre'       => $docenteUser
                ? trim(($docenteUser->nombre1 ?? '') . ' ' . ($docenteUser->apellido1 ?? ''))
                : null,
            'docente_email'        => $docenteUser?->email,
            'docente_cargo'        => $docenteTitular?->cargo,
            // Asignatura details for quick-view modal
            'cod_asignatura'       => $this->asignacionPlan?->asignatura?->cod_asignatura,
            'creditos_sct'         => $this->asignacionPlan?->asignatura?->creditos_sct,
            'horas_catedra'        => $this->asignacionPlan?->asignatura?->horas_catedra,
            'horas_taller'         => $this->asignacionPlan?->asignatura?->horas_taller,
            'horas_laboratorio'    => $this->asignacionPlan?->asignatura?->horas_laboratorio,
            'horas_dirigidas'      => $this->asignacionPlan?->asignatura?->horas_dirigidas,
            'horas_autonomas'      => $this->asignacionPlan?->asignatura?->horas_autonomas,
            
            // Programa information
            'has_programa' => $programa !== null,
            'programa_estado' => $programa?->estado,
            'id_programa' => $programa?->id_programa,
            
            // Fechas límite de entrega del programa
            'fecha_limite_entrega_basico'   => $this->fecha_limite_entrega_basico?->toIso8601String(),
            'fecha_limite_entrega_syllabus' => $this->fecha_limite_entrega_syllabus?->toIso8601String(),
            
            // Relationships
            'asignacionPlan' => new AsignacionPlanResource($this->whenLoaded('asignacionPlan')),
            'componentes' => ComponenteResource::collection($this->whenLoaded('componentes')),
            'inscripcionCursos' => InscripcionCursoResource::collection($this->whenLoaded('inscripcionCursos')),
        ];
    }
}
