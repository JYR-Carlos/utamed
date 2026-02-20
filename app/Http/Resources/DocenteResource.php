<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocenteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_docente' => $this->id_docente,
            'nombre1' => $this->nombre1,
            'nombre2' => $this->nombre2,
            'apellido1' => $this->apellido1,
            'apellido2' => $this->apellido2,
            'nombre_completo' => $this->nombre_completo,
            'email' => $this->email,
            'grado' => $this->grado,
            'titulo' => $this->titulo,
            'cargo' => $this->cargo,
        ];
    }
}
