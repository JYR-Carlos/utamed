<?php

namespace App\Api\V1\Resources\Student\Agenda;

use App\Models\Agenda\Agenda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialización de mensaje individual o entrega enviada.
 *
 * @property Agenda $resource
 */
class AgendaMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userActual = $request->user();
        $emisor = $this->resource->usuario;
        $esUsuarioActual = $userActual && (int) $this->resource->id_usuario_emisor === (int) $userActual->id_usuario;

        $rol = 'estudiante';
        if ($emisor) {
            if ($emisor->docente !== null) {
                $rol = 'docente';
            } elseif ($emisor->hasAnyRole(['Docente', 'Docente Titular', 'Docente Componente', 'Docente Componente Colegiado'])) {
                $rol = 'docente';
            }
        }

        $archivoData = null;
        if ($this->resource->uuid_archivo_subido && $this->resource->archivo) {
            $archivo = $this->resource->archivo;
            $cursoId = $request->route('curso') instanceof \App\Models\Curso\Curso
                ? $request->route('curso')->id_curso
                : $request->route('curso');
            $actividadId = $request->route('actividad') instanceof \App\Models\Agenda\Actividad
                ? $request->route('actividad')->id_actividad
                : $request->route('actividad');

            $archivoData = [
                'uuid_archivo' => $archivo->uuid_archivo,
                'nombre_original' => $archivo->nombre_original,
                'peso_bytes' => (int) $archivo->peso_bytes,
                'extension' => $archivo->extension,
                'url_descarga' => route('api.v1.estudiante.cursos.actividades.entregas.descargar', [
                    'curso' => $cursoId,
                    'actividad' => $actividadId,
                    'agenda' => $this->resource->id_agenda,
                ]),
            ];
        }

        $tipo = $this->resource->tipo_mensaje instanceof \BackedEnum
            ? $this->resource->tipo_mensaje->value
            : (string) $this->resource->tipo_mensaje;

        $fechaEnvio = $this->resource->fecha_envio instanceof \Carbon\Carbon
            ? $this->resource->fecha_envio->toIso8601String()
            : ($this->resource->fecha_envio ? \Carbon\Carbon::parse($this->resource->fecha_envio)->toIso8601String() : null);

        $data = [
            'id_agenda' => (int) $this->resource->id_agenda,
            'tipo_mensaje' => $tipo,
            'mensaje' => $this->resource->mensaje,
            'fecha_envio' => $fechaEnvio,
            'emisor' => [
                'id_usuario' => (int) $this->resource->id_usuario_emisor,
                'nombre' => $emisor ? $emisor->nombre_completo : '',
                'es_usuario_actual' => $esUsuarioActual,
                'rol' => $rol,
            ],
        ];

        if ($archivoData !== null) {
            $data['archivo'] = $archivoData;
        }

        return $data;
    }
}
