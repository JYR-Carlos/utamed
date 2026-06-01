<?php
/**
 * Controlador de Curso para
 * 
 * 
 * 
 * 
 * 
 */


namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Curso\Curso;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class CourseController extends Controller
{


    public function index(Request $request)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Parámetros desde la URL
        // /courses?semestre=1&agno=2025
        $semestre = (int) $request->input('semestre', 1);
        $agno     = (int) $request->input('agno', now()->year);

        // Debug opcional
        // dd($semestre, $agno);

        // Obtener inscripciones filtradas
        $inscripciones = $estudiante->inscripcionCursos()
        ->with([
            'curso',
            'curso.asignacionPlan.asignatura',
            'curso.asignacionPlan.plan.carrera'
        ])
        ->get();

        // Debug para verificar resultados
        /*
        dd(
            $inscripciones->map(function ($i) {
                return [
                    'curso_id' => $i->curso?->id,
                    'semestre_real' => $i->curso?->semestre_real,
                    'agno_real' => $i->curso?->agno_real,
                ];
            })
        );
        */

        $cursosEstudiante = $inscripciones->map(function ($inscripcion) {
            $curso = $inscripcion->curso;

            return $this->formatCurso($curso, 'Estudiante');
        });

        return Inertia::render('student/Courses/Index', [
            'cursos'   => $cursosEstudiante,
            'semestre' => $semestre,
            'agno'     => $agno,
        ]);
    }

    private function formatCurso(Curso $curso, string $rol): array
    {
        $tieneProg = \App\Models\Curso\Programa::where('id_curso', $curso->id_curso)
            ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
            ->where('es_actual', true)
            ->exists();
        $default_img = "data:image/png;base64,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";
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
            'imagen_url'       => $curso->imagen_url ?? $default_img,
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
        ]);

        // Obtener IDs de componentes en los que está inscrito el alumno
        $componenteIds = DB::table('curso.inscripcion_componente as ic')
            ->join('curso.componente as c', 'c.id_componente', '=', 'ic.id_componente')
            ->where('ic.id_estudiante', $estudiante->id_estudiante)
            ->where('c.id_curso', $curso->id_curso)
            ->pluck('ic.id_componente');

        if ($componenteIds->isEmpty()) {
            $componenteIds = DB::table('curso.componente')
                ->where('id_curso', $curso->id_curso)
                ->pluck('id_componente');
        }

        // Obtener actividades visibles de los componentes del alumno
        $actividades = Actividad::whereIn('id_componente', $componenteIds)
            ->where('visible', true)
            ->with(['componente.tipoComponente', 'unidad'])
            ->orderBy('fecha_limite', 'asc')
            ->get();

        // Mapear actividades con estado y notas del estudiante
        $actividadesData = $actividades->map(function (Actividad $actividad) use ($estudiante) {
            $asignado = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
                ->whereHas('actividadAsignadaGrupo', fn($q) => $q->where('id_actividad', $actividad->id_actividad))
                ->with('actividadAsignadaGrupo')
                ->first();

            $grupo = $asignado?->actividadAsignadaGrupo;

            return [
                'id_actividad'       => $actividad->id_actividad,
                'nombre'             => $actividad->nombre,
                'fecha_limite'       => $actividad->fecha_limite,
                'tipo_actividad'     => $actividad->tipo_actividad,
                'tipo_entrega'       => $actividad->tipo_entrega,
                'es_grupal'          => $actividad->es_grupal,
                'max_integrantes'    => $actividad->max_integrantes,
                'componente'         => $actividad->componente ? [
                    'id_componente' => $actividad->componente->id_componente,
                    'tipo'          => $actividad->componente->tipoComponente?->tipo ?? 'Componente',
                ] : null,
                'unidad'             => $actividad->unidad ? [
                    'id_unidad' => $actividad->unidad->id_unidad,
                    'nombre'    => $actividad->unidad->nombre,
                ] : null,
                'grupo_numero'       => $grupo?->id_actividad_asignada_grupo,
                'nota_grupal'        => $grupo?->nota,
                'nota_individual'    => $asignado?->nota_individual,
                'diferencia_decimas' => $asignado?->diferencia_decimas,
                'estado'             => $grupo?->estado_actividad_asignada?->value,
                'asignado'           => $asignado !== null,
            ];
        })->values();

        $actividadesData = $actividades->map(fn (Actividad $actividad) => [
            'id_actividad'    => $actividad->id_actividad,
            'nombre'          => $actividad->nombre,
            'es_sumativa'     => $actividad->tipo_actividad === 'SUMATIVA',
            'con_entrega'     => $actividad->tipo_entrega !== 'SIN_ENTREGA',
            'es_grupal'       => (bool) $actividad->es_grupal,
            'max_integrantes' => $actividad->max_integrantes ?? 1,
            'fecha_limite'    => $actividad->fecha_limite,
            'visible'         => (bool) $actividad->visible,
        ])->values();

        return Inertia::render('student/Courses/Show', [
            'curso' => [
                'id_curso'          => $curso->id_curso,
                'nombre'            => $curso->nombre,
                'cod_curso'         => $curso->cod_curso,
                'cod_asignatura'    => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? '',
                'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                'semestre_real'     => $curso->semestre_real,
                'agno_real'         => $curso->agno_real,
                'unidades'          => $curso->unidades_real
            ],
            'actividades' => $actividadesData,
        ]);
    }
}
