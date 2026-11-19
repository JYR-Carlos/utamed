<?php

namespace App\Services\Student;

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Curso;
use App\Models\Usuario\Estudiante;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Bitácora del curso para el alumno (T41): todas las agendas de sus
 * actividades del curso —entregas, mensajes, retroalimentaciones,
 * evaluaciones— en un solo flujo cronológico.
 *
 * Cada agenda cuelga de un `id_actividad_asignada_grupo`; aquí se juntan las
 * de todos los grupos del alumno en actividades visibles de ese curso. Es lo
 * mismo que ya ve dentro de cada actividad, sólo que reunido.
 *
 * Un plazo que vence sin entrega no deja fila en la agenda, así que se suma
 * calculado desde las actividades (OBS-55): sin esto, un curso con entregas
 * atrasadas decía «Aún no hay actividad registrada». No se guarda nada.
 */
class BitacoraCursoEstudiante
{
    public function __construct(private readonly ResumenActividadesEstudiante $resumen)
    {
    }

    /**
     * @return array{entradas: list<array<string, mixed>>, actividades: list<array{id: int, nombre: string}>, componentes: list<array{id: int, tipo: string}>}
     */
    public function compilar(Curso $curso, Estudiante $estudiante): array
    {
        $integraciones = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('actividadAsignadaGrupo.actividad', fn ($q) => $q
                ->where('visible', true)
                ->whereHas('componente', fn ($c) => $c->where('id_curso', $curso->id_curso)))
            ->with('actividadAsignadaGrupo.actividad.componente.tipoComponente')
            ->get();

        $grupos = $integraciones->pluck('actividadAsignadaGrupo')->filter()->keyBy('id_actividad_asignada_grupo');
        $plazos = $this->plazosVencidos($curso, $estudiante);

        if ($grupos->isEmpty() && $plazos->isEmpty()) {
            return ['entradas' => [], 'actividades' => [], 'componentes' => []];
        }

        $agendas = $grupos->isEmpty()
            ? collect()
            : Agenda::whereIn('id_actividad_asignada_grupo', $grupos->keys())
                ->with(['usuario.docente', 'evaluacion', 'archivo'])
                ->orderByDesc('fecha_envio')
                ->orderByDesc('id_agenda')
                ->get();

        $entradas = $agendas->map(function (Agenda $agenda) use ($grupos, $estudiante) {
            $actividad = $grupos->get($agenda->id_actividad_asignada_grupo)->actividad;
            $componente = $actividad->componente;
            $usuario = $agenda->usuario;
            $esEntrega = $agenda->tipo_mensaje === TipoMensaje::ENTREGA_DE_ARCHIVO;

            return [
                'clave' => "agenda-{$agenda->id_agenda}",
                'id_agenda' => $agenda->id_agenda,
                'fecha' => (string) $agenda->fecha_envio,
                'tipo' => $agenda->tipo_mensaje->value,
                'mensaje' => $agenda->mensaje ?? '',
                'emisor' => $usuario
                    ? trim("{$usuario->nombre1} {$usuario->apellido1}")
                    : 'Sistema',
                'es_propio' => $usuario !== null && $usuario->id_usuario === $estudiante->id_usuario,
                'es_de_docente' => $usuario?->docente !== null,
                'archivo' => $esEntrega && $agenda->archivo
                    ? $agenda->archivo->nombre_original
                    : null,
                'resultado' => $agenda->evaluacion?->evaluacion_obtenida,
                'actividad' => [
                    'id' => $actividad->id_actividad,
                    'nombre' => $actividad->nombre,
                ],
                'componente' => [
                    'id' => $componente?->id_componente,
                    'tipo' => $componente?->tipoComponente?->tipo,
                ],
            ];
        })
            ->concat($plazos)
            ->sortByDesc('fecha')
            ->values();

        $actividades = $grupos
            ->map(fn ($g) => ['id' => $g->actividad->id_actividad, 'nombre' => $g->actividad->nombre])
            ->concat($plazos->pluck('actividad'))
            ->unique('id')
            ->sortBy('nombre')
            ->values()
            ->all();

        $componentes = $grupos
            ->map(fn ($g) => $g->actividad->componente)
            ->filter()
            ->unique('id_componente')
            ->pipe(fn ($c) => \App\Models\Curso\TipoComponente::ordenarCTL($c))
            ->map(fn ($c) => ['id' => $c->id_componente, 'tipo' => $c->tipoComponente?->tipo ?? 'Componente'])
            ->concat($plazos->pluck('componente')->filter(fn ($c) => $c['id'] !== null))
            ->unique('id')
            ->values()
            ->all();

        return [
            'entradas' => $entradas->all(),
            'actividades' => $actividades,
            'componentes' => $componentes,
        ];
    }

    /**
     * Plazos vencidos sin entrega, como entradas de la bitácora. Mismo
     * criterio que las atrasadas del inicio: actividades con entrega, sin
     * entrega vigente ni evaluación, cuyo día límite ya terminó.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function plazosVencidos(Curso $curso, Estudiante $estudiante): Collection
    {
        $hoy = now()->startOfDay();

        return $this->resumen->delCurso($estudiante, $curso)
            ->filter(fn (array $a) => $a['con_entrega']
                && !$a['ya_entregada']
                && !$a['ya_evaluada']
                && $a['fecha_limite'] !== null
                && Carbon::parse($a['fecha_limite'])->lt($hoy))
            ->map(fn (array $a) => [
                'clave' => "plazo-{$a['id_actividad']}",
                'id_agenda' => null,
                'fecha' => Carbon::parse($a['fecha_limite'])->endOfDay()->format('Y-m-d H:i:s'),
                'tipo' => self::PLAZO_VENCIDO,
                'mensaje' => '',
                'emisor' => '',
                'es_propio' => false,
                'es_de_docente' => false,
                'archivo' => null,
                'resultado' => null,
                'actividad' => ['id' => $a['id_actividad'], 'nombre' => $a['nombre']],
                'componente' => ['id' => $a['id_componente'], 'tipo' => $a['componente_tipo']],
            ])
            ->values();
    }

    /** Tipo de las entradas calculadas: no es un tipo_mensaje de la agenda. */
    public const PLAZO_VENCIDO = 'Plazo vencido';
}
