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
            'promedio_parcial' => $this->promedio_parcial,
            'estado_inscripcion' => $this->estado_inscripcion,
        ];
    }
}
