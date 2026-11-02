<?php

namespace App\Api\V1\Resources\Student;

use App\Models\Usuario\Usuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Pantalla de inicio de la app móvil del estudiante.
 *
 * @property Usuario $resource
 */
class DashboardResource extends JsonResource
{
    public function __construct(
        Usuario $usuario,
        private readonly array $periodo,
        private readonly Collection $cursos,
        private readonly array $proximasAVencer,
        private readonly array $notasRecientes,
        private readonly array $mensajesNoLeidos,
    ) {
        parent::__construct($usuario);
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
                'fecha_inicio' => $fecha($c['fecha_inicio']),
                'fecha_fin' => $fecha($c['fecha_fin']),
                'semestre_real' => $c['semestre_real'],
                'agno_real' => $c['agno_real'],
                'docente' => $c['profesor'],
                'mensajes_no_leidos' => (int) ($noLeidosPorCurso[$c['id_curso']] ?? 0),
            ])->values(),
            'proximas_a_vencer' => $this->proximasAVencer,
            'notas_recientes' => collect($this->notasRecientes)
                ->map(fn (array $n) => [...$n, 'fecha' => Carbon::parse($n['fecha'])->toIso8601String()])
                ->values(),
            'mensajes_no_leidos' => [
                'total' => $this->mensajesNoLeidos['no_leidos'],
                'cursos' => $this->mensajesNoLeidos['cursos'],
            ],
        ];
    }
}
