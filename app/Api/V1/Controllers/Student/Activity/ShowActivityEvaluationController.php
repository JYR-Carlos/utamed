<?php

namespace App\Api\V1\Controllers\Student\Activity;

use App\Api\V1\Resources\Student\Activity\ActivityEvaluationResource;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Services\Student\ActividadesCursoEstudiante;
use Illuminate\Http\Request;

/**
 * Consulta las calificaciones (nota grupal e individual con décimas),
 * desglose de rúbrica evaluada y feedback docente de una actividad.
 */
class ShowActivityEvaluationController
{
    public function __construct(
        protected ActividadesCursoEstudiante $actividadesService
    ) {}

    public function __invoke(Request $request, Curso $curso, Actividad $actividad): ActivityEvaluationResource
    {
        $estudiante = $request->user()->estudiante;

        $datos = $this->actividadesService->obtenerEvaluacion($curso, $actividad, $estudiante);

        return new ActivityEvaluationResource($datos);
    }
}
