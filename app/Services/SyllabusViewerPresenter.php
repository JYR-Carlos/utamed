<?php

namespace App\Services;

use App\Models\Auditoria\ProgramaHistorial;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use Carbon\CarbonImmutable;

/**
 * Arma la cabecera y el historial del visor de documento de syllabus
 * (`docente/Programa.svelte`), compartido por las dos rutas que lo abren:
 * `/docente/cursos/{curso}/programa` (Administrativo\ProgramaController) y
 * `/admin/cursos/{curso}/programa/revisar` (Admin\ProgramaController).
 *
 * Toda fecha del documento sale de `auditoria.programa_historial`: la tabla
 * `curso.programa` NO tiene columnas de fecha (ni `fecha_creacion`, ni
 * `fecha_entrega`, ni `fecha_aprobacion`) y su modelo declara
 * `$timestamps = false`. Los triggers `tr_programa_creado` /
 * `tr_programa_modificado` son los que dejan rastro, así que el historial es la
 * única fuente veraz de "creado el", "última modificación" y "aprobado el".
 */
class SyllabusViewerPresenter
{
    /** Cuántos eventos del historial viajan al visor. */
    private const MAX_EVENTOS = 40;

    /**
     * `auditoria.programa_historial.fecha_accion` no tiene cast en el modelo y
     * llega como 'Y-m-d H:i:s.u'. El frontend espera ISO 8601 para poder mostrar
     * la hora sin depender de qué formatos tolere el `Date` del navegador.
     */
    private static function iso(?string $fecha): ?string
    {
        return $fecha === null || $fecha === ''
            ? null
            : CarbonImmutable::parse($fecha)->toIso8601String();
    }

    /**
     * Metadatos del documento + línea de tiempo.
     *
     * @return array{
     *   historial: list<array{accion: string, estado_anterior: ?string, estado_nuevo: ?string, observaciones: ?string, fecha_accion: ?string, usuario: ?string}>,
     *   fecha_creacion: ?string,
     *   fecha_modificacion: ?string,
     *   fecha_aprobacion: ?string,
     *   autor: ?string,
     *   revisor: ?string,
     *   tipo_syllabus: ?string,
     *   secciones_requeridas: list<string>
     * }
     */
    public static function documento(Programa $programa): array
    {
        $eventos = ProgramaHistorial::with('usuario')
            ->where('id_programa', $programa->id_programa)
            ->orderByDesc('fecha_accion')
            ->orderByDesc('id_log')
            ->limit(self::MAX_EVENTOS)
            ->get();

        $historial = $eventos->map(fn (ProgramaHistorial $e) => [
            'accion'          => $e->accion,
            'estado_anterior' => $e->estado_anterior,
            'estado_nuevo'    => $e->estado_nuevo,
            'observaciones'   => $e->observaciones,
            'fecha_accion'    => self::iso($e->fecha_accion),
            'usuario'         => $e->usuario?->nombre_completo,
        ])->values()->all();

        // Sólo se carga `revisor` cuando hay firma: con FK nula, la relación
        // compuesta de Compoships emite un deprecation por cada acceso.
        $programa->loadMissing($programa->revisado_por ? ['autor', 'revisor'] : ['autor']);

        return [
            'historial'            => $historial,
            'fecha_creacion'       => self::iso(
                $eventos->last(fn ($e) => $e->accion === 'CREATE')?->fecha_accion
                    ?? $eventos->last()?->fecha_accion
            ),
            'fecha_modificacion'   => self::iso($eventos->first()?->fecha_accion),
            'fecha_aprobacion'     => $programa->estado === 'APROBADO'
                ? self::iso($eventos->first(fn ($e) => $e->estado_nuevo === 'APROBADO')?->fecha_accion)
                : null,
            'autor'                => $programa->autor?->nombre_completo,
            'revisor'              => $programa->revisado_por ? $programa->revisor?->nombre_completo : null,
            'tipo_syllabus'        => $programa->getTipoSyllabus(),
            'secciones_requeridas' => ProgramaService::getRequiredSecciones($programa),
        ];
    }

    /**
     * Último rechazo registrado, para el banner que abre la vista del docente.
     * Sólo tiene sentido en BORRADOR: rechazar devuelve el programa a ese estado
     * (no existe un estado RECHAZADO en `curso.programa.estado`).
     *
     * @return array{razon_rechazo: ?string, fecha_rechazo: ?string, rechazado_por: ?string}
     */
    public static function ultimoRechazo(Programa $programa): array
    {
        if ($programa->estado !== 'BORRADOR') {
            return ['razon_rechazo' => null, 'fecha_rechazo' => null, 'rechazado_por' => null];
        }

        $rechazo = ProgramaHistorial::with('usuario')
            ->where('id_programa', $programa->id_programa)
            ->where('accion', 'RECHAZO')
            ->orderByDesc('fecha_accion')
            ->orderByDesc('id_log')
            ->first();

        return [
            'razon_rechazo' => $rechazo?->observaciones,
            'fecha_rechazo' => self::iso($rechazo?->fecha_accion),
            'rechazado_por' => $rechazo?->usuario?->nombre_completo,
        ];
    }

    /**
     * Identificación del curso para la cabecera del visor (código de asignatura,
     * grupo, período y carrera). Espera `asignacionPlan.asignatura`,
     * `asignacionPlan.plan.carrera` y `docenteTitular.usuario` cargados.
     *
     * @return array<string, mixed>
     */
    public static function cabeceraCurso(Curso $curso): array
    {
        $curso->loadMissing([
            'asignacionPlan.asignatura',
            'asignacionPlan.plan.carrera',
            'docenteTitular.usuario',
        ]);

        $asignatura = $curso->asignacionPlan?->asignatura;

        return [
            'cod_asignatura'  => $asignatura?->cod_asignatura,
            'letra_grupo'     => $curso->letra_grupo,
            'agno_real'       => $curso->agno_real,
            'semestre_real'   => $curso->semestre_real,
            'docente_titular' => $curso->docenteTitular?->usuario?->nombre_completo,
        ];
    }
}
