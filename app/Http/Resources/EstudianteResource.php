<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstudianteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_estudiante' => $this->id_estudiante,
            'id_usuario' => $this->id_usuario,
            'rut' => $this->rut,
            'nombre_completo' => $this->nombre_completo,
            'agno_ingreso' => $this->agno_ingreso,
            'id_carrera' => $this->id_carrera,
            'id_contexto' => $this->id_contexto,
            'usuario' => new UsuarioResource($this->whenLoaded('usuario')),
            'carrera' => new CarreraResource($this->whenLoaded('carrera')),
        ];
    }
}
