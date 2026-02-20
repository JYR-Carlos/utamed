<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeccionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_seccion' => $this->id_seccion,
            'id_curso' => $this->id_curso,
            'id_tipo_seccion' => $this->id_tipo_seccion,
            'id_docente' => $this->id_docente,
            'es_plantilla' => $this->es_plantilla,
            'tipo_seccion' => new TipoSeccionResource($this->whenLoaded('tipoSeccion')),
            'docente' => new DocenteResource($this->whenLoaded('docente')),
        ];
    }
}
