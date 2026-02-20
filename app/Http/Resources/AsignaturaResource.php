<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsignaturaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_asignatura' => $this->id_asignatura,
            'cod_asignatura' => $this->cod_asignatura,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'creditos_sct' => $this->creditos_sct,
            'horas_catedra' => $this->horas_catedra,
            'horas_taller' => $this->horas_taller,
            'horas_laboratorio' => $this->horas_laboratorio,
        ];
    }
}
