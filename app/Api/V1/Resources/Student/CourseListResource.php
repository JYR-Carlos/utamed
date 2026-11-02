<?php

namespace App\Api\V1\Resources\Student;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Catálogo de cursos inscritos del estudiante para el período solicitado (por defecto actual).
 *
 * @property array $resource
 */
class CourseListResource extends JsonResource
{
    /**
     * @param  array{semestre: int, agno: int}  $periodo
     * @param  Collection<int, array<string, mixed>>  $cursos
     * @param  array{no_leidos: int, cursos: array<int, array<string, mixed>>}  $mensajesNoLeidos
     */
    public function __construct(
        private readonly array $periodo,
        private readonly Collection $cursos,
        private readonly array $mensajesNoLeidos,
    ) {
        parent::__construct([
            'periodo' => $this->periodo,
            'cursos' => $this->cursos,
        ]);
    }

    public function toArray(Request $request): array
    {
        $noLeidosPorCurso = collect($this->mensajesNoLeidos['cursos'])->pluck('no_leidos', 'id_curso');
        $fecha = fn ($valor) => $valor ? Carbon::parse($valor)->format('Y-m-d') : null;

        return [
            'periodo' => $this->periodo,
            'cursos' => $this->cursos->map(fn (array $c) => [
                'id_curso' => (int) $c['id_curso'],
                'nombre' => $c['nombre'],
                'cod_curso' => $c['cod_curso'],
                'cod_asignatura' => $c['cod_asignatura'],
                'asignatura_nombre' => $c['asignatura_nombre'],
                'carrera_nombre' => $c['carrera_nombre'],
                'letra_grupo' => $c['letra_grupo'] ?? null,
                'fecha_inicio' => $fecha($c['fecha_inicio']),
                'fecha_fin' => $fecha($c['fecha_fin']),
                'semestre_real' => (int) $c['semestre_real'],
                'agno_real' => (int) $c['agno_real'],
                'docente' => $c['profesor'],
                'mensajes_no_leidos' => (int) ($noLeidosPorCurso[$c['id_curso']] ?? 0),
                'tiene_programa' => (bool) ($c['tiene_programa'] ?? false),
                'imagen_url' => $c['imagen_url'],
            ])->values(),
        ];
    }
}
