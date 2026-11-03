<?php

namespace App\Services\Student;

use App\Enums\DB\TipoActividad;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Estudiante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rendimiento del alumno en un curso (T30), por componente.
 *
 * Reglas (reunión del 23-09 y respuesta del 28-09):
 * - Promedio del alumno en un componente: promedio ponderado de sus notas ya
 *   registradas en actividades sumativas visibles, con la `ponderacion` de
 *   cada actividad, normalizado sobre lo ya evaluado. La nota es la individual
 *   (grupal + décimas) y, si no la hay, la grupal.
 * - Promedio del curso en un componente: promedio de los promedios de cada
 *   alumno de ese componente que ya tiene alguna nota. Sólo sale el número
 *   agregado, nunca datos de otro alumno.
 * - Asistencia mínima: 70 % en todos los componentes.
 * - El total del curso combina los componentes según el % del syllabus
 *   (sección IX), sólo entre los que ya tienen promedio.
 */
class RendimientoCursoEstudiante
{
    /** Asistencia mínima exigida, igual para todos los componentes. */
    public const ASISTENCIA_MINIMA = 70;

    /**
     * @param  array<int, array{componente: string, porcentaje: int|float|string, aprobacion_obligatoria?: bool}>  $componentesSyllabus
     */
    public function calcular(Curso $curso, Estudiante $estudiante, array $componentesSyllabus = []): array
    {
        $componentes = Componente::where('id_curso', $curso->id_curso)
            ->whereHas('inscripcionComponentes', fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante))
            ->with('tipoComponente')
            ->get();
        $componentes = TipoComponente::ordenarCTL($componentes);

        $syllabusPorTipo = collect($componentesSyllabus)
            ->keyBy(fn ($c) => mb_strtoupper(trim((string) ($c['componente'] ?? ''))));

        $filas = $componentes->map(function (Componente $componente) use ($estudiante, $syllabusPorTipo) {
            $tipo = $componente->tipoComponente?->tipo ?? 'Componente';
            $syllabus = $syllabusPorTipo->get(mb_strtoupper(trim($tipo)));

            $sumativas = Actividad::where('id_componente', $componente->id_componente)
                ->where('visible', true)
                ->where('tipo_actividad', TipoActividad::SUMATIVA->value)
                ->get(['id_actividad', 'ponderacion']);

            $notas = $this->notasPorEstudiante($sumativas->pluck('id_actividad'));
            $ponderaciones = $sumativas->pluck('ponderacion', 'id_actividad')->map(fn ($p) => (float) $p);

            $mias = $notas->get($estudiante->id_estudiante, collect());
            $miPromedio = $this->promedioPonderado($mias, $ponderaciones);

            $promediosCurso = $notas
                ->map(fn (Collection $n) => $this->promedioPonderado($n, $ponderaciones))
                ->filter(fn ($p) => $p !== null);

            $ponderacionTotal = $ponderaciones->sum();
            $ponderacionEvaluada = $ponderaciones->only($mias->keys()->all())->sum();

            return [
                'id_componente' => $componente->id_componente,
                'tipo' => $tipo,
                'mi_promedio' => $miPromedio,
                'promedio_curso' => $promediosCurso->isNotEmpty() ? round($promediosCurso->avg(), 1) : null,
                'actividades_sumativas' => $sumativas->count(),
                'actividades_evaluadas' => $mias->count(),
                'ponderacion_evaluada' => $ponderacionTotal > 0 ? round($ponderacionEvaluada / $ponderacionTotal * 100) : null,
                'asistencia' => $this->asistencia($componente, $estudiante),
                'syllabus' => [
                    'porcentaje' => isset($syllabus['porcentaje']) ? (float) $syllabus['porcentaje'] : null,
                    'aprobacion_obligatoria' => (bool) ($syllabus['aprobacion_obligatoria'] ?? false),
                ],
                'exigencia' => $componente->porcentaje_aprobacion !== null ? (float) $componente->porcentaje_aprobacion : null,
            ];
        })->values();

