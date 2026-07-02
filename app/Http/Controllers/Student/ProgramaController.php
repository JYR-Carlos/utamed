<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\Programa;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Syllabus\Secciones\ComponenteEvaluacion;
use App\Syllabus\Secciones\RecursoSyllabus;
use App\Syllabus\Secciones\SeccionVIICompleto;
use App\Syllabus\Secciones\TituloItem;
use App\Syllabus\Secciones\UnidadSyllabus;
use App\Syllabus\SyllabusData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista del programa/syllabus de un curso para el estudiante.
 *
 * Sólo muestra programas visibles para alumnos (estado `APROBADO`, con
 * preferencia sobre `BASICO_COMPLETO`) y exige inscripción activa en el curso.
 */
class ProgramaController extends Controller
{
    /**
     * Muestra el programa visible del curso (o un aviso si no existe).
     *
     * GET estudiante/cursos/{curso}/programa
     */
    public function show(Curso $curso): RedirectResponse|Response
    {
        /** @var \App\Models\Usuario\Usuario $user */
        $user = Auth::user();

        // Verificar que estudiante está inscrito en este curso
        if (!$user->estudiante) {
            return redirect()->route('estudiante.dashboard')
                ->with('error', 'No tienes un perfil de estudiante');
        }

        $inscripcion = InscripcionCurso::where('id_estudiante', $user->estudiante->id_estudiante)
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->first();

        if (!$inscripcion) {
            return redirect()->route('estudiante.cursos.index')
                ->with('error', 'No estás inscrito en este curso');
        }

        // Obtener el mejor programa visible: APROBADO tiene preferencia; si no, BASICO_COMPLETO
        // (versión básica ya completada es pública sin necesidad de aprobación)
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
            ->where('es_actual', true)
            ->with('autor')
            ->orderByRaw("CASE estado WHEN 'APROBADO' THEN 0 WHEN 'BASICO_COMPLETO' THEN 1 ELSE 2 END")
            ->first();

        // Si no hay programa, mostrar página con aviso en lugar de 404
        if (!$programa) {
            $curso->load([
                'asignacionPlan.asignatura',
                'asignacionPlan.plan.carrera',
                'componentes.docente.usuario',
            ]);

            $cursoData = [
                'id_curso'   => $curso->id_curso,
                'nombre'     => $curso->nombre,
                'cod_curso'  => $curso->cod_curso,
                'asignatura' => $curso->asignacionPlan?->asignatura,
                'carrera'    => $curso->asignacionPlan?->plan?->carrera,
            ];

            $primerComponente = $curso->componentes->first();
            $docenteUsuario = $primerComponente?->docente?->usuario;

            return Inertia::render('student/Courses/Syllabus', [
                'programa' => null,
                'curso'    => $cursoData,
                'docente'  => $docenteUsuario ? ['nombre' => $docenteUsuario->nombre, 'email' => $docenteUsuario->email] : null,
                'datos'    => null,
            ]);
        }

        // Cargar relaciones del curso (incluye componentes para obtener docente)
        $curso->load([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'componentes.docente.usuario',
        ]);

        // Procesar data_syllabus correctamente
        $dataSyllabus = is_array($programa->data_syllabus)
            ? $programa->data_syllabus
            : json_decode($programa->data_syllabus, true);

        $syllabus = SyllabusData::fromArray($dataSyllabus ?? []);
        $secciones = $syllabus->secciones;

        // Determinar tipo de syllabus (BASICO o COMPLETO)
        $tipoSyllabus = $syllabus->metadata?->tipoSyllabus?->value ?? 'COMPLETO';

        // ── Datos estructurados para la vista del alumno ──────────────────────
        $secI = $secciones->seccionI();
        $secII = $secciones->seccionII();
        $secIV = $secciones->seccionIV();
        $secVI = $secciones->seccionVI();
        $secVII = $secciones->seccionVII();
        $secVIII = $secciones->seccionVIII();
        $secIX = $secciones->seccionIX();

        $resultados = $secVII instanceof SeccionVIICompleto
            ? array_map(fn ($r) => $r->toArray(), $secVII->resultadosAprendizajeItems)
            : [];

        $datos = [
            // Sección I: Identificación (BÁSICO)
            'categoria'                => $secI?->categoria ?? '',

            // Sección II: Presentación (BÁSICO)
            'descripcion'              => $secII?->texto ?? '',

            // Sección IV: Competencias (COMPLETO)
            'competencias_especificas' => $secIV ? TituloItem::listToArray($secIV->competenciasEspecificas) : [],
            'competencias_genericas'   => $secIV ? TituloItem::listToArray($secIV->competenciasGenericas) : [],

            // Sección VI: Unidades (BÁSICO)
            'unidades'                 => $secVI ? UnidadSyllabus::listToArray($secVI->unidades) : [],

            // Sección VII: Resultados de Aprendizaje (COMPLETO)
            'resultados_aprendizaje'   => $resultados,

            // Sección VIII: Recursos (BÁSICO)
            'recursos'                 => $secVIII ? RecursoSyllabus::listToArray($secVIII->recursos) : [],

            // Sección IX: Aspectos Administrativos (BÁSICO)
            'componentes'              => $secIX ? ComponenteEvaluacion::listToArray($secIX->tablaComponentes) : [],
            'normativa'                => $secIX?->descripcion ?? '',
        ];

        // ── Docente (primer componente) ──────────────────────────────────────────
        $primerComponente = $curso->componentes->first();
        $docenteUsuario = $primerComponente?->docente?->usuario;
        $docenteData = $docenteUsuario ? [
            'nombre' => $docenteUsuario->nombre,
            'email'  => $docenteUsuario->email,
        ] : null;

        $programaData = [
            'id_programa'      => $programa->id_programa,
            'version_programa' => $programa->version_programa,
            'estado'           => $programa->estado,
            'creado_por'       => $programa->autor?->nombre,
            'fecha_creacion'   => $programa->fecha_creacion,
            'tipo_syllabus'    => $tipoSyllabus,
        ];

        $cursoData = [
            'id_curso'   => $curso->id_curso,
            'nombre'     => $curso->nombre,
            'cod_curso'  => $curso->cod_curso,
            'asignatura' => $curso->asignacionPlan?->asignatura,
            'carrera'    => $curso->asignacionPlan?->plan?->carrera,
        ];

        return Inertia::render('student/Courses/Syllabus', [
            'programa' => $programaData,
            'curso'    => $cursoData,
            'docente'  => $docenteData,
            'datos'    => $datos,
        ]);
    }

}
