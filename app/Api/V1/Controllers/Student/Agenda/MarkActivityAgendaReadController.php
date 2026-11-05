<?php

namespace App\Api\V1\Controllers\Student\Agenda;

use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Student\AgendaCursoEstudiante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Confirmación explícita de lectura de los mensajes de la agenda.
 */
class MarkActivityAgendaReadController
{
    public function __construct(
        protected AgendaCursoEstudiante $agendaService
    ) {}

    public function __invoke(Request $request, Curso $curso, Actividad $actividad): JsonResponse
    {
        $usuario = $request->user();
        $estudiante = $usuario->estudiante;

        $grupo = $this->agendaService->validarPertenenciaYGrupo($curso, $actividad, $estudiante);

        $this->agendaService->marcarLectura($grupo, $usuario->id_usuario);

        $noLeidos = $this->agendaService->contarNoLeidos($grupo, $usuario->id_usuario);

        return response()->json([
            'message' => 'Agenda marcada como leída.',
            'no_leidos' => $noLeidos,
        ]);
    }
}
