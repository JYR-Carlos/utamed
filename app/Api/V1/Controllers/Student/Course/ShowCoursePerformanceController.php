<?php

namespace App\Api\V1\Controllers\Student\Course;

use App\Api\V1\Resources\Student\CoursePerformanceResource;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\RendimientoCursoEstudiante;
use Illuminate\Http\Request;

/**
 * Endpoint de rendimiento académico individual y asistencia del curso.
 */
class ShowCoursePerformanceController
{
    public function __construct(
        private readonly RendimientoCursoEstudiante $service,
    ) {}

    public function __invoke(Request $request, Curso $curso): CoursePerformanceResource
    {
        /** @var Usuario $user */
        $user = $request->user();
        $estudiante = $user->estudiante;

        $datos = $this->service->calcularIndividual($curso, $estudiante);

        return new CoursePerformanceResource($datos);
    }
}
