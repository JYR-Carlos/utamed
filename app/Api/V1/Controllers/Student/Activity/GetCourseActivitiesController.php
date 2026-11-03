<?php

namespace App\Api\V1\Controllers\Student\Activity;

use App\Api\V1\Resources\Student\Activity\CourseActivityListResource;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\ActividadesCursoEstudiante;
use Illuminate\Http\Request;

/**
 * Endpoint para listar las actividades evaluativas y formativas del curso para el estudiante.
 */
class GetCourseActivitiesController
{
    public function __construct(
        private readonly ActividadesCursoEstudiante $service,
    ) {}

    public function __invoke(Request $request, Curso $curso): CourseActivityListResource
    {
        /** @var Usuario $user */
        $user = $request->user();
        $estudiante = $user->estudiante;

        $datos = $this->service->listarActividades($curso, $estudiante);

        return new CourseActivityListResource($datos);
    }
}
