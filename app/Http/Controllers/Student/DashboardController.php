<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\InscripcionCurso;
use Inertia\Inertia;

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
        $user = auth()->user();

        // Verificar que el usuario es estudiante
        if (!$user->estudiante) {
            return redirect('/dashboard')->with('error', 'No tienes acceso a esta sección');
        }

        $estudiante = $user->estudiante;

        // Obtener las inscripciones del estudiante
        $inscripciones = InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->with(['curso.asignatura', 'curso.plan.carrera'])
            ->get();

        // Transformar datos para la vista
        $cursosData = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;
            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura_nombre' => $curso->asignatura?->nombre ?? 'N/A',
                'carrera_nombre' => $curso->plan?->carrera?->nombre ?? 'N/A',
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
            ];
        });

        // Check if user is also Ayudante
        $isAyudante = $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereHas('rol', function ($query) {
                $query->whereIn('nombre', ['Ayudante', 'ayudante']);
            })
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
