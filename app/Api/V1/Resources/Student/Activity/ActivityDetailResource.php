<?php

namespace App\Api\V1\Resources\Student\Activity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha detallada de la actividad, compañeros de equipo, rúbrica asignada y archivo enunciado.
 *
 * @property array $resource
 */
class ActivityDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'actividad' => $this->resource['actividad'],
            'grupo' => $this->resource['grupo'],
            'rubrica' => $this->resource['rubrica'],
            'archivo_enunciado' => $this->resource['archivo_enunciado'],
        ];
    }
}
