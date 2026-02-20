<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InscripcionCursoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_inscripcion_curso' => $this->id_inscripcion_curso,
            'id_curso' => $this->id_curso,
            'id_estudiante' => $this->id_estudiante,
            'cod_inscripcion_uta' => $this->cod_inscripcion_uta,
            'fecha_inscripcion' => $this->fecha_inscripcion,
            'estado_inscripcion' => $this->estado_inscripcion,
            'num_intento' => $this->num_intento,
            'promedio_parcial' => $this->promedio_parcial,
            'cuando_se_creo' => $this->cuando_se_creo,
            'cuando_se_modifico' => $this->cuando_se_modifico,
            //Relationships
            'curso' => new CursoResource($this->whenLoaded('curso')),
            'estudiante' => new EstudianteResource($this->whenLoaded('estudiante')),
        ];
    }
}
