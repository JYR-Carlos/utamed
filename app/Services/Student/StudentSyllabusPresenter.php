<?php

namespace App\Services\Student;

use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Syllabus\Secciones\ComponenteEvaluacion;
use App\Syllabus\Secciones\RecursoSyllabus;
use App\Syllabus\Secciones\SeccionVIICompleto;
use App\Syllabus\Secciones\TituloItem;
use App\Syllabus\Secciones\UnidadSyllabus;
use App\Syllabus\SyllabusData;

/**
 * Arma los props (`programa`, `docente`, `datos`) que consume
 * `student/Courses/Syllabus.svelte`. Compartido por la página dedicada
 * (Student\ProgramaController::show) y por la vista "Acerca de este curso"
 * embebida en Student\CourseController::show, para no duplicar el parseo del
 * JSONB data_syllabus en dos lugares.
 */
class StudentSyllabusPresenter
{
    /**
     * @return array{programa: ?array, docente: ?array{nombre: string, email: string}, datos: ?array}
     */
    public static function build(Curso $curso): array
    {
        $curso->loadMissing('componentes.docente.usuario');

        $primerComponente = $curso->componentes->first();
        $docenteUsuario = $primerComponente?->docente?->usuario;
        $docenteData = $docenteUsuario ? [
            'nombre' => $docenteUsuario->nombre,
            'email'  => $docenteUsuario->email,
        ] : null;

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
            return ['programa' => null, 'docente' => $docenteData, 'datos' => null];
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

        return ['programa' => $programaData, 'docente' => $docenteData, 'datos' => $datos];
    }
}
