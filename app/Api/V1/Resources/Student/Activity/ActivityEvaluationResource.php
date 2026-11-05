<?php

namespace App\Api\V1\Resources\Student\Activity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha detallada de la evaluación: calificaciones (grupal e individual con décimas),
 * feedback docente, desglose de rúbrica evaluada y archivo entregado que fue calificado.
 *
 * @property array $resource
 */
class ActivityEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'curso' => $this->resource['curso'],
            'actividad' => $this->resource['actividad'],
            'grupo' => $this->resource['grupo'],
            'calificacion' => $this->resource['calificacion'],
            'feedback' => $this->resource['feedback'],
            'rubrica' => $this->resource['rubrica'],
            'entrega_evaluada' => $this->resource['entrega_evaluada'],
        ];
    }
}
