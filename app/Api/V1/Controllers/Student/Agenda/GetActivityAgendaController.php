<?php

namespace App\Api\V1\Controllers\Student\Agenda;

use App\Api\V1\Resources\Student\Agenda\ActivityAgendaResource;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Student\AgendaCursoEstudiante;
use Illuminate\Http\Request;

/**
 * Consulta del hilo cronológico de la agenda de la actividad con lectura automática integrada.
 */
class GetActivityAgendaController
{
    public function __construct(
        protected AgendaCursoEstudiante $agendaService
    ) {}

    public function __invoke(Request $request, Curso $curso, Actividad $actividad): ActivityAgendaResource
    {
        $usuario = $request->user();
        $estudiante = $usuario->estudiante;
        $marcarLeido = $request->boolean('marcar_leido', true);

        $datos = $this->agendaService->obtenerAgenda(
            $curso,
            $actividad,
            $estudiante,
            $usuario,
            $marcarLeido
        );

        return new ActivityAgendaResource($datos);
    }
}
