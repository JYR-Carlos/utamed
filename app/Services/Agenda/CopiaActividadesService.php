<?php

namespace App\Services\Agenda;

use App\Enums\DB\EstadoRubrica;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copia actividades de un curso a sus cursos hermanos: los otros grupos (A, B…)
 * de la misma asignatura, en el mismo año y semestre, con el mismo docente
 * titular. Evita que el docente cree dos veces la misma actividad.
 *
 * Decisiones del equipo (2026-10-07):
 *  - Sólo el docente titular copia, y sólo a cursos donde también es titular.
 *  - La copia llega oculta (Planificada) y con la fecha que elija el docente.
 *  - El enunciado se comparte (mismo uuid_archivo): reemplazarlo en un curso
 *    sube un archivo nuevo para ese curso y no toca al otro, porque
 *    subirEnunciado sólo cambia el uuid de la actividad y nunca borra el
 *    archivo anterior.
 *  - La rúbrica se copia como fila independiente (POSTULADA), así cada curso
 *    la puede editar o bloquear por su cuenta.
 *  - Si el curso de destino no tiene el componente (mismo tipo) o la unidad
 *    (mismo número), esa actividad no se copia ahí y se avisa por qué.
 *  - No se copian grupos, entregas, notas ni mensajes: son de los estudiantes
 *    de cada curso. Las actividades individuales reciben sus grupos de 1
 *    (GrupoIndividualService), igual que al crearlas a mano.
 */
class CopiaActividadesService
{
    public function __construct(private GrupoIndividualService $gruposIndividuales = new GrupoIndividualService())
    {
    }

    /** Cursos hermanos del curso de origen, ordenados por letra de grupo. */
    public function cursosHermanos(Curso $origen): Collection
    {
        $idAsignatura = $origen->asignacionPlan?->id_asignatura;
        if (!$idAsignatura || !$origen->id_docente_titular) {
            return collect();
        }

        return Curso::query()
            ->where('id_curso', '!=', $origen->id_curso)
            ->where('es_plantilla', false)
            ->where('id_docente_titular', $origen->id_docente_titular)
            ->where('agno_real', $origen->agno_real)
            ->where('semestre_real', $origen->semestre_real)
            ->whereHas('asignacionPlan', fn ($q) => $q->where('id_asignatura', $idAsignatura))
            ->with('asignacionPlan.asignatura')
            ->orderBy('letra_grupo')
            ->get();
    }

    /**
     * Qué actividades se pueden copiar a cada destino y por qué no las demás.
     *
     * @param  Collection<int, Actividad>  $actividades  con componente y unidad cargados
     * @param  Collection<int, Curso>  $destinos
     * @return array<int, array{id_curso:int, etiqueta:string, cod_curso:string, fecha_inicio:?string, fecha_fin:?string, actividades: array<int, array{copiable:bool, motivo:?string, nombre_repetido:bool}>}>
     */
    public function compatibilidad(Collection $actividades, Collection $destinos): array
    {
        return $destinos->map(function (Curso $destino) use ($actividades) {
            [$componentes, $unidades] = $this->estructuraDestino($destino);
            $nombres = Actividad::whereIn('id_componente', $componentes->values())
                ->pluck('nombre')
                ->map(fn ($n) => mb_strtolower(trim((string) $n)))
                ->flip();

            return [
                'id_curso'    => $destino->id_curso,
                'etiqueta'    => $destino->etiqueta(),
                'cod_curso'   => (string) $destino->cod_curso,
                // Período del curso: la ventana avisa si una fecha cae fuera
                // (p. ej. un año equivocado al cambiarla).
                'fecha_inicio' => $destino->fecha_inicio?->format('Y-m-d'),
                'fecha_fin'    => $destino->fecha_fin?->format('Y-m-d'),
                'actividades' => $actividades->mapWithKeys(fn (Actividad $a) => [
                    $a->id_actividad => [
                        'copiable'        => ($motivo = $this->motivoNoCopiable($a, $componentes, $unidades)) === null,
                        'motivo'          => $motivo,
                        'nombre_repetido' => $nombres->has(mb_strtolower(trim((string) $a->nombre))),
                    ],
                ])->all(),
            ];
        })->values()->all();
    }

