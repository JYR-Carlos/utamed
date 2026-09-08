<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Auditoria\ProgramaHistorial;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Controlador del calendario académico del docente.
 *
 * Reúne, para los cursos en los que el docente participa (titular del curso o
 * docente de un componente), las tres familias de marcas que el calendario
 * sabe dibujar:
 *
 * 1. Fechas límite  (agenda.actividad.fecha_limite)  — fecha NOMINAL.
 * 2. Sesiones de asistencia (curso.asistencia)       — sesión implícita
 *    (dia, hora_inicio, hora_fin) por componente, con su contador de
 *    presentes. Sólo existen las sesiones YA TOMADAS: el esquema no tiene
 *    ninguna tabla de horario/planificación de clases, así que no hay forma de
 *    saber que "hoy tocaba clase y no se tomó asistencia".
 * 3. Hitos de syllabus — dos fechas límite reales del curso
 *    (curso.fecha_limite_entrega_basico / fecha_limite_entrega_syllabus) y los
 *    eventos de aprobación/rechazo del programa vigente
 *    (auditoria.programa_historial.fecha_accion + observaciones).
 *
 * Es una vista de solo lectura: no crea, edita ni elimina nada.
 *
 * Tablas implicadas:
 * - curso.curso: Cursos del docente (incluye letra_grupo y las fechas límite
 *   de entrega de básico/syllabus)
 * - curso.componente / curso.tipo_componente: Componentes del curso
 * - curso.docente_componente: Asignación del docente a componentes
 * - curso.inscripcion_componente + curso.asistencia: Sesiones de asistencia
 * - agenda.actividad: Actividades con su fecha_limite
 * - agenda.actividad_asignada_grupo: Grupos asignados y su nota (pendientes
 *   de calificar)
 * - curso.programa + auditoria.programa_historial: Estado del syllabus
 * - administrativo.asignatura: Nombre de la asignatura del curso
 */
