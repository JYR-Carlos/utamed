<?php

namespace App\Services;

use App\Models\Agenda\Actividad;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Support\Permissions;

/**
 * Arma los props de la página del asistente de syllabus
 * (`docente/SyllabusWizard.svelte`), que reemplaza al antiguo `SyllabusModal`.
 *
 * Todo lo que el asistente necesitaba cargar por su cuenta (el JSONB del
 * programa, los componentes del curso y las actividades existentes) viaja ahora
 * como prop de Inertia: la pantalla es una página, no un modal que se abre y
 * empieza a pedir cosas.
 *
 * ## Quién puede escribir cada sección
 *
 * `puedeEscribirSeccion()` es la **única** definición de esa regla: la usan
 * tanto la barra de pasos como el guardado
 * (`Admin\ProgramaController::validatePermissionForSeccion`), para que no
 * puedan volver a divergir.
 *
 * El **docente titular escribe todas las secciones de su curso sin permisos de
 * módulo**. Los `cursos/programas/modificar:modulo_N` existen justamente para
 * que el titular *delegue* secciones en el resto del equipo — es lo que hace
 * `DelegacionPermisosController`, cuya matriz de delegables son esos mismos
 * nueve slugs y que sólo el titular puede usar. Exigírselos al propio titular
 * dejaba al dueño del documento sin poder tocarlo.
 */
