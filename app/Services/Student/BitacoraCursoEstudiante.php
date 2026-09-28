<?php

namespace App\Services\Student;

use App\Enums\DB\TipoMensaje;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Curso;
use App\Models\Usuario\Estudiante;

/**
 * Bitácora del curso para el alumno (T41): todas las agendas de sus
 * actividades del curso —entregas, mensajes, retroalimentaciones,
 * evaluaciones— en un solo flujo cronológico.
 *
 * Cada agenda cuelga de un `id_actividad_asignada_grupo`; aquí se juntan las
 * de todos los grupos del alumno en actividades visibles de ese curso. Es lo
 * mismo que ya ve dentro de cada actividad, sólo que reunido.
 */
class BitacoraCursoEstudiante
{
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

        if ($grupos->isEmpty()) {
            return ['entradas' => [], 'actividades' => [], 'componentes' => []];
        }

        $agendas = Agenda::whereIn('id_actividad_asignada_grupo', $grupos->keys())
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
        })->values();

        $actividades = $grupos
            ->map(fn ($g) => ['id' => $g->actividad->id_actividad, 'nombre' => $g->actividad->nombre])
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
            ->values()
            ->all();

        return [
            'entradas' => $entradas->all(),
            'actividades' => $actividades,
            'componentes' => $componentes,
        ];
    }
}
