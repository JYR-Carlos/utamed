<?php

namespace App\Api\V1\Controllers\Student\Calendar;

use App\Api\V1\Middleware\IsEnrolledInCourse;
use App\Api\V1\Requests\Student\Calendar\GetAcademicCalendarRequest;
use App\Api\V1\Resources\Student\Calendar\CalendarResource;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Student\DashboardEstudiante;
use App\Services\Student\ResumenActividadesEstudiante;

/**
 * Calendario académico de entregas y actividades evaluativas para la app móvil.
 *
 * Soporta 4 modos de consulta:
 * 1. Historial completo (periodo=todos).
 * 2. Período actual o definido sin agrupar.
 * 3. Curso aislado sin agrupar (id_curso).
 * 4. Curso agrupado con actividades concurrentes (id_curso e incluir_otros=true).
 */
class GetAcademicCalendarController
{
    public function __construct(
        private readonly ResumenActividadesEstudiante $resumen,
    ) {}

    public function __invoke(GetAcademicCalendarRequest $request): CalendarResource
    {
        /** @var Usuario $user */
        $user = $request->user();
        $estudiante = $user->estudiante;

        $periodoActual = DashboardEstudiante::periodoActual();

        // Si se provee id_curso, se aplican los guardrails IDOR
        if ($request->filled('id_curso')) {
            $idCurso = $request->integer('id_curso');
            $curso = Curso::find($idCurso);

            if (! $curso) {
                abort(404, 'Curso no encontrado.');
            }

            if (! IsEnrolledInCourse::estaInscritoEnCurso($user, $curso)) {
                abort(403, IsEnrolledInCourse::NO_INSCRITO_MENSAJE);
            }

            $semestre = (int) $curso->semestre_real;
            $agno = (int) $curso->agno_real;
            $esHistorico = ($semestre !== $periodoActual['semestre'] || $agno !== $periodoActual['agno']);

            $periodo = [
                'semestre' => $semestre,
                'agno' => $agno,
                'es_historico' => $esHistorico,
            ];

            // Modo 4: Curso agrupado con concurrencia de otros cursos
            if ($request->boolean('incluir_otros')) {
                $todas = $this->resumen->calendarioDelPeriodo($estudiante, $semestre, $agno);

                $actividades = array_values(array_filter($todas, fn ($a) => (int) $a['id_curso'] === $idCurso));
                $otrasActividades = array_values(array_filter($todas, fn ($a) => (int) $a['id_curso'] !== $idCurso));

                return new CalendarResource(
                    periodo: $periodo,
                    agrupado: true,
                    actividades: $actividades,
                    otrasActividades: $otrasActividades,
                );
            }

            // Modo 3: Curso aislado no agrupado
            $actividades = $this->resumen->calendarioDelPeriodo($estudiante, $semestre, $agno, $idCurso);

            return new CalendarResource(
                periodo: $periodo,
                agrupado: false,
                actividades: $actividades,
                otrasActividades: [],
            );
        }

        // Modo 1: Historial completo no agrupado
        if ($request->query('periodo') === 'todos') {
            $actividades = $this->resumen->calendarioDelPeriodo($estudiante, null, null);

            return new CalendarResource(
                periodo: [
                    'semestre' => null,
                    'agno' => null,
                    'es_historico' => true,
                ],
                agrupado: false,
                actividades: $actividades,
                otrasActividades: [],
            );
        }

        // Modo 2: Período actual o parametrizado no agrupado
        $semestre = $request->filled('semestre')
            ? (int) $request->query('semestre')
            : $periodoActual['semestre'];

        $agno = $request->filled('agno')
            ? (int) $request->query('agno')
            : $periodoActual['agno'];

        $esHistorico = ($semestre !== $periodoActual['semestre'] || $agno !== $periodoActual['agno']);

        $actividades = $this->resumen->calendarioDelPeriodo($estudiante, $semestre, $agno);

        return new CalendarResource(
            periodo: [
                'semestre' => $semestre,
                'agno' => $agno,
                'es_historico' => $esHistorico,
            ],
            agrupado: false,
            actividades: $actividades,
            otrasActividades: [],
        );
    }
}
