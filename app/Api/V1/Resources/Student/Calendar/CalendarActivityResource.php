<?php

namespace App\Api\V1\Resources\Student\Calendar;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fecha = fn ($val) => $val ? Carbon::parse($val)->format('Y-m-d') : null;

        return [
            'id_actividad'    => (int) $this['id_actividad'],
            'id_curso'        => (int) $this['id_curso'],
            'curso'           => (string) $this['curso'],
            'asignatura'      => (string) $this['asignatura'],
            'nombre'          => (string) $this['nombre'],
            'es_sumativa'     => (bool) $this['es_sumativa'],
            'componente_tipo' => $this['componente_tipo'] !== null ? (string) $this['componente_tipo'] : null,
            'con_entrega'     => (bool) $this['con_entrega'],
            'es_grupal'       => (bool) $this['es_grupal'],
            'max_integrantes' => (int) ($this['max_integrantes'] ?? 1),
            'fecha_limite'    => $fecha($this['fecha_limite']),
            'visible'         => (bool) $this['visible'],
            'estado'          => (string) $this['estado'],
            'ya_entregada'    => (bool) $this['ya_entregada'],
            'fecha_entrega'   => $fecha($this['fecha_entrega'] ?? null),
            'ya_evaluada'     => (bool) $this['ya_evaluada'],
        ];
    }
}