class CalendarioController extends Controller
{
    /**
     * Muestra el calendario del docente: fechas límite, sesiones de asistencia
     * e hitos de syllabus de todos sus cursos.
     */
    public function index()
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->docente) {
            return redirect()->route('dashboard')->with('error', 'No tienes un perfil docente asociado.');
        }

        $idDocente = $user->docente->id_docente;

        // Cursos donde el docente participa (titular del curso o de algún componente)
        $cursos = Curso::where(function ($q) use ($idDocente) {
                $q->where('id_docente_titular', $idDocente)
                    ->orWhereHas('componentes.docenteComponentes', function ($dq) use ($idDocente) {
                        $dq->where('id_docente', $idDocente);
                    });
            })
            ->whereNull('fecha_eliminacion')
            ->with([
                'asignacionPlan.asignatura',
                'componentes.tipoComponente',
                'componentes.actividades' => fn ($q) => $q->whereNotNull('fecha_limite'),
                'programas' => fn ($q) => $q->where('es_actual', true),
            ])
            ->orderBy('agno_real', 'desc')
            ->orderBy('semestre_real', 'desc')
            ->get();

        $cursosPayload = [];
        $eventos       = [];

        /** @var array<int,int> $cursoPorComponente id_componente => id_curso */
        $cursoPorComponente = [];
        /** @var array<int,string> $tipoPorComponente id_componente => tipo del componente */
        $tipoPorComponente = [];
        /** @var array<int,int> $totalActividadesPorCurso */
        $idsActividades = [];

        foreach ($cursos as $curso) {
            $asignatura = $curso->asignacionPlan?->asignatura?->nombre ?? ($curso->nombre ?? 'Curso');

            // Nombre del curso = nombre asignatura + año + semestre
            $nombreCurso = trim(sprintf(
                '%s %s-%s',
                $asignatura,
                $curso->agno_real ?? '',
                $curso->semestre_real ?? ''
            ));

            $totalActividades = 0;

            foreach ($curso->componentes as $componente) {
                $tipoComponente = $componente->tipoComponente?->tipo ?? 'General';

                $cursoPorComponente[$componente->id_componente] = $curso->id_curso;
                $tipoPorComponente[$componente->id_componente]  = $tipoComponente;

                foreach ($componente->actividades as $actividad) {
                    if (!$actividad->fecha_limite) {
                        continue;
                    }

                    $idsActividades[] = $actividad->id_actividad;

                    $eventos[] = [
                        'id_actividad'    => $actividad->id_actividad,
                        'id_curso'        => $curso->id_curso,
                        'titulo'          => $actividad->nombre,
                        'fecha'           => $actividad->fecha_limite->format('Y-m-d'),
                        'tipo_actividad'  => $actividad->tipo_actividad?->value ?? (string) $actividad->tipo_actividad,
                        'tipo_entrega'    => $actividad->tipo_entrega,
                        'es_grupal'       => (bool) $actividad->es_grupal,
                        'visible'         => (bool) $actividad->visible,
                        'componente'      => $tipoComponente,
                        'id_componente'   => $componente->id_componente,
                        // Se completan más abajo con un único agregado.
                        'grupos_total'    => 0,
                        'grupos_sin_nota' => 0,
                    ];

                    $totalActividades++;
                }
            }

            $cursosPayload[] = [
                'id_curso'          => $curso->id_curso,
                'nombre'            => $nombreCurso,
                'asignatura'        => $asignatura,
                'cod_curso'         => $curso->cod_curso,
                'letra_grupo'       => $curso->letra_grupo,
                'agno_real'         => $curso->agno_real,
                'semestre_real'     => $curso->semestre_real,
                'total_actividades' => $totalActividades,
                // Se completan al final, cuando ya están las otras dos familias.
                'total_sesiones'    => 0,
                'total_hitos'       => 0,
            ];
        }

        $eventos  = $this->anotarGruposPorCalificar($eventos, $idsActividades);
        $sesiones = $this->sesionesDeAsistencia($cursoPorComponente, $tipoPorComponente);
        $hitos    = $this->hitosDeSyllabus($cursos);

        // Contadores por curso de las familias que no se cuentan en el bucle.
        $sesionesPorCurso = array_count_values(array_column($sesiones, 'id_curso'));
        $hitosPorCurso    = array_count_values(array_column($hitos, 'id_curso'));

        foreach ($cursosPayload as $i => $fila) {
            $cursosPayload[$i]['total_sesiones'] = $sesionesPorCurso[$fila['id_curso']] ?? 0;
            $cursosPayload[$i]['total_hitos']    = $hitosPorCurso[$fila['id_curso']] ?? 0;
        }

        return Inertia::render('docente/Calendario', [
            'cursos'   => $cursosPayload,
            'eventos'  => $eventos,
            'sesiones' => $sesiones,
            'hitos'    => $hitos,
        ]);
    }

    /**
     * Añade a cada evento cuántos grupos tiene asignados la actividad y
     * cuántos siguen sin nota, con un único agregado sobre
     * agenda.actividad_asignada_grupo.
     *
     * Es lo más cercano a "cuánto queda por evaluar" que existe en el modelo:
     * el enum EstadoActividadAsignada sólo distingue PLANIFICADA/ACTIVA/CERRADA
     * y no registra la recepción de una entrega.
     *
     * @param  array<int,array<string,mixed>>  $eventos
     * @param  array<int,int>  $idsActividades
     * @return array<int,array<string,mixed>>
     */
    private function anotarGruposPorCalificar(array $eventos, array $idsActividades): array
    {
        if (empty($idsActividades)) {
            return $eventos;
        }

        $resumen = DB::table('agenda.actividad_asignada_grupo')
            ->whereIn('id_actividad', array_unique($idsActividades))
            ->groupBy('id_actividad')
            ->selectRaw('id_actividad, count(*) as total, sum(case when nota is null then 1 else 0 end) as sin_nota')
            ->get()
            ->keyBy('id_actividad');

        foreach ($eventos as $i => $evento) {
            $fila = $resumen->get($evento['id_actividad']);
            if (!$fila) {
                continue;
            }
            $eventos[$i]['grupos_total']    = (int) $fila->total;
            $eventos[$i]['grupos_sin_nota'] = (int) $fila->sin_nota;
        }

        return $eventos;
    }

    /**
     * Sesiones de asistencia ya tomadas, agrupadas por la sesión implícita
     * (componente, dia, hora_inicio, hora_fin) con su contador de presentes.
     *
     * Una sesión existe únicamente cuando hay filas en curso.asistencia: la
     * tabla no admite un estado "sin tomar" (esta_presente es NOT NULL) y no
     * hay ninguna tabla de horario que declare cuándo debería haber clase.
     *
     * @param  array<int,int>  $cursoPorComponente
     * @param  array<int,string>  $tipoPorComponente
     * @return array<int,array<string,mixed>>
     */
    private function sesionesDeAsistencia(array $cursoPorComponente, array $tipoPorComponente): array
    {
        $idsComponentes = array_keys($cursoPorComponente);

        if (empty($idsComponentes)) {
            return [];
        }

        $filas = DB::table('curso.asistencia as a')
            ->join(
                'curso.inscripcion_componente as ic',
                'ic.id_inscripcion_componente',
                '=',
                'a.id_inscripcion_componente'
            )
            ->whereIn('ic.id_componente', $idsComponentes)
            ->groupBy('ic.id_componente', 'a.dia', 'a.hora_inicio', 'a.hora_fin')
            ->orderBy('a.dia')
            ->orderBy('a.hora_inicio')
            ->selectRaw(
                'ic.id_componente,'
                . ' a.dia,'
                . ' a.hora_inicio,'
                . ' a.hora_fin,'
                . ' count(*) as total,'
                . ' sum(case when a.esta_presente then 1 else 0 end) as presentes'
            )
            ->get();

        $sesiones = [];

        foreach ($filas as $fila) {
            $idComponente = (int) $fila->id_componente;

            $sesiones[] = [
                'id_curso'      => $cursoPorComponente[$idComponente],
                'id_componente' => $idComponente,
                'componente'    => $tipoPorComponente[$idComponente] ?? 'General',
                'fecha'         => Carbon::parse($fila->dia)->format('Y-m-d'),
                'hora_inicio'   => substr((string) $fila->hora_inicio, 0, 5),
                'hora_fin'      => substr((string) $fila->hora_fin, 0, 5),
                'presentes'     => (int) $fila->presentes,
                'total'         => (int) $fila->total,
            ];
        }

        return $sesiones;
    }

    /**
     * Hitos de syllabus con fecha propia.
     *
     * curso.programa NO tiene ninguna columna de fecha (y $timestamps = false),
     * así que las únicas fechas reales son:
     * - curso.fecha_limite_entrega_basico     → hito "datos básicos"
     * - curso.fecha_limite_entrega_syllabus   → hito "syllabus"
     * - auditoria.programa_historial.fecha_accion, para las acciones que
     *   escriben los flujos de revisión ('APROBACION' / 'RECHAZO'), con
     *   observaciones como razón del rechazo.
     *
     * @param  \Illuminate\Support\Collection<int,Curso>  $cursos
     * @return array<int,array<string,mixed>>
     */
    private function hitosDeSyllabus($cursos): array
    {
        $hitos = [];

        /** @var array<int,int> $cursoPorPrograma */
        $cursoPorPrograma = [];

        foreach ($cursos as $curso) {
            if ($curso->fecha_limite_entrega_basico) {
                $hitos[] = [
                    'id'       => "basico-{$curso->id_curso}",
                    'id_curso' => $curso->id_curso,
                    'fecha'    => $curso->fecha_limite_entrega_basico->format('Y-m-d'),
                    'tipo'     => 'LIMITE_BASICO',
                    'titulo'   => 'Límite de datos básicos',
                    'detalle'  => null,
                ];
            }

            if ($curso->fecha_limite_entrega_syllabus) {
                $hitos[] = [
                    'id'       => "syllabus-{$curso->id_curso}",
                    'id_curso' => $curso->id_curso,
                    'fecha'    => $curso->fecha_limite_entrega_syllabus->format('Y-m-d'),
                    'tipo'     => 'LIMITE_SYLLABUS',
                    'titulo'   => 'Límite de entrega del syllabus',
                    'detalle'  => null,
                ];
            }

            foreach ($curso->programas as $programa) {
                $cursoPorPrograma[$programa->id_programa] = $curso->id_curso;
            }
        }

        if (!empty($cursoPorPrograma)) {
            $historial = ProgramaHistorial::whereIn('id_programa', array_keys($cursoPorPrograma))
                ->whereIn('accion', ['APROBACION', 'RECHAZO'])
                ->orderBy('fecha_accion')
                ->get();

            foreach ($historial as $evento) {
                $esRechazo = $evento->accion === 'RECHAZO';

                $hitos[] = [
                    'id'       => "historial-{$evento->id_log}",
                    'id_curso' => $cursoPorPrograma[$evento->id_programa],
                    'fecha'    => Carbon::parse($evento->fecha_accion)->format('Y-m-d'),
                    'tipo'     => $esRechazo ? 'RECHAZO' : 'APROBACION',
                    'titulo'   => $esRechazo ? 'Syllabus rechazado' : 'Syllabus aprobado',
                    'detalle'  => $evento->observaciones,
                ];
            }
        }

        return $hitos;
    }
}
