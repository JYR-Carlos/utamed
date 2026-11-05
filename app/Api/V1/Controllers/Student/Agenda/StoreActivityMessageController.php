<?php

namespace App\Api\V1\Controllers\Student\Agenda;

use App\Api\V1\Requests\Student\Agenda\StoreActivityMessageRequest;
use App\Api\V1\Resources\Student\Agenda\AgendaMessageResource;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Student\AgendaCursoEstudiante;
use Illuminate\Http\JsonResponse;

/**
 * Envío de mensaje o duda al equipo docente sobre la actividad.
 */
class StoreActivityMessageController
{
    public function __construct(
        protected AgendaCursoEstudiante $agendaService
    ) {}

    public function __invoke(StoreActivityMessageRequest $request, Curso $curso, Actividad $actividad): JsonResponse
    {
        $usuario = $request->user();
        $estudiante = $usuario->estudiante;

        $grupo = $this->agendaService->validarPertenenciaYGrupo($curso, $actividad, $estudiante);

        $agenda = $this->agendaService->enviarMensaje(
            $grupo,
            $usuario,
            $request->validated('mensaje')
        );

        $agenda->loadMissing(['usuario', 'archivo']);

        return response()->json([
            'message' => 'Mensaje enviado exitosamente.',
            'data' => (new AgendaMessageResource($agenda))->resolve($request),
        ], 201);
    }
}
