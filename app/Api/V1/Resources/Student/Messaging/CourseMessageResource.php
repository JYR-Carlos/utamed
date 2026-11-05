<?php

namespace App\Api\V1\Resources\Student\Messaging;

use App\Models\Curso\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización de un mensaje de curso recién enviado o consultado.
 *
 * @mixin Mensaje
 */
class CourseMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usuarioActual = $request->user();
        $idUsuarioActual = $usuarioActual ? (int) $usuarioActual->id_usuario : 0;

        $emisor = $this->emisor;
        $receptor = $this->receptor;

        $nombreEmisor = $emisor
            ? trim($emisor->nombre1 . ' ' . ($emisor->apellido1 ?? ''))
            : null;

        $nombreReceptor = $receptor
            ? trim($receptor->nombre1 . ' ' . ($receptor->apellido1 ?? ''))
            : null;

        return [
            'id_mensaje'     => (int) $this->id_mensaje,
            'tema'           => $this->tema,
            'mensaje'        => $this->mensaje,
            'id_componente'  => (int) $this->id_componente,
            'fecha_creacion' => (string) $this->fecha_creacion,
            'emisor'         => $nombreEmisor,
            'receptor'       => $nombreReceptor,
            'es_mio'         => (int) $this->id_usuario_emisor === $idUsuarioActual,
            'es_alumno'      => (int) $this->id_usuario_emisor === $idUsuarioActual,
        ];
    }
}