    /**
     * Copia las actividades a los destinos. Lo que no tiene dónde caer se omite
     * (no aborta el resto) y vuelve en `omitidas`.
     *
     * @param  Collection<int, Actividad>  $actividades  con componente y unidad cargados
     * @param  Collection<int, Curso>  $destinos
     * @param  array<int, string|null>  $fechas  id_actividad => 'Y-m-d' (si falta, la de origen)
     * @return array{creadas: list<array{id_actividad:int, id_curso:int}>, omitidas: list<array{nombre:string, curso:string, motivo:string}>}
     */
    public function copiar(
        Collection $actividades,
        Collection $destinos,
        bool $conRubrica,
        bool $conEnunciado,
        array $fechas = [],
    ): array {
        $creadas = [];
        $omitidas = [];

        DB::transaction(function () use ($actividades, $destinos, $conRubrica, $conEnunciado, $fechas, &$creadas, &$omitidas) {
            $rubricas = $conRubrica
                ? Rubrica::whereIn('id_actividad', $actividades->pluck('id_actividad'))
                    ->orderByDesc('id_rubrica')
                    ->get()
                    ->unique('id_actividad')
                    ->keyBy('id_actividad')
                : collect();

            foreach ($destinos as $destino) {
                [$componentes, $unidades] = $this->estructuraDestino($destino);

                foreach ($actividades as $origen) {
                    $motivo = $this->motivoNoCopiable($origen, $componentes, $unidades);
                    if ($motivo !== null) {
                        $omitidas[] = ['nombre' => $origen->nombre, 'curso' => $destino->etiqueta(), 'motivo' => $motivo];
                        continue;
                    }

                    $copia = Actividad::create([
                        'nombre'                            => $origen->nombre,
                        'fecha_limite'                      => $fechas[$origen->id_actividad] ?? $origen->fecha_limite,
                        'nro_dias_adicionales_para_bloqueo' => $origen->nro_dias_adicionales_para_bloqueo,
                        // Llega oculta: el docente la revisa y la publica en cada curso.
                        'visible'                           => false,
                        'ponderacion'                       => $origen->ponderacion,
                        'exigencia'                         => $origen->exigencia,
                        'tipo_actividad'                    => $origen->tipo_actividad?->value ?? $origen->tipo_actividad,
                        'tipo_entrega'                      => $origen->tipo_entrega,
                        'es_grupal'                         => $origen->es_grupal,
                        'max_integrantes'                   => $origen->max_integrantes,
                        'es_plantilla'                      => false,
                        'id_componente'                     => $componentes[$origen->componente->id_tipo_componente],
                        'id_unidad'                         => $unidades[$origen->unidad->num_unidad],
                        'uuid_archivo_enunciado'            => $conEnunciado ? $origen->uuid_archivo_enunciado : null,
                    ]);

                    if ($rubrica = $rubricas->get($origen->id_actividad)) {
                        Rubrica::create([
                            'rubrica'        => $rubrica->rubrica,
                            'estado_rubrica' => EstadoRubrica::POSTULADA,
                            'id_actividad'   => $copia->id_actividad,
                        ]);
                    }

                    if (!$copia->es_grupal) {
                        $this->gruposIndividuales->asegurarGruposDelCurso($destino, $copia);
                    }

                    $creadas[] = ['id_actividad' => $copia->id_actividad, 'id_curso' => $destino->id_curso];
                }
            }
        });

        Log::info('[CopiaActividades] Copia realizada', [
            'actividades' => $actividades->pluck('id_actividad')->all(),
            'destinos'    => $destinos->pluck('id_curso')->all(),
            'creadas'     => count($creadas),
            'omitidas'    => count($omitidas),
        ]);

        return ['creadas' => $creadas, 'omitidas' => $omitidas];
    }

    /**
     * Componentes del destino por id_tipo_componente y unidades por num_unidad.
     *
     * @return array{0: Collection<int, int>, 1: Collection<int, int>}
     */
    private function estructuraDestino(Curso $destino): array
    {
        $componentes = Componente::where('id_curso', $destino->id_curso)
            ->pluck('id_componente', 'id_tipo_componente');
        $unidades = Unidad::where('id_curso', $destino->id_curso)
            ->pluck('id_unidad', 'num_unidad');

        return [$componentes, $unidades];
    }

    private function motivoNoCopiable(Actividad $a, Collection $componentes, Collection $unidades): ?string
    {
        $tipo = $a->componente?->id_tipo_componente;
        if ($tipo === null || !$componentes->has($tipo)) {
            $nombre = $a->componente?->tipoComponente?->tipo ?? 'de la actividad';
            return "El curso no tiene el componente {$nombre}.";
        }

        $num = $a->unidad?->num_unidad;
        if ($num === null || !$unidades->has($num)) {
            return $num === null
                ? 'La actividad no tiene unidad.'
                : "El curso no tiene la unidad {$num}.";
        }

        return null;
    }
}
