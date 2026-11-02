<?php

namespace App\Api\V1\Controllers\Student;

use App\Api\V1\Resources\Student\CourseListResource;
use App\Models\Usuario\Usuario;
use App\Services\Student\DashboardEstudiante;
use Illuminate\Http\Request;

/**
 * Catálogo de cursos inscritos del estudiante en el período académico vigente (o solicitado).
 *
 * Endpoint independiente y optimizado para la pestaña móvil "Mis Cursos".
 */
class GetCoursesController
{
    public function __construct(
        private readonly DashboardEstudiante $dashboard,
    ) {}

    public function __invoke(Request $request): CourseListResource
    {
        /** @var Usuario $user */
        $user = $request->user();
        $estudiante = $user->estudiante;

        $periodoVigente = DashboardEstudiante::periodoActual();

        $semestre = $request->filled('semestre')
            ? (int) $request->query('semestre')
            : $periodoVigente['semestre'];

        $agno = $request->filled('agno')
            ? (int) $request->query('agno')
            : $periodoVigente['agno'];

        $periodo = [
            'semestre' => $semestre,
            'agno' => $agno,
        ];

        $cursos = $this->dashboard->cursos($estudiante, $semestre, $agno);

        // Ordenamiento alfabético ascendente por nombre de asignatura de forma natural y case-insensitive
        $cursosOrdenados = $cursos->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $mensajesNoLeidos = $this->dashboard->mensajesNoLeidosPorCurso((int) $user->id_usuario);

        return new CourseListResource(
            periodo: $periodo,
            cursos: $cursosOrdenados,
            mensajesNoLeidos: $mensajesNoLeidos,
        );
    }
}
