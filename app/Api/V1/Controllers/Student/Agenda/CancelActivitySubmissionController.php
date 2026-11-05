<?php

namespace App\Api\V1\Controllers\Student\Agenda;

use App\Models\Agenda\Actividad;
use App\Models\Agenda\Agenda;
use App\Models\Curso\Curso;
use App\Services\Student\AgendaCursoEstudiante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cancelación de la última entrega no evaluada dentro del plazo reglamentario.
 */
class CancelActivitySubmissionController
{
    public function __construct(
        protected AgendaCursoEstudiante $agendaService
    ) {}

    public function __invoke(Request $request, Curso $curso, Actividad $actividad, Agenda $agenda): JsonResponse
    {
        $usuario = $request->user();
        $estudiante = $usuario->estudiante;

        $grupo = $this->agendaService->validarPertenenciaYGrupo($curso, $actividad, $estudiante);

        $this->agendaService->cancelarEntrega(
            actividad: $actividad,
            grupo: $grupo,
            agenda: $agenda,
            usuario: $usuario
        );

        return response()->json([
            'message' => 'Entrega cancelada exitosamente.',
        ]);
    }
}
