<?php

namespace App\Http\Controllers\Student;

use App\Enums\DB\EstadoActividadAsignada;
use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Http\Controllers\Controller;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\AsignadoActividad;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Buscar el grupo del estudiante para esta actividad
        $integranteGrupo = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('actividadAsignadaGrupo', fn($q) => $q->where('id_actividad', $actividad->id_actividad))
            ->with('actividadAsignadaGrupo')
            ->first();

        $grupo = $integranteGrupo?->actividadAsignadaGrupo;

        $ultimaNota = $integranteGrupo?->nota_individual ?? $grupo?->nota;

        $interacciones = [];
        

        if ($grupo) {
            $agendas = Agenda::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
                ->with(['usuario', 'evaluacion.rubrica'])
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
                    'es_de_docente'      => $agenda->id_usuario_emisor !== $user->id_usuario,
                    'es_retroalimentacion' => in_array($agenda->tipo_mensaje, [TipoMensaje::FEEDBACK, TipoMensaje::EVALUACIÓN]),
                    'adjunta_rubrica'    => $rubricaData !== null,
                    'rubrica'            => $rubricaData,
                    'puntaje_obtenido'   => $evaluacion?->puntaje_obtenido,
                ];
            })->values()->toArray();

            // Obtener la rúbrica de la última evaluación
            foreach (array_reverse($interacciones) as $item) {
                if ($item['rubrica'] !== null) {
                    $rubrica = $item['rubrica'];
                    break;
                }
            }
        }

        $rubrica = Rubrica::where('id_actividad','=', $actividad->id_actividad)
         ->orderByDesc('id_rubrica')->first();

        // Derivar estado legible para el estudiante
        $estado = $grupo?->estado_actividad_asignada; //EstadoActividadAsignada::PLANIFICADA->value;

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
            'fecha_limite'          => $actividad->fecha_limite ? (string) $actividad->fecha_limite : '',
            'es_sumativa'           => $actividad->tipo_actividad === TipoActividad::SUMATIVA,
            'trae_archivo'          => $actividad->uuid_archivo !== null,
            'entrega_obligatoria'   => strtolower($actividad->tipo_entrega ?? '') !== 'sin entrega',
            'ultima_nota'           => $ultimaNota,
            'ultimo_estado'         => $estado,
            'entradas'              => $entradas,
            'listado_interacciones' => $interacciones,
            'rubrica_evaluada'      => $rubrica,
            'rubrica'               => $rubrica,
            'id_actividad_asignada_grupo' => $grupo?->id_actividad_asignada_grupo,
        ]);
    }
}


