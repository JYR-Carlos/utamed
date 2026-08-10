<?php

namespace App\Services\Student;

use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Estudiante;
use App\Syllabus\Secciones\ComponenteEvaluacion;
use App\Syllabus\Secciones\RecursoSyllabus;
use App\Syllabus\Secciones\SeccionVIICompleto;
use App\Syllabus\Secciones\TituloItem;
use App\Syllabus\Secciones\UnidadSyllabus;
use App\Syllabus\SyllabusData;

/**
 * Arma los props (`programa`, `docentes`, `datos`) que consume
 * `student/Courses/Syllabus.svelte`. Compartido por la página dedicada
 * (Student\ProgramaController::show) y por la vista "Acerca de este curso"
 * embebida en Student\CourseController::show, para no duplicar el parseo del
 * JSONB data_syllabus en dos lugares.
 */
class StudentSyllabusPresenter
{
    /**
     * @param  ?Estudiante  $estudiante  Alumno que mira la ficha; acota los docentes
     *                                   a los de su(s) componente(s).
     * @return array{programa: ?array, docentes: list<array{nombre: string, email: ?string, es_titular: bool, componente: ?string}>, datos: ?array}
     */
    public static function build(Curso $curso, ?Estudiante $estudiante = null): array
    {
        $docentesData = self::docentes($curso, $estudiante);

        // El mejor programa visible para el alumno: APROBADO tiene preferencia;
        // si no, BASICO_COMPLETO (versión básica ya completada, pública sin
        // necesidad de aprobación del consejo).
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->whereIn('estado', ['APROBADO', 'BASICO_COMPLETO'])
            ->where('es_actual', true)
            ->with('autor')
            ->orderByRaw("CASE estado WHEN 'APROBADO' THEN 0 WHEN 'BASICO_COMPLETO' THEN 1 ELSE 2 END")
            ->first();

        if (!$programa) {
            return ['programa' => null, 'docentes' => $docentesData, 'datos' => null];
        }

        $dataSyllabus = is_array($programa->data_syllabus)
            ? $programa->data_syllabus
            : json_decode($programa->data_syllabus, true);

        $syllabus = SyllabusData::fromArray($dataSyllabus ?? []);
        $secciones = $syllabus->secciones;
        $tipoSyllabus = $syllabus->metadata?->tipoSyllabus?->value ?? 'COMPLETO';

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

        $programaData = [
            'id_programa'      => $programa->id_programa,
            'version_programa' => $programa->version_programa,
            'estado'           => $programa->estado,
            'creado_por'       => $programa->autor?->nombre,
            'fecha_creacion'   => $programa->fecha_creacion,
            'tipo_syllabus'    => $tipoSyllabus,
        ];

        return ['programa' => $programaData, 'docentes' => $docentesData, 'datos' => $datos];
    }

    /**
     * Docentes que el alumno debe ver: los de SU sección, no los del curso.
     *
     * Un componente (cátedra, laboratorio, taller) tiene un docente titular y
     * puede tener además un docente extra propio de esa sección; ambos se
     * devuelven, el titular primero. El titular del curso
     * (`curso.id_docente_titular`) es un rol administrativo que puede no hacer
     * clases, así que no se usa como fuente aquí.
     *
     * Si el alumno no tiene inscripción a nivel de componente —hoy sólo 5 de
     * 435 componentes tienen docente asignado y la inscripción por componente
     * está incompleta— se cae a todos los componentes del curso para no dejar
     * la ficha vacía, igual que hace CourseController::show con las actividades.
     *
     * @return list<array{nombre: string, email: ?string, es_titular: bool, componente: ?string}>
     */
    private static function docentes(Curso $curso, ?Estudiante $estudiante): array
    {
        $componentes = null;

        if ($estudiante) {
            $propios = self::componentesQuery($curso)
                ->whereHas('inscripcionComponentes', fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante))
                ->get();

            if ($propios->isNotEmpty()) {
                $componentes = $propios;
            }
        }

        $componentes ??= self::componentesQuery($curso)->get();

        return $componentes
            ->sortBy('id_componente')
            ->flatMap(fn (Componente $componente) => $componente->docenteComponentes
                // Titular primero, luego el resto en orden de asignación.
                ->sortBy(fn ($dc) => [!$dc->es_titular, $dc->id_docente_componente])
                ->map(function ($dc) use ($componente) {
                    $usuario = $dc->docente?->usuario;

                    return $usuario ? [
                        'nombre'     => $usuario->nombre_completo,
                        'email'      => $usuario->email,
                        'es_titular' => (bool) $dc->es_titular,
                        'componente' => $componente->tipoComponente?->tipo,
                    ] : null;
                })
                ->filter())
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Componente>
     */
    private static function componentesQuery(Curso $curso)
    {
        return Componente::where('id_curso', $curso->id_curso)
            ->with(['tipoComponente', 'docenteComponentes.docente.usuario']);
    }
}
