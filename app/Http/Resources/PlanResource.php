<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_plan' => $this->id_plan,
            'id_carrera' => $this->id_carrera,
            'agno' => $this->agno,
            'version' => $this->version,
            'creditos_sct_totales' => $this->creditos_sct_totales,
            'carrera' => new CarreraResource($this->whenLoaded('carrera')),
        ];
    }
}
