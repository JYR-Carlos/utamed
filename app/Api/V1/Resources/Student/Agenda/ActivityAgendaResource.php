<?php

namespace App\Api\V1\Resources\Student\Agenda;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización del hilo completo de interacciones, estado y entregas de la actividad.
 *
 * @property array $resource
 */
class ActivityAgendaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'curso' => $this->resource['curso'],
            'actividad' => $this->resource['actividad'],
            'grupo' => $this->resource['grupo'],
            'resumen_entregas' => $this->resource['resumen_entregas'],
            'interacciones' => $this->resource['interacciones'],
        ];
    }
}
