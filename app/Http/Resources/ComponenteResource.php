<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComponenteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_componente'                    => $this->id_componente,
            'id_curso'                         => $this->id_curso,
            'id_tipo_componente'               => $this->id_tipo_componente,
            'genera_acta'                      => $this->genera_acta,
            'porcentaje_aprobacion'            => $this->porcentaje_aprobacion,
            'aprobacion_obligatoria'           => $this->aprobacion_obligatoria,
            'porcentaje_asistencia_obligatoria' => $this->porcentaje_asistencia_obligatoria,
            'tipo_componente' => $this->whenLoaded('tipoComponente', fn () => [
                'id_tipo_componente' => $this->tipoComponente->id_tipo_componente,
                'tipo'               => $this->tipoComponente->tipo,
            ]),
            'docentes' => $this->whenLoaded('docentesAsignados', fn () =>
                $this->docentesAsignados->map(fn ($docente) => [
                    'id_docente'      => $docente->id_docente,
                    'nombre_completo' => trim(($docente->usuario?->nombre1 ?? '') . ' ' . ($docente->usuario?->apellido1 ?? '')),
                    'nombre1'         => $docente->usuario?->nombre1,
                    'apellido1'       => $docente->usuario?->apellido1,
                    'cargo'           => $docente->cargo,
                ])
            ),
        ];
    }
}
