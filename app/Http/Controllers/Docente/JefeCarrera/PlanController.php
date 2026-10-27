<?php

namespace App\Http\Controllers\Docente\JefeCarrera;

use App\Http\Controllers\Concerns\LimitsPageSize;
use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Gestión de planes de estudio (malla) acotada a la carrera del Jefe de Carrera.
 *
 * Reutiliza la vista admin/Planes pasando routePrefix = '/docente/jefe-carrera'.
 * Toda query queda restringida a la carrera del jefe; cualquier plan ajeno => 403.
 */
class PlanController extends Controller
{
    use LimitsPageSize;

    use ResolvesJefaturaCarrera;

    private const PREFIX = '/docente/jefe-carrera';

    public function index(Request $request)
    {
        $carreraId = $this->carreraIdOrAbort();

        $query = Plan::with('carrera')->where('id_carrera', $carreraId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('asignacionPlanes.asignatura', function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('cod_asignatura', 'ilike', "%{$search}%");
            });
        }

        // Calculate total SCT credits summing Asignatura.creditos_sct via AsignacionPlan
        $query->withSum('asignaturas as creditos_sct_totales', 'creditos_sct');

        // Ordenamiento
        $sortKey = $request->input('sort_key');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortKey === 'carrera.nombre') {
            $sub = Carrera::select('nombre')->whereColumn('carrera.id_carrera', 'plan.id_carrera')->limit(1);
            $query->orderBy($sub, $sortDir);
        } elseif ($sortKey === 'agno_plan') {
            $query->orderBy('plan.agno_plan', $sortDir)->orderBy('plan.version_plan', $sortDir);
        } elseif ($sortKey === 'version_plan') {
            $query->orderBy('plan.version_plan', $sortDir);
        } elseif ($sortKey === 'creditos_sct_totales') {
            $sub = Asignatura::selectRaw('coalesce(sum(asignatura.creditos_sct), plan.creditos_sct_totales, 0)')
                ->join('asignacion_plan', 'asignatura.id_asignatura', '=', 'asignacion_plan.id_asignatura')
                ->whereColumn('plan.id_plan', 'asignacion_plan.id_plan')
                ->whereNull('asignacion_plan.fecha_eliminacion')
                ->whereNull('asignatura.fecha_eliminacion');
            $query->orderBy($sub, $sortDir);
        } else {
            $query->orderBy('plan.agno_plan', 'desc')->orderBy('plan.version_plan', 'desc');
        }

        $planes = $query->paginate($this->perPage($request))
            ->withQueryString();

        // El selector de carrera queda bloqueado a la carrera del jefe.
        $carreras = Carrera::where('id_carrera', $carreraId)->get();

        return Inertia::render('admin/Planes', [
            'planes' => $planes,
            'carreras' => $carreras,
            'filters' => $request->only(['search', 'id_carrera', 'sort_key', 'sort_dir']),
            'routePrefix' => self::PREFIX,
        ]);
    }

    public function store(Request $request)
    {
        $carreraId = $this->carreraIdOrAbort();

        $validated = $request->validate([
            'agno_plan' => 'required|integer|min:1900|max:2100',
            'version_plan' => 'required|integer|min:1',
        ]);

        // Forzar la carrera del jefe (se ignora cualquier id_carrera entrante).
        $validated['id_carrera'] = $carreraId;

        try {
            Plan::create($validated);

            return redirect(self::PREFIX . '/planes')
                ->with('success', 'Plan creado exitosamente para el año ' . $validated['agno_plan'] . ' versión ' . $validated['version_plan'] . '.');
        } catch (\Exception $e) {
            Log::error('Error al crear plan (jefe de carrera): ' . $e->getMessage(), [
                'validated_data' => $validated,
            ]);

            return back()->with('error', 'No se pudo crear el plan. Por favor, inténtalo nuevamente.');
        }
    }

    public function update(Request $request, Plan $plan)
    {
        $carreraId = $this->carreraIdOrAbort();
        $this->assertPlanDeCarrera($plan, $carreraId);

        $validated = $request->validate([
            'agno_plan' => 'required|integer|min:1900|max:2100',
            'version_plan' => 'required|integer|min:1',
        ]);

        // No permitir mover el plan a otra carrera.
        $validated['id_carrera'] = $carreraId;

        $plan->update($validated);

        return redirect(self::PREFIX . '/planes')
            ->with('success', 'Plan actualizado exitosamente.');
    }

    public function destroy(Plan $plan)
    {
        $carreraId = $this->carreraIdOrAbort();
        $this->assertPlanDeCarrera($plan, $carreraId);

        try {
            Log::info('Deleting plan with ID ' . $plan->id_plan . ' (jefe de carrera)');
            $plan->delete();

            return redirect(self::PREFIX . '/planes')
                ->with('success', 'Plan eliminado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar plan (jefe de carrera): ' . $e->getMessage(), [
                'id_plan' => $plan->id_plan,
            ]);

            return redirect(self::PREFIX . '/planes')
                ->with('error', 'No se puede eliminar el plan. Verifica que no tenga asignaturas o cursos asociados.');
        }
    }

    /**
     * Aborta con 403 si el plan no pertenece a la carrera del jefe.
     */
    private function assertPlanDeCarrera(Plan $plan, int $carreraId): void
    {
        if ((int) $plan->id_carrera !== $carreraId) {
            abort(403, 'Este plan no pertenece a tu carrera.');
        }
    }
}
