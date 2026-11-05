<?php

namespace App\Api\V1\Resources\Student\Calendar;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarResource extends JsonResource
{
    /**
     * @param  array{semestre: ?int, agno: ?int, es_historico: bool}  $periodo
     * @param  bool  $agrupado
     * @param  array<int, array<string, mixed>>  $actividades
     * @param  array<int, array<string, mixed>>  $otrasActividades
     */
    public function __construct(
        private readonly array $periodo,
        private readonly bool $agrupado,
        private readonly array $actividades,
        private readonly array $otrasActividades = [],
    ) {
        parent::__construct([
            'periodo' => $this->periodo,
            'agrupado' => $this->agrupado,
            'actividades' => $this->actividades,
            'otras_actividades' => $this->otrasActividades,
        ]);
    }

    public function toArray(Request $request): array
    {
        return [
            'periodo' => $this->periodo,
            'agrupado' => $this->agrupado,
            'actividades' => CalendarActivityResource::collection($this->actividades),
            'otras_actividades' => CalendarActivityResource::collection($this->otrasActividades),
        ];
    }
}
