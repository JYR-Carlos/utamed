<?php

namespace App\Api\V1\Controllers\Student\Activity;

use App\Api\V1\Resources\Student\Activity\ActivityDetailResource;
use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\ActividadesCursoEstudiante;
use Illuminate\Http\Request;

/**
 * Endpoint para consultar la ficha detallada de una actividad, rúbrica asignada y grupo.
 */
class ShowActivityController
{
    public function __construct(
        private readonly ActividadesCursoEstudiante $service,
    ) {}

    public function __invoke(Request $request, Curso $curso, Actividad $actividad): ActivityDetailResource
    {
        /** @var Usuario $user */
        $user = $request->user();
        $estudiante = $user->estudiante;

        $datos = $this->service->obtenerDetalle($curso, $actividad, $estudiante);

        return new ActivityDetailResource($datos);
    }
}
