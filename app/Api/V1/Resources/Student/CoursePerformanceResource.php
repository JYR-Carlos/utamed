<?php

namespace App\Api\V1\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumen de rendimiento académico individual y asistencia del curso.
 *
 * @property array $resource
 */
class CoursePerformanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'curso' => $this->resource['curso'],
            'mi_promedio' => $this->resource['mi_promedio'],
            'asistencia_minima' => $this->resource['asistencia_minima'],
            'asistencia_global' => $this->resource['asistencia_global'],
            'totales' => $this->resource['totales'],
            'componentes' => $this->resource['componentes'],
        ];
    }
}
