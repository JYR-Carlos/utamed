<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Curso\InscripcionCurso;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function index()
    {
        /** @var Usuario $user */
        $user = auth()->user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Obtener inscripciones del estudiante
        $inscripciones = $estudiante->inscripcionCursos()
            ->where('estado_inscripcion', 'INSCRITO') // Asumiendo estado para cursos activos
            ->with(['curso.asignacionPlan.asignatura', 'curso.asignacionPlan.plan.carrera'])
            ->get();

        $cursosData = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;
            return [
                'id_curso' => $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'carrera_nombre' => $curso->plan?->carrera?->nombre ?? 'N/A',
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
            ];
        });

        return Inertia::render('student/Courses/Index', [
            'cursos' => $cursosData,
        ]);
    }

    public function show(int $id)
    {
        // Logic to show specific course details (activities, messages)
        // For now just a placeholder or basic view
        return Inertia::render('student/Courses/Show', [
            'id_curso' => $id
        ]);
    }
}