        return [
            'asistencia_minima' => self::ASISTENCIA_MINIMA,
            'mi_promedio' => $this->combinar($filas, 'mi_promedio'),
            'promedio_curso' => $this->combinar($filas, 'promedio_curso'),
            'componentes' => $filas->all(),
        ];
    }

    /**
     * Notas registradas por alumno y actividad:
     * [id_estudiante => [id_actividad => nota]].
     *
     * @param  Collection<int, int>  $idsActividad
     * @return Collection<int, Collection<int, float>>
     */
    private function notasPorEstudiante(Collection $idsActividad): Collection
    {
        if ($idsActividad->isEmpty()) {
            return collect();
        }

        return IntegranteGrupo::query()
            ->join('agenda.actividad_asignada_grupo as g', 'g.id_actividad_asignada_grupo', '=', 'integrante_grupo.id_actividad_asignada_grupo')
            ->whereIn('g.id_actividad', $idsActividad)
            ->where(fn ($q) => $q->whereNotNull('integrante_grupo.nota_individual')->orWhereNotNull('g.nota'))
            ->get([
                'integrante_grupo.id_estudiante',
                'g.id_actividad',
                DB::raw('COALESCE(integrante_grupo.nota_individual, g.nota) AS nota'),
            ])
            ->groupBy('id_estudiante')
            ->map(fn ($filas) => $filas->mapWithKeys(fn ($f) => [(int) $f->id_actividad => (float) $f->nota]));
    }

    /**
     * Promedio ponderado de las notas dadas, normalizado sobre las
     * ponderaciones de las actividades evaluadas. Si ninguna tiene
     * ponderación, promedio simple.
     *
     * @param  Collection<int, float>  $notas  id_actividad => nota
     * @param  Collection<int, float>  $ponderaciones  id_actividad => ponderación
     */
    public function promedioPonderado(Collection $notas, Collection $ponderaciones): ?float
    {
        if ($notas->isEmpty()) {
            return null;
        }

        $pesos = $notas->keys()->mapWithKeys(fn ($id) => [$id => $ponderaciones->get($id, 0.0)]);
        $sumaPesos = $pesos->sum();

        $promedio = $sumaPesos > 0
            ? $notas->map(fn ($nota, $id) => $nota * $pesos[$id])->sum() / $sumaPesos
            : $notas->avg();

        return round($promedio, 1);
    }

    /** @return array{presentes: int, total: int, porcentaje: ?int} */
    private function asistencia(Componente $componente, Estudiante $estudiante): array
    {
        $fila = DB::table('curso.asistencia as a')
            ->join('curso.inscripcion_componente as ic', 'ic.id_inscripcion_componente', '=', 'a.id_inscripcion_componente')
            ->where('ic.id_componente', $componente->id_componente)
            ->where('ic.id_estudiante', $estudiante->id_estudiante)
            ->selectRaw('COUNT(*) AS total, COUNT(*) FILTER (WHERE a.esta_presente) AS presentes')
            ->first();

        $total = (int) ($fila->total ?? 0);
        $presentes = (int) ($fila->presentes ?? 0);

        return [
            'presentes' => $presentes,
            'total' => $total,
            'porcentaje' => $total > 0 ? (int) round($presentes / $total * 100) : null,
        ];
    }

    /**
     * Combina un promedio por componente según el % del syllabus, entre los
     * componentes que ya lo tienen. Con un solo componente es ese mismo valor.
     */
    private function combinar(Collection $filas, string $campo): ?float
    {
        $conValor = $filas->filter(fn ($f) => $f[$campo] !== null);
        if ($conValor->isEmpty()) {
            return null;
        }

        $pesos = $conValor->map(fn ($f) => $f['syllabus']['porcentaje'] ?? 0);
        $suma = $pesos->sum();

        $valor = $suma > 0
            ? $conValor->map(fn ($f, $i) => $f[$campo] * $pesos[$i])->sum() / $suma
            : $conValor->avg($campo);

        return round($valor, 1);
    }

    /**
     * Calcula el resumen de rendimiento individual y asistencia del alumno para la API móvil.
     *
     * @param  array<int, array{componente: string, porcentaje: int|float|string, aprobacion_obligatoria?: bool}>|null  $componentesSyllabus
     */
    public function calcularIndividual(Curso $curso, Estudiante $estudiante, ?array $componentesSyllabus = null): array
    {
        $this->asegurarInscripcionActiva($curso, $estudiante);

        // Si no se pasaron las ponderaciones del syllabus, obtenerlas vía presenter
        if ($componentesSyllabus === null) {
            $syllabus = StudentSyllabusPresenter::build($curso, $estudiante);
            $componentesSyllabus = $syllabus['datos']['componentes'] ?? [];
        }

        $componentes = Componente::where('id_curso', $curso->id_curso)
            ->whereHas('inscripcionComponentes', fn ($q) => $q->where('id_estudiante', $estudiante->id_estudiante))
            ->with('tipoComponente')
            ->get();

        // Fallback resiliente: si no tiene inscripción por componente, evaluar componentes del curso
        if ($componentes->isEmpty()) {
            $componentes = Componente::where('id_curso', $curso->id_curso)
                ->with('tipoComponente')
                ->get();
        }

        $componentes = TipoComponente::ordenarCTL($componentes);

        $syllabusPorTipo = collect($componentesSyllabus)
            ->keyBy(fn ($c) => mb_strtoupper(trim((string) ($c['componente'] ?? ''))));

        $totalPresentes = 0;
        $totalClases = 0;
        $totalActividadesSumativas = 0;
        $totalActividadesEvaluadas = 0;

        $filas = $componentes->map(function (Componente $componente) use (
            $estudiante,
            $syllabusPorTipo,
            &$totalPresentes,
            &$totalClases,
            &$totalActividadesSumativas,
            &$totalActividadesEvaluadas
        ) {
            $tipo = $componente->tipoComponente?->tipo ?? 'Componente';
            $syllabus = $syllabusPorTipo->get(mb_strtoupper(trim($tipo)));

            $sumativas = Actividad::where('id_componente', $componente->id_componente)
                ->where('visible', true)
                ->where('tipo_actividad', TipoActividad::SUMATIVA->value)
                ->orderBy('fecha_limite', 'asc')
                ->get(['id_actividad', 'nombre', 'ponderacion', 'fecha_limite']);

            $notas = $this->notasIndividualesEstudiante($estudiante->id_estudiante, $sumativas->pluck('id_actividad'));
            $ponderaciones = $sumativas->pluck('ponderacion', 'id_actividad')->map(fn ($p) => (float) $p);

            $miPromedio = $this->promedioPonderado($notas, $ponderaciones);

            $ponderacionTotal = $ponderaciones->sum();
            $ponderacionEvaluada = $ponderaciones->only($notas->keys()->all())->sum();

            $asistencia = $this->asistencia($componente, $estudiante);
            $totalPresentes += $asistencia['presentes'];
            $totalClases += $asistencia['total'];
            $totalActividadesSumativas += $sumativas->count();
            $totalActividadesEvaluadas += $notas->count();

            $actividades = $sumativas->map(function ($act) use ($notas) {
                $tieneNota = $notas->has($act->id_actividad);

                return [
                    'id_actividad' => (int) $act->id_actividad,
                    'nombre' => $act->nombre,
                    'ponderacion' => $act->ponderacion !== null ? (float) $act->ponderacion : null,
                    'nota' => $tieneNota ? (float) $notas->get($act->id_actividad) : null,
                    'es_evaluada' => $tieneNota,
                    'fecha_limite' => $act->fecha_limite?->format('Y-m-d'),
                ];
            })->values()->all();

            $alertaAsistencia = $asistencia['porcentaje'] !== null && $asistencia['porcentaje'] < self::ASISTENCIA_MINIMA;

            return [
                'id_componente' => (int) $componente->id_componente,
                'tipo' => $tipo,
                'mi_promedio' => $miPromedio,
                'actividades_sumativas' => $sumativas->count(),
                'actividades_evaluadas' => $notas->count(),
                'ponderacion_evaluada' => $ponderacionTotal > 0 ? round($ponderacionEvaluada / $ponderacionTotal * 100) : null,
                'syllabus' => [
                    'porcentaje' => isset($syllabus['porcentaje']) ? (float) $syllabus['porcentaje'] : null,
                    'aprobacion_obligatoria' => (bool) ($syllabus['aprobacion_obligatoria'] ?? false),
                ],
                'exigencia' => $componente->porcentaje_aprobacion !== null ? (float) $componente->porcentaje_aprobacion : null,
                'asistencia' => array_merge($asistencia, [
                    'minimo_exigido' => self::ASISTENCIA_MINIMA,
                    'alerta_asistencia' => $alertaAsistencia,
                ]),
                'actividades' => $actividades,
            ];
        })->values();

        $pctGlobal = $totalClases > 0 ? (int) round($totalPresentes / $totalClases * 100) : null;
        $asignatura = $curso->asignacionPlan?->asignatura;

        return [
            'curso' => [
                'id_curso' => (int) $curso->id_curso,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
                'asignatura_nombre' => $asignatura?->nombre ?? 'N/A',
            ],
            'mi_promedio' => $this->combinar($filas, 'mi_promedio'),
            'asistencia_minima' => self::ASISTENCIA_MINIMA,
            'asistencia_global' => [
                'presentes' => $totalPresentes,
                'total' => $totalClases,
                'porcentaje' => $pctGlobal,
                'alerta_asistencia' => $pctGlobal !== null && $pctGlobal < self::ASISTENCIA_MINIMA,
            ],
            'totales' => [
                'actividades_sumativas' => $totalActividadesSumativas,
                'actividades_evaluadas' => $totalActividadesEvaluadas,
                'ponderacion_evaluada' => $this->calcularPonderacionEvaluadaTotal($filas),
            ],
            'componentes' => $filas->all(),
        ];
    }

    /**
     * Calcula la ponderación evaluada combinada en base al syllabus de los componentes.
     *
     * @param  Collection<int, array>  $filas
     */
    private function calcularPonderacionEvaluadaTotal(Collection $filas): ?float
    {
        $conPonderacion = $filas->filter(fn ($f) => $f['ponderacion_evaluada'] !== null);
        if ($conPonderacion->isEmpty()) {
            return null;
        }

        $pesos = $conPonderacion->map(fn ($f) => $f['syllabus']['porcentaje'] ?? 0);
        $sumaPesos = $pesos->sum();

        $valor = $sumaPesos > 0
            ? $conPonderacion->map(fn ($f, $i) => $f['ponderacion_evaluada'] * $pesos[$i])->sum() / $sumaPesos
            : $conPonderacion->avg('ponderacion_evaluada');

        return round($valor);
    }

    /**
     * Obtiene las notas individuales del estudiante para las actividades indicadas.
     *
     * @param  Collection<int, int>  $idsActividad
     * @return Collection<int, float>  id_actividad => nota
     */
    private function notasIndividualesEstudiante(int $idEstudiante, Collection $idsActividad): Collection
    {
        if ($idsActividad->isEmpty()) {
            return collect();
        }

        return IntegranteGrupo::query()
            ->join('agenda.actividad_asignada_grupo as g', 'g.id_actividad_asignada_grupo', '=', 'integrante_grupo.id_actividad_asignada_grupo')
            ->whereIn('g.id_actividad', $idsActividad)
            ->where('integrante_grupo.id_estudiante', $idEstudiante)
            ->where(fn ($q) => $q->whereNotNull('integrante_grupo.nota_individual')->orWhereNotNull('g.nota'))
            ->get([
                'g.id_actividad',
                DB::raw('COALESCE(integrante_grupo.nota_individual, g.nota) AS nota'),
            ])
            ->mapWithKeys(fn ($f) => [(int) $f->id_actividad => (float) $f->nota]);
    }

    private function asegurarInscripcionActiva(Curso $curso, Estudiante $estudiante): void
    {
        $inscrito = $estudiante->inscripcionCursos()
            ->where('id_curso', $curso->id_curso)
            ->where('estado_inscripcion', 'INSCRITO')
            ->exists();

        if (!$inscrito) {
            abort(403, 'No estás inscrito en este curso.');
        }
    }
}
