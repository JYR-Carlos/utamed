<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\InscripcionCurso;
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

        // Obtener las inscripciones del estudiante
        $inscripciones = InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->with([
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                'curso.componentes.docentesAsignados.usuario'  // Cargar docentes de cada componente
            ])
            ->get();

        // Transformar datos para la vista
        $cursosData = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;
            
            // Obtener el nombre del primer docente de los componentes, o mostrar "(sin docente asignado)"
            $profesor = '(sin docente asignado)';
            if ($curso->componentes && $curso->componentes->count() > 0) {
                // Obtener el primer docente de cualquier componente
                $primerDocente = $curso->componentes
                    ->flatMap(fn ($componente) => $componente->docentesAsignados ?? collect())
                    ->first()?->usuario;
                if ($primerDocente) {
                    $profesor = trim("{$primerDocente->nombre1} {$primerDocente->apellido1}");
                }
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

        return Inertia::render('student/Dashboard', [
            'estudiante' => [
                'id_estudiante' => $estudiante->id_estudiante,
                'rut' => $user->rut, // Estudiante puede no tener grado/titulo
                'id_usuario' => $user->id_usuario,
            ],
            'cursos' => $cursosData,
            'stats' => [
                'total_cursos' => $cursosData->count(),
                'nombre_completo' => trim("{$user->nombre1} {$user->apellido1}"),
            ],
            'isAyudante' => $isAyudante
        ]);
    }
}
