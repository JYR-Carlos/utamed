<?php

namespace App\Http\Controllers\Docente\JefeCarrera;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Curso;
use Inertia\Inertia;

/**
 * Detalle (solo lectura) de la carrera del Jefe de Carrera.
 *
 * Renderiza la página jefe-carrera/Carrera con la carrera, sus planes y métricas básicas.
 */
class CarreraController extends Controller
{
    use ResolvesJefaturaCarrera;

    public function show()
    {
        $jefatura = $this->jefaturaOrAbort();
        $carreraId = (int) $jefatura['carrera_id'];

        $carrera = Carrera::with('departamento')
            ->findOrFail($carreraId);

        $planes = $carrera->planes()
            ->withCount('asignacionPlanes as asignaturas_count')
            ->withSum('asignaturas as creditos_sct_totales', 'creditos_sct')
            ->orderBy('agno_plan', 'desc')
            ->orderBy('version_plan', 'desc')
            ->get();

        $asignaturasEnMalla = AsignacionPlan::whereHas('plan', fn($q) => $q->where('id_carrera', $carreraId))
            ->distinct('id_asignatura')
            ->count('id_asignatura');

        $cursosCount = Curso::whereHas('asignacionPlan.plan', fn($q) => $q->where('id_carrera', $carreraId))
            ->whereNull('fecha_eliminacion')
            ->count();

        return Inertia::render('jefe-carrera/Carrera', [
            'carrera' => [
                'id_carrera' => $carrera->id_carrera,
                'nombre' => $carrera->nombre,
                'jornada' => $carrera->jornada,
                'sede' => $carrera->sede,
                'modalidad' => $carrera->modalidad,
                'departamento' => $carrera->departamento?->nombre,
            ],
            'planes' => $planes,
            'stats' => [
                'planes' => $planes->count(),
                'asignaturas' => $asignaturasEnMalla,
                'cursos' => $cursosCount,
            ],
        ]);
    }
}
