<?php

namespace App\Api\V1\Controllers\Student;

use App\Api\V1\Resources\Student\CourseDetailResource;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\DetalleCursoEstudiante;
use Illuminate\Http\Request;

/**
 * Ficha detallada del curso y componentes asignados al estudiante.
 */
class ShowCourseController
{
    public function __construct(
        private readonly DetalleCursoEstudiante $service,
    ) {}

    public function __invoke(Request $request, Curso $curso): CourseDetailResource
    {
        /** @var Usuario $user */
        $user = $request->user();
        $estudiante = $user->estudiante;

        $datos = $this->service->resolver($curso, $estudiante);

        return new CourseDetailResource($datos);
    }
}
