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
}
