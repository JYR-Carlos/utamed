<?php

namespace App\Api\V1\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha detallada del curso, asignatura, componentes y unidades.
 *
 * @property array $resource
 */
class CourseDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'curso' => $this->resource['curso'],
            'asignatura' => $this->resource['asignatura'],
            'componentes' => $this->resource['componentes'],
            'unidades' => $this->resource['unidades'],
        ];
    }
}
