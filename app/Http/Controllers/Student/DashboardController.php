<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\InscripcionCurso;
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
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        // Verificar que el usuario es estudiante
        if (!$user->estudiante) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $estudiante = $user->estudiante;
        $nombreCarrera = $estudiante->carrera->nombre;
        // Obtener las inscripciones del estudiante
        $inscripciones = InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->with([
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                // Docentes de cada componente, con es_titular para poder priorizar.
                'curso.componentes.docenteComponentes.docente.usuario',
                // Marca qué componentes son la sección del alumno.
                'curso.componentes.inscripcionComponentes' => fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante),
            ])
            ->get();

        // Transformar datos para la vista
        $cursosData = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;

            // El profesor de la tarjeta es el titular de la sección del alumno
            // (mismo criterio que StudentSyllabusPresenter). Si todavía no hay
            // inscripción por componente, se cae a los componentes del curso.
            $profesor = '(sin docente asignado)';
            $componentes = $curso->componentes ?? collect();
            $propios = $componentes->filter(fn ($componente) => $componente->inscripcionComponentes->isNotEmpty());
            $relevantes = $propios->isNotEmpty() ? $propios : $componentes;

            $primerDocente = $relevantes
                ->sortBy('id_componente')
                ->flatMap(fn ($componente) => $componente->docenteComponentes
                    ->sortBy(fn ($dc) => [!$dc->es_titular, $dc->id_docente_componente]))
                ->first()?->docente?->usuario;

            if ($primerDocente) {
                $profesor = trim("{$primerDocente->nombre1} {$primerDocente->apellido1}");
            }

            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
                'profesor' => $profesor,
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

        return Inertia::render('student/Dashboard', [
            'estudiante' => [
                'id_estudiante' => $estudiante->id_estudiante,
                'rut' => $user->rut, 
                'id_usuario' => $user->id_usuario,
                'nombre_carrera' => $nombreCarrera
            ],
            'cursos' => $cursosData,
            'stats' => [
                'total_cursos' => $cursosData->count(),
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
            ],
            'isAyudante' => $isAyudante,
            'semestreActual' => $semestreActual

        ]);
    }
}
