<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarreraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_carrera' => $this->id_carrera,
            'nombre' => $this->nombre,
            'jornada' => $this->jornada,
            'sede' => $this->sede,
            'modalidad' => $this->modalidad,
        ];
    }
}
