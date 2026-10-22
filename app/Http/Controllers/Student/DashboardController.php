<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\InscripcionCurso;
use App\Services\Student\ResumenActividadesEstudiante;
use App\Services\Student\ResumenCursosEstudiante;
use App\Services\Sso\SgeqSsoService;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
/**
 * Controlador para el dashboard del estudiante.
 */
class DashboardController extends Controller
{
    /**
     * Muestra el dashboard del estudiante con su lista de cursos inscritos.
     *
     * @return \Illuminate\Http\RedirectResponse|\Inertia\Response
     */
    public function index(ResumenCursosEstudiante $cursos, SgeqSsoService $sgeq, ResumenActividadesEstudiante $resumen)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Verificar que el usuario es estudiante
        if (!$user->estudiante) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $estudiante = $user->estudiante;
        // Obtener las inscripciones del estudiante
        $inscripciones = InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->with([
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                ...ResumenCursosEstudiante::relacionesDocente($estudiante->id_estudiante),
            ])
            ->get();

        // Transformar datos para la vista
        $cursosData = $inscripciones->map(function ($inscripcion) use ($cursos) {
            $curso = $inscripcion->curso;

            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
                'semestre_real' => $curso->semestre_real,
                'agno_real' => $curso->agno_real,
                'profesor' => $cursos->docenteDeSeccion($curso),
            ];
        });

        // Check if user is also Ayudante
        $isAyudante = $user->rolesAsignados()
            ->wherePivot('esta_activo', true)
            ->wherePivot('fue_eliminado', false)
            ->whereIn('rol.nombre', ['Ayudante'])
            ->exists();

        //vista del semestre por defecto (entre 1 y 2)
        $semestreActual = Carbon::now()->month > 6 ? 2 : 1;
        $agnoActual = Carbon::now()->year;

        return Inertia::render('student/Dashboard', [
            'mensajeria' => $cursos->mensajesSinLeer((int) $user->id_usuario),
            'cursos' => $cursosData,
            'proximasAVencer' => $resumen->proximasAVencer($estudiante, $semestreActual, $agnoActual),
            'notasRecientes' => $resumen->notasYRetroalimentaciones($estudiante),
            'stats' => [
                'total_cursos' => $cursosData->count(),
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
            ],
            'isAyudante' => $isAyudante,
            // El acceso a SGEQ depende de la carrera, así que el botón no se
            // muestra a quien igual rebotaría al hacer clic.
            'puedeAbrirSgeq' => $sgeq->resolverRol($user) !== null,
            'semestreActual' => $semestreActual,
            'periodoActual' => [
                'semestre' => $semestreActual,
                'agno' => $agnoActual,
            ],

        ]);
    }
}
