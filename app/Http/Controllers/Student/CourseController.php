<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Services\Student\BitacoraCursoEstudiante;
use App\Services\Student\MensajeriaCursoEstudiante;
use App\Services\Student\RendimientoCursoEstudiante;
use App\Services\Student\ResumenActividadesEstudiante;
use App\Services\Student\ResumenCursosEstudiante;
use App\Services\Student\StudentSyllabusPresenter;
use App\Traits\ParsesSyllabus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cursos del estudiante: listado por período y detalle de un curso inscrito.
 *
 * Todas las acciones exigen un perfil de estudiante y, en el detalle, una
 * inscripción activa (`INSCRITO`) en el curso.
 */
class CourseController extends Controller
{
    use ParsesSyllabus;

    private const DEFAULT_COURSE_IMAGE = '/img/utamed.webp';


    /**
     * Lista los cursos del estudiante para un semestre/año.
     *
     * GET estudiante/cursos
     */
    public function index(Request $request, ResumenCursosEstudiante $resumenCursos, ResumenActividadesEstudiante $resumen): RedirectResponse|Response
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Parámetros desde la URL. Sin ellos se muestra el período vigente:
        // antes el semestre por defecto era siempre 1 y, en el segundo
        // semestre, «Mis Cursos» decía que no había cursos (T03).
        $semestre = (int) $request->input('semestre', now()->month <= 6 ? 1 : 2);
        $agno     = (int) $request->input('agno', now()->year);

        // Obtener inscripciones filtradas por Semestre y Año del Curso
        $inscripciones = $estudiante->inscripcionCursos()
            ->with([
                'curso',
                'curso.asignacionPlan.asignatura',
                'curso.asignacionPlan.plan.carrera',
                'curso.programas' => fn ($query) => $query->where('es_actual', true)
                    ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO']),
                ...ResumenCursosEstudiante::relacionesDocente($estudiante->id_estudiante),
            ])
            ->get();

        $cursosEstudiante = $inscripciones->map(function ($inscripcion) use ($resumenCursos) {
            $curso = $inscripcion->curso;
            return $this->formatCurso($curso, 'Estudiante') + [
                'cod_asignatura' => $curso->asignacionPlan?->asignatura?->cod_asignatura,
                'profesor'       => $resumenCursos->docenteDeSeccion($curso),
            ];
        });

        $semestreActual = now()->month <= 6 ? 1 : 2;
        $agnoActual = now()->year;

        return Inertia::render('student/Courses/Index', [
            'cursos'   => $cursosEstudiante,
            // Lo mismo que las tarjetas del Inicio: próxima entrega y mensajes sin leer.
            'proximasAVencer' => $resumen->proximasAVencer($estudiante, $semestreActual, $agnoActual),
            'mensajeria' => $resumenCursos->mensajesSinLeer((int) $user->id_usuario),
            'periodoActual' => [
                'semestre' => $semestreActual,
                'agno' => $agnoActual,
            ],
        ]);
    }

    private function formatCurso(Curso $curso, string $rol): array
    {
        $tieneProg = $curso->relationLoaded('programas')
            ? $curso->programas->isNotEmpty()
            : Programa::where('id_curso', $curso->id_curso)
                ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
                ->where('es_actual', true)
                ->exists();
        $default_img = self::DEFAULT_COURSE_IMAGE;
        return [
            'id_curso'         => $curso->id_curso,
            'nombre'           => $curso->nombre,
            'cod_curso'        => $curso->cod_curso,
            'asignatura_nombre'=> $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
            'etiqueta'         => $curso->etiqueta(),
            'carrera_nombre'   => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
            'fecha_inicio'     => $curso->fecha_inicio,
            'fecha_fin'        => $curso->fecha_fin,
            'semestre'    => $curso->semestre_real,
            'agno'        => $curso->agno_real,
            'letra_grupo'      => $curso->letra_grupo,
            'rol'              => $rol,
            'tiene_programa'   => $tieneProg,
            'imagen_url'       => $curso->imagen_url ?? $default_img,
        ];
    }

    /**
     * Muestra el detalle de un curso inscrito y sus actividades visibles.
     *
     * GET estudiante/cursos/{curso}
     */
    public function show(Curso $curso): RedirectResponse|Response
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

        // Cada actividad con el estado del grupo del alumno (entregada, evaluada).
        $actividadesData = app(ResumenActividadesEstudiante::class)->delCurso($estudiante, $curso);

        $syllabus = StudentSyllabusPresenter::build($curso, $estudiante);

        // Las pestañas «Programa» y «Biblioteca» muestran el documento íntegro,
        // igual que la página dedicada; la ficha ya no lo resume.
        $secciones = [];
        if ($syllabus['programa']) {
            $data = Programa::find($syllabus['programa']['id_programa'])?->data_syllabus;
            $data = is_array($data) ? $data : json_decode($data ?? '[]', true);
            $secciones = $this->parseSecciones($data ?? []);
        }

        return Inertia::render('student/Courses/Show', array_merge(
            [
                'secciones' => $secciones,
                // Panel «Rendimiento» (T30): se calcula sólo cuando el alumno
                // lo abre (router.reload con only: ['rendimiento']).
                'rendimiento' => Inertia::lazy(fn () => app(RendimientoCursoEstudiante::class)
                    ->calcular($curso, $estudiante, $syllabus['datos']['componentes'] ?? [])),
                // Pestañas «Mensajería» y «Bitácora»: igual que el rendimiento,
                // se cargan al abrirlas. Abrir la mensajería marca como leído.
                'mensajeria' => Inertia::lazy(fn () => app(MensajeriaCursoEstudiante::class)
                    ->bandeja($curso, (int) $user->id_usuario, request()->integer('componente_id') ?: null)),
                // Contador de la pestaña Mensajería, sin abrirla (no marca leído).
                // En closure para que las recargas parciales no lo recalculen.
                'mensajes_sin_leer' => fn () => app(MensajeriaCursoEstudiante::class)
                    ->noLeidos($curso, (int) $user->id_usuario),
                'bitacora' => Inertia::lazy(fn () => app(BitacoraCursoEstudiante::class)
                    ->compilar($curso, $estudiante)),
                // Vista «Ver todos» del calendario: las fechas de todos los cursos
                // del período de éste. Se pide al elegir esa vista.
                'calendario_semestre' => Inertia::lazy(fn () => $resumen
                    ->calendarioDelPeriodo($estudiante, $curso->semestre_real, $curso->agno_real)),
                'curso' => [
                    'id_curso'          => $curso->id_curso,
                    'nombre'            => $curso->nombre,
                    'cod_curso'         => $curso->cod_curso,
                    'cod_asignatura'    => $curso->asignacionPlan?->asignatura?->cod_asignatura ?? '',
                    'asignatura_nombre' => $curso->asignacionPlan?->asignatura?->nombre ?? 'N/A',
                    'etiqueta'          => $curso->etiqueta(),
                    'carrera_nombre'    => $curso->asignacionPlan?->plan?->carrera?->nombre ?? '',
                    'letra_grupo'       => $curso->letra_grupo,
                    'semestre_real'     => $curso->semestre_real,
                    'agno_real'         => $curso->agno_real,
                    'unidades'          => $curso->unidades_real,
                    // Créditos y horas: la ficha del encabezado los muestra junto
                    // al período, y el programa embebido los daba vacíos.
                    'asignatura'        => $curso->asignacionPlan?->asignatura,
                ],
                'actividades' => $actividadesData,
            ],
            $syllabus
        ));
    }
}
