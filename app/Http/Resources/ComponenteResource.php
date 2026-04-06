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
            'docentes' => $this->whenLoaded('docenteComponentes', fn () =>
                $this->docenteComponentes->map(fn ($dc) => [
                    'id_docente_componente' => $dc->id_docente_componente,
                    'id_docente'            => $dc->id_docente,
                    'es_titular'            => (bool) $dc->es_titular,
                    'nombre_completo'       => trim(($dc->docente?->usuario?->nombre1 ?? '') . ' ' . ($dc->docente?->usuario?->apellido1 ?? '')),
                    'nombre1'               => $dc->docente?->usuario?->nombre1,
                    'apellido1'             => $dc->docente?->usuario?->apellido1,
                    'cargo'                 => $dc->docente?->cargo,
                ])
            ),
        ];
    }
}
