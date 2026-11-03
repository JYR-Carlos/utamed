<?php

namespace App\Api\V1\Resources\Student\Activity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Catálogo de actividades de un curso para el estudiante.
 *
 * @property array $resource
 */
class CourseActivityListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'curso' => $this->resource['curso'],
            'totales' => $this->resource['totales'],
            'actividades' => $this->resource['actividades'],
        ];
    }
}
