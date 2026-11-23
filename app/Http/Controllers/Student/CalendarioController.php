<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Services\Student\ResumenActividadesEstudiante;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendario general del estudiante (FEAT-06, OBS-23): las fechas límite de
 * todos sus cursos del semestre vigente en un solo lugar, con un color por
 * curso. Antes sólo existía el calendario dentro de cada curso.
 *
 * Semestre vigente = el más reciente en que el alumno tiene cursos
 * inscritos, el mismo criterio del menú lateral.
 */
class CalendarioController extends Controller
{
    public function index(ResumenActividadesEstudiante $resumen): Response
    {
        /** @var Usuario $usuario */
        $usuario = Auth::user();
        $estudiante = $usuario->estudiante;

        $periodo = DB::table('curso.inscripcion_curso as ic')
            ->join('curso.curso as c', 'c.id_curso', '=', 'ic.id_curso')
            ->where('ic.id_estudiante', $estudiante->id_estudiante)
            ->where('ic.estado_inscripcion', 'INSCRITO')
            ->whereNotNull('c.agno_real')
            ->whereNotNull('c.semestre_real')
            ->orderByDesc('c.agno_real')
            ->orderByDesc('c.semestre_real')
            ->first(['c.agno_real', 'c.semestre_real']);

        return Inertia::render('student/Calendario', [
            'actividades' => $periodo
                ? $resumen->calendarioDelPeriodo($estudiante, (int) $periodo->semestre_real, (int) $periodo->agno_real)
                : [],
            'periodo' => $periodo
                ? ['semestre' => (int) $periodo->semestre_real, 'agno' => (int) $periodo->agno_real]
                : null,
        ]);
    }
}
