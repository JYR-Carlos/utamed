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
            'id_curso_padre' => $this->id_curso_padre,
            'version_plantilla' => $this->version_plantilla,
            'fecha_creacion' => $this->fecha_creacion,
            'fecha_modificacion' => $this->fecha_modificacion,
            
            // Computed properties for table display
            'asignatura_nombre' => $this->asignacionPlan?->asignatura?->nombre,
            'carrera_nombre' => $this->asignacionPlan?->plan?->carrera?->nombre,
            'numero_semestre' => $this->asignacionPlan?->semestre_planificado,
            'docente_nombre' => null, // Docentes are in secciones, not cursos
            
            // Relationships
            'asignacionPlan' => new AsignacionPlanResource($this->whenLoaded('asignacionPlan')),
            'secciones' => SeccionResource::collection($this->whenLoaded('secciones')),
            'inscripcionCursos' => InscripcionCursoResource::collection($this->whenLoaded('inscripcionCursos')),
        ];
    }
}