class SyllabusWizardPresenter
{
    /** Numerales de las nueve secciones, en orden. */
    public const SECCIONES = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];

    /** Permiso de módulo que gobierna cada sección. */
    private const PERMISO_SECCION = [
        'I'    => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1,
        'II'   => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2,
        'III'  => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3,
        'IV'   => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4,
        'V'    => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5,
        'VI'   => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6,
        'VII'  => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7,
        'VIII' => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8,
        'IX'   => Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9,
    ];

    /**
     * Props completos de la página.
     *
     * @param  ?string  $tipoSolicitado  'BASICO'|'COMPLETO' al crear, o al promover
     *                                   un básico a completo. Si es null se toma el
     *                                   tipo que ya declara el programa.
     * @return array<string, mixed>
     */
    public static function build(
        Curso $curso,
        Usuario $user,
        ?Programa $programa,
        ?string $tipoSolicitado = null,
        string $layoutType = 'docente',
    ): array {
        $curso->loadMissing([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'docenteTitular.usuario',
        ]);

        $asignatura = $curso->asignacionPlan?->asignatura;

        $tipo = $tipoSolicitado
            ?? $programa?->getTipoSyllabus()
            ?? 'COMPLETO';

        return [
            'curso' => array_merge(
                [
                    'id_curso'          => $curso->id_curso,
                    'cod_curso'         => $curso->cod_curso,
                    'nombre'            => $curso->nombre,
                    'id_contexto'       => $curso->id_contexto,
                    'asignatura_nombre' => $asignatura?->nombre,
                    'carrera_nombre'    => $curso->asignacionPlan?->plan?->carrera?->nombre,
                    'creditos_sct'      => $asignatura?->creditos_sct,
                    'horas_catedra'     => $asignatura?->horas_catedra,
                    'horas_taller'      => $asignatura?->horas_taller,
                    'horas_laboratorio' => $asignatura?->horas_laboratorio,
                    // `asignacion_plan.tipo_ramo` es un smallint sin catálogo en
                    // BD, así que NO puede rellenar la categoría textual que
                    // exige la sección I (Obligatorio|Electivo|…): esa la elige
                    // quien redacta. Viaja igual por si el equipo publica el
                    // catálogo más adelante.
                    'tipo_ramo'         => $curso->asignacionPlan?->tipo_ramo,
                ],
                SyllabusViewerPresenter::cabeceraCurso($curso),
            ),
            'programa' => $programa ? [
                'id_programa'      => $programa->id_programa,
                'version_programa' => $programa->version_programa,
                'estado'           => $programa->estado,
                'tipo_syllabus'    => $programa->getTipoSyllabus(),
                // Secciones EN CRUDO: el asistente escribe sobre el mismo JSONB
                // que guarda, no sobre el texto aplanado que lee el visor.
                'secciones'        => $programa->data_syllabus['secciones'] ?? [],
            ] : null,
            'tipoSyllabus'        => $tipo,
            'seccionesEditables'  => self::seccionesEditables($user, $curso),
            'componentes'         => self::componentes($curso),
            'actividades'         => self::actividades($curso),
            'editandoDeOtro'      => self::editandoDeOtro($user, $curso),
            'docenteTitular'      => $curso->docenteTitular?->usuario?->nombre_completo,
            'layoutType'          => $layoutType,
        ];
    }

    /**
     * Numerales que este usuario puede escribir en este curso.
     *
     * @return list<string>
     */
    public static function seccionesEditables(Usuario $user, Curso $curso): array
    {
        if (self::escribeTodoElDocumento($user, $curso)) {
            return self::SECCIONES;
        }

        return array_values(array_filter(
            self::SECCIONES,
            fn (string $seccion) => self::puedeEscribirSeccion($user, $curso, $seccion),
        ));
    }

    /**
     * ¿Puede este usuario escribir esta sección de este curso?
     *
     * Definición única, compartida por la interfaz y por el guardado.
     */
    public static function puedeEscribirSeccion(Usuario $user, Curso $curso, string $seccion): bool
    {
        if (!isset(self::PERMISO_SECCION[$seccion])) {
            return false;
        }

        if (self::escribeTodoElDocumento($user, $curso)) {
            return true;
        }

        return $curso->id_contexto !== null
            && $user->hasPermission(self::PERMISO_SECCION[$seccion], $curso->id_contexto);
    }

    /**
     * Quien manda sobre el documento entero: el docente titular del curso (es
     * suyo y es quien delega los módulos) y quien tenga el comodín de
     * modificación en el contexto (administración).
     */
    private static function escribeTodoElDocumento(Usuario $user, Curso $curso): bool
    {
        if ($user->docente?->id_docente !== null
            && $user->docente->id_docente === $curso->id_docente_titular) {
            return true;
        }

        return $curso->id_contexto !== null
            && $user->hasPermission(Permissions::CURSOS_PROGRAMAS_MODIFICAR_ALL, $curso->id_contexto);
    }

    /**
     * Componentes de evaluación del curso, para precargar la tabla de la
     * sección IX. Mismo contrato que devolvía el endpoint que el modal pedía
     * por axios (`GET {base}/cursos/{curso}/componentes`).
     *
     * @return list<array<string, mixed>>
     */
    private static function componentes(Curso $curso): array
    {
        // Mismas claves que devolvía `Admin\ComponenteController::indexByCurso`,
        // que es lo que el modal pedía por axios. Ojo con los nombres de columna:
        // en curso.componente son `porcentaje_aprobacion` y
        // `porcentaje_asistencia_obligatoria`.
        return Componente::with('tipoComponente')
            ->where('id_curso', $curso->id_curso)
            ->get()
            ->map(fn (Componente $c) => [
                'componente'             => $c->tipoComponente?->tipo ?? 'Componente',
                'porcentaje'             => (float) ($c->porcentaje_aprobacion ?? 0),
                'genera_acta'            => (bool) $c->genera_acta,
                'aprobacion_obligatoria' => (bool) $c->aprobacion_obligatoria,
                'asistencia_obligatoria' => (float) ($c->porcentaje_asistencia_obligatoria ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Actividades ya creadas en el curso, para que la sección VII del syllabus
     * BÁSICO pueda referenciarlas en vez de duplicarlas.
     *
     * @return list<array<string, mixed>>
     */
    private static function actividades(Curso $curso): array
    {
        // `agenda.actividad` no tiene id_curso: cuelga del componente.
        return Actividad::whereHas('componente', fn ($q) => $q->where('id_curso', $curso->id_curso))
            ->orderBy('fecha_limite')
            ->get(['id_actividad', 'nombre', 'fecha_limite'])
            ->map(fn (Actividad $a) => [
                'id_actividad' => $a->id_actividad,
                'nombre'       => $a->nombre,
                'fecha_limite' => $a->fecha_limite,
            ])
            ->values()
            ->all();
    }

    /**
     * True cuando quien edita no es el docente titular del curso: la lámina pide
     * avisarlo, porque el cambio queda a nombre de quien lo hizo en
     * `auditoria.programa_historial`.
     */
    private static function editandoDeOtro(Usuario $user, Curso $curso): bool
    {
        $idDocente = $user->docente?->id_docente;

        return $idDocente === null || $idDocente !== $curso->id_docente_titular;
    }
}
