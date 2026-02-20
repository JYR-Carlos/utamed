<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsignacionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_asignacion_plan' => $this->id_asignacion_plan,
            'id_plan' => $this->id_plan,
            'id_asignatura' => $this->id_asignatura,
            'agno_planificado' => $this->agno_planificado,
            'semestre_planificado' => $this->semestre_planificado,
            'tipo_ramo' => $this->tipo_ramo,
            'asignatura' => new AsignaturaResource($this->whenLoaded('asignatura')),
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
