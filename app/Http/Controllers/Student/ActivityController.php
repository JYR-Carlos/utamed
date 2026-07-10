<?php

namespace App\Http\Controllers\Student;

use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Services\Agenda\GrupoIndividualService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Controlador para que el estudiante vea sus actividades en un curso.
 * 
 * Tablas involucradas:
 * - agenda.actividad: Definición de la actividad (visible, fecha_límite, tipo, etc.)
 * - agenda.actividad_asignada: Grupo asignado (nota grupal, estado)
 * - agenda.asignado_actividad: Nota individual del estudiante
 * - curso.inscripcion_componente: Para saber a qué componentes pertenece el alumno
 * - curso.componente: Componente al que pertenece la actividad
 */
class ActivityController extends Controller
{

    public function show(Curso $curso, Actividad $actividad)
    {
        /** @var Usuario $user */
        $user = Auth::user();

        if (!$user->estudiante) {
            return redirect('/dashboard');
        }

        $estudiante = $user->estudiante;

        // Verificar inscripción al curso
        $inscrito = $estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->first();

        if (!$inscrito) {
            abort(403, 'No estás inscrito en este curso.');
        }

        if (!$actividad->visible) {
            abort(403, 'Esta actividad no está disponible.');
        }

        $actividad->load(['componente.tipoComponente', 'unidad', 'archivo']);

        // Las actividades individuales no pasan por el flujo manual de creación
        // de grupos: si el estudiante todavía no tiene su grupo de 1 integrante
        // (actividad creada antes de este fix, o inscripción posterior), se crea
        // aquí de forma perezosa (ver GrupoIndividualService).
        if (!$actividad->es_grupal) {
            (new GrupoIndividualService())->asegurarGrupo($actividad, $estudiante->id_estudiante);
        }

        // Buscar el grupo del estudiante para esta actividad
        $integranteGrupo = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('actividadAsignadaGrupo', fn($q) => $q->where('id_actividad', $actividad->id_actividad))
            ->with('actividadAsignadaGrupo')
            ->first();

        $grupo = $integranteGrupo?->actividadAsignadaGrupo;

        $restoIntegrantes = $grupo
            ? IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
                ->where('id_estudiante', '!=', $estudiante->id_estudiante)
                ->get()
                ->map(function (IntegranteGrupo $i) {
                    return [
                        'id_estudiante' => $i->estudiante->id_estudiante,
                        'nombre1' => $i->estudiante->usuario->nombre1,
                        'nombre2' => $i->estudiante->usuario->nombre2,
                        'apellido1' => $i->estudiante->usuario->apellido1,
                        'apellido2' => $i->estudiante->usuario->apellido2,
                        'email' => $i->estudiante->usuario->email,
                    ];
                })
            : collect();

        
        $ultimaNota = $integranteGrupo?->evaluacion->nota_obtenida ?? $grupo?->nota;
        
        $interacciones = [];
        $ultimaEntrega = null;
        $rubricaUltimaEvaluacion = null;

        if ($grupo) {
            $agendas = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
                ->with(['usuario.docente', 'evaluacion.rubrica'])
                ->orderBy('fecha_envio', 'asc')
                ->get();

            $interacciones = $agendas->map(function (Agenda $agenda) use ($user) {
                $evaluacion = $agenda->evaluacion;
                $rubricaData = $evaluacion?->rubrica?->rubrica;

                $nombreEmisor = trim(
                    ($agenda->usuario?->nombre1 ?? '') . ' ' .
                    ($agenda->usuario?->apellido1 ?? '') . ' ' .
                    ($agenda->usuario?->apellido2 ?? '')
                );
                if (empty($nombreEmisor)) {
                    $nombreEmisor = 'Sistema';
                }

                return [
                    'id_interaccion'     => $agenda->id_agenda,
                    'fecha_emision'      => (string) $agenda->fecha_envio,
                    'tipo_interaccion'   => $agenda->tipo_mensaje->value,
                    'emisor'             => $nombreEmisor,
                    'mensaje'            => $agenda->mensaje ?? '',
                    'es_de_docente'      => $agenda->usuario?->docente !== null,
                    'es_retroalimentacion' => in_array($agenda->tipo_mensaje, [TipoMensaje::FEEDBACK, TipoMensaje::EVALUACIÓN]),
                    'adjunta_rubrica'    => $rubricaData !== null,
                    'rubrica'            => $rubricaData,
                    'puntaje_obtenido'   => $evaluacion?->puntaje_obtenido,
                    'resultado'          => $evaluacion?->resultado,
                ];
            })->values()->toArray();

            // Rúbrica usada en la evaluación más reciente (no la última rúbrica creada)
            $ultimaEvaluacionAgenda = $agendas->reverse()->first(fn (Agenda $a) => $a->evaluacion !== null);
            $rubricaUltimaEvaluacion = $ultimaEvaluacionAgenda?->evaluacion?->rubrica;

            // obtiene la ultima entrega del estudiante
            foreach (array_reverse($interacciones) as $item) {
                if ($item['tipo_interaccion'] === "Entrega de avance") {
                    $ultimaEntrega = $item;
                    break;
                }
            }

        }
        // Si ya hay una evaluación, se muestra la rúbrica que efectivamente se usó en ella;
        // si aún no hay evaluación, se muestra sólo la cabecera de la rúbrica vigente.
        $rubrica = $rubricaUltimaEvaluacion
            ?? Rubrica::where('id_actividad', '=', $actividad->id_actividad)
                ->orderByDesc('id_rubrica')->first();

        // Derivar estado legible para el estudiante
        $estado = $grupo?->estado_actividad_asignada?->value;

        // Entregas del estudiante (archivos enviados)
        $entradas = collect($interacciones)
            ->filter(fn($i) => $i['tipo_interaccion'] === TipoMensaje::ENTREGA_DE_ARCHIVO->value)
            ->map(fn($i) => ['id' => $i['id_interaccion']])
            ->values()
            ->toArray();

        $curso->load(['asignacionPlan.asignatura']);
        


        return Inertia::render('student/Activities/Index', [
            'cod_curso'             => $curso->cod_curso,
            'nombre_curso'          => $curso->asignacionPlan?->asignatura?->nombre ?? $curso->nombre,
            'cod_actividad'         => (string) $actividad->id_actividad,
            'nombre_actividad'      => $actividad->nombre ?? '',
            'descripcion'           => '', 
            'dias_holgura'          => $actividad->nro_dias_adicionales_para_bloqueo,
            'fecha_limite'          => $actividad->fecha_limite ? (string) $actividad->fecha_limite : '',
            'es_sumativa'           => $actividad->tipo_actividad === TipoActividad::SUMATIVA,
            'trae_archivo'          => $actividad->uuid_archivo !== null,
            'entrega_obligatoria'   => strtolower($actividad->tipo_entrega ?? '') !== 'sin entrega',
            'ultima_nota'           => $ultimaNota !== null ? (float) $ultimaNota : null,
            'ultima_evaluacion'     => null,
            'ultima_entrega'        => $ultimaEntrega,
            'estado'                => $estado,
            'entradas'              => $entradas,
            'listado_interacciones' => $interacciones,
            'rubrica'               => $rubrica,
            'id_actividad_asignada_grupo' => $grupo?->id_actividad_asignada_grupo,
            'resto_integrantes'     => $restoIntegrantes
        ]);
    }
}


