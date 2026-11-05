<?php

namespace App\Api\V1\Controllers\Student\Agenda;

use App\Api\V1\Requests\Student\Agenda\StoreActivitySubmissionRequest;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Student\AgendaCursoEstudiante;
use Illuminate\Http\JsonResponse;

/**
 * Subida de archivo de entrega de actividad con validación de plazos y concurrencia.
 */
class StoreActivitySubmissionController
{
    public function __construct(
        protected AgendaCursoEstudiante $agendaService
    ) {}

    public function __invoke(StoreActivitySubmissionRequest $request, Curso $curso, Actividad $actividad): JsonResponse
    {
        $usuario = $request->user();
        $estudiante = $usuario->estudiante;

        $grupo = $this->agendaService->validarPertenenciaYGrupo($curso, $actividad, $estudiante);

        $agenda = $this->agendaService->subirEntrega(
            actividad: $actividad,
            grupo: $grupo,
            usuario: $usuario,
            archivo: $request->getFile(),
            mensaje: $request->getSubmissionMessage(),
            nombreArchivo: $request->getFileName()
        );
        // sube entrega y ---
        // carga datos de mensaje exitoso 
        $agenda->loadMissing('archivo');
        $archivo = $agenda->archivo;

        $fechaEnvio = $agenda->fecha_envio instanceof \Carbon\Carbon
            ? $agenda->fecha_envio->toIso8601String()
            : ($agenda->fecha_envio ? \Carbon\Carbon::parse($agenda->fecha_envio)->toIso8601String() : null);

        return response()->json([
            'message' => 'Entrega subida exitosamente.',
            'data' => [
                'id_agenda' => (int) $agenda->id_agenda,
                'tipo_mensaje' => $agenda->tipo_mensaje instanceof \BackedEnum ? $agenda->tipo_mensaje->value : (string) $agenda->tipo_mensaje,
                'fecha_envio' => $fechaEnvio,
                'archivo' => $archivo ? [
                    'uuid_archivo' => $archivo->uuid_archivo,
                    'nombre_original' => $archivo->nombre_original,
                    'peso_bytes' => (int) $archivo->peso_bytes,
                    'extension' => $archivo->extension,
                    'url_descarga' => route('api.v1.estudiante.cursos.actividades.entregas.descargar', [
                        'curso' => $curso->id_curso,
                        'actividad' => $actividad->id_actividad,
                        'agenda' => $agenda->id_agenda,
                    ]),
                ] : null,
            ],
        ], 201);
    }
}
