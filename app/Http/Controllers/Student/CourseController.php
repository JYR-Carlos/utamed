<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Administrativo\Programa;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // ── Cursos como Estudiante ────────────────────────────────────────────
        // Obtener todas las inscripciones activas del estudiante
        $inscripciones = $estudiante->inscripcionCursos()
            ->with(['curso.asignacionPlan.asignatura', 'curso.asignacionPlan.plan.carrera'])
            ->get();

        $cursosEstudiante = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;
            return $this->formatCurso($curso, 'Estudiante');
        });

        // ── Cursos como Ayudante ──────────────────────────────────────────────
        $rolAyudante = Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();

        $idsEstudiante = $cursosEstudiante->pluck('id_curso')->toArray();

        $cursosAyudante = collect();
        if ($rolAyudante) {
            $contextosAsignados = UsuarioRolAsignacion::where('id_usuario', $user->id_usuario)
                ->where('id_rol', $rolAyudante->id_rol)
                ->where('esta_activo', true)
                ->where('fue_eliminado', false)
                ->pluck('id_contexto');

            if ($contextosAsignados->isNotEmpty()) {
                $cursosAyudante = Curso::whereIn('id_contexto', $contextosAsignados)
                    ->whereNotIn('id_curso', $idsEstudiante)
                    ->with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera'])
                    ->get()
                    ->map(fn($curso) => $this->formatCurso($curso, 'Ayudante'));
            }
        }

        $todos = $cursosEstudiante->concat($cursosAyudante)->values();

        $semestre = $todos->first()?['semestre_real'] ?? 1: 1;
        $agno     = $todos->first()?['agno_real']     ?? now()->year: now()->year;

        return Inertia::render('student/Courses/Index', [
            'cursos'   => $todos,
            'semestre' => $semestre,
            'agno'     => $agno,
        ]);
    }

    private function formatCurso(Curso $curso, string $rol): array
    {
        $tieneProg = \App\Models\Administrativo\Programa::where('id_curso', $curso->id_curso)
            ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
            ->where('es_actual', true)
            ->exists();

        return [
            'id_curso'         => $curso->id_curso,
            'nombre'           => $curso->nombre,
            'cod_curso'        => $curso->cod_curso,
            'asignatura_nombre'=> $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            'carrera_nombre'   => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
            'fecha_inicio'     => $curso->fecha_inicio,
            'fecha_fin'        => $curso->fecha_fin,
            'semestre_real'    => $curso->semestre_real,
            'agno_real'        => $curso->agno_real,
            'letra_grupo'      => $curso->letra_grupo,
            'rol'              => $rol,
            'tiene_programa'   => $tieneProg,
        ];
    }

    public function show(Curso $curso)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Verificar que el estudiante está inscrito en este curso
        $inscripcion = $estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->first();

        if (!$inscripcion) {
            abort(403, 'No estás inscrito en este curso');
        }

        // Cargar curso con relaciones
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'secciones.tipoSeccion',
            'secciones.docente'
        ]);

        // Formatear datos del curso
        $cursoData = [
            'id_curso' => $curso->id_curso,
            'nombre' => $curso->nombre,
            'cod_curso' => $curso->cod_curso,
            'fecha_inicio' => $curso->fecha_inicio,
            'fecha_fin' => $curso->fecha_fin,
            'asignatura' => [
                'id_asignatura' => $curso->asignacionPlan?->asignatura?->id_asignatura,
                'nombre' => $curso->asignacionPlan?->asignatura?->nombre,
                'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura,
                'descripcion' => $curso->asignacionPlan?->asignatura?->descripcion,
                'creditos_sct' => $curso->asignacionPlan?->asignatura?->creditos_sct,
            ],
            'carrera' => [
                'id_carrera' => $curso->asignacionPlan?->plan?->carrera?->id_carrera,
                'nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre,
            ],
            'secciones' => $curso->secciones->map(function ($seccion) {
                return [
                    'id_seccion' => $seccion->id_seccion,
                    'tipo_seccion' => [
                        'id_tipo_seccion' => $seccion->tipoSeccion?->id_tipo_seccion,
                        'tipo' => $seccion->tipoSeccion?->tipo,
                    ],
                    'docente' => $seccion->docente ? [
                        'id_docente' => $seccion->docente->id_docente,
                        'nombre_completo' => $seccion->docente->usuario?->nombre1 . ' ' . $seccion->docente->usuario?->apellido1,
                    ] : null,
                ];
            })->values(),
        ];

        return Inertia::render('student/Courses/Show', [
            'curso' => $cursoData
        ]);
    }
}
