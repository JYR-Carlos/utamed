<?php

namespace App\Api\V1\Controllers\Student;

use App\Api\V1\Resources\Student\DashboardResource;
use App\Models\Usuario\Usuario;
use App\Services\Student\DashboardEstudiante;
use App\Services\Student\ResumenActividadesEstudiante;
use Illuminate\Http\Request;

/**
 * Pantalla de inicio móvil: cursos vigentes, entregas próximas, notas recientes
 * y mensajes sin leer del Mensajero. Sin SGEQ.
 */
class GetDashboardController
{
    public function __construct(
        private readonly DashboardEstudiante $dashboard,
        private readonly ResumenActividadesEstudiante $resumen,
    ) {}

    public function __invoke(Request $request): DashboardResource
    {
        /** @var Usuario $user */
        $user = $request->user();
        $estudiante = $user->estudiante;
        $periodo = DashboardEstudiante::periodoActual();

        return new DashboardResource(
            usuario: $user,
            periodo: $periodo,
            cursos: $this->dashboard->cursos($estudiante, $periodo['semestre'], $periodo['agno']),
            proximasAVencer: $this->resumen->proximasAVencer($estudiante, $periodo['semestre'], $periodo['agno']),
            notasRecientes: $this->resumen->notasYRetroalimentaciones($estudiante),
            mensajesNoLeidos: $this->dashboard->mensajesNoLeidosPorCurso((int) $user->id_usuario),
        );
    }
}
