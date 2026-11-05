<?php

namespace App\Api\V1\Resources\Student\Mensajero;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización consolidada del mensajero del curso y sus componentes para la app móvil.
 *
 * @property array $resource
 */
class CourseMessagingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'curso'       => $this->resource['curso'],
            'componentes' => $this->resource['componentes'],
        ];
    }
}
