<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\LimitsPageSize;
use App\Http\Controllers\Controller;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Carrera;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
/**
 * Controlador para la gestión de planes de estudio.
 * 
 * Tablas implicadas:
 * - administrativo.plan: Planes de estudio de carreras.
 * - administrativo.carrera: Carreras que contienen planes.
 * - administrativo.asignacion_plan: Asignaturas asignadas a planes.
 * 
 * Los planes pertenecen a carreras y contienen asignaturas organizadas por años y semestres.
 * Cada plan tiene versionado para permitir cambios en la malla sin afectar estudios anteriores.
 */
class PlanController extends Controller
{
    use LimitsPageSize;

    /**
     * Muestra un listado paginado de planes con búsqueda y filtros por carrera.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Plan::class);
        $query = Plan::with('carrera');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('carrera', function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%");
            });
        }

        // Filter by carrera
        if ($request->filled('id_carrera')) {
            $query->where('id_carrera', $request->input('id_carrera'));
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
            $query->orderBy('agno_plan', $sortDir)->orderBy('version_plan', $sortDir);
        } elseif ($sortKey === 'version_plan') {
            $query->orderBy('version_plan', $sortDir);
        } elseif ($sortKey === 'creditos_sct_totales') {
            $query->orderBy('creditos_sct_totales', $sortDir);
        } else {
            $query->orderBy('agno_plan', 'desc')->orderBy('version_plan', 'desc');
        }

        // Pagination
        $planes = $query->paginate($this->perPage($request))
            ->withQueryString();

        // Get all carreras for the filter
        $carreras = Carrera::orderBy('nombre')->get();

        return Inertia::render('admin/Planes', [
            'planes' => $planes,
            'carreras' => $carreras,
            'filters' => $request->only(['search', 'id_carrera', 'sort_key', 'sort_dir'])
        ]);
    }

    /**
     * Obtiene planes para un select en cascada (por carrera).
     */
    public function byCarrera(Carrera $carrera)
    {
        $this->authorize('viewAny', Plan::class);
        $planes = $carrera->planes()
            ->with('carrera')
            ->orderBy('agno_plan', 'desc')
            ->orderBy('version_plan', 'desc')
            ->get();

        return response()->json($planes);
    }

    /**
     * Crea un nuevo plan de estudios para una carrera.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Plan::class);
        $validated = $request->validate([
            'id_carrera' => ['required', Rule::exists(Carrera::class, 'id_carrera')],
            'agno_plan' => 'required|integer|min:1900|max:2100',
            'version_plan' => 'required|integer|min:1',
        ]);

        try {
            $plan = Plan::create($validated);

            return redirect()->route('admin.planes.index')
                ->with('success', 'Plan creado exitosamente para el año ' . $validated['agno_plan'] . ' versión ' . $validated['version_plan'] . '.');
        } catch (\Exception $e) {
            Log::error('Error al crear plan: ' . $e->getMessage(), [
                'validated_data' => $validated,
                'exception' => $e
            ]);

            return back()->with('error', 'Error al crear plan: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene un plan con sus asignaciones y el total de créditos.
     */
    public function show(Plan $plan)
    {
        $this->authorize('view', $plan);
        $plan->load(['carrera', 'asignacionPlanes.asignatura']);
        $plan->setAttribute('creditos_sct_totales', $plan->calculateTotalCredits());

        return response()->json($plan);
    }

    /**
     * Actualiza los datos de un plan de estudios.
     */
    public function update(Request $request, Plan $plan)
    {
        $this->authorize('update', $plan);
        $validated = $request->validate([
            'id_carrera' => ['required', Rule::exists(Carrera::class, 'id_carrera')],
            'agno_plan' => 'required|integer|min:1900|max:2100',
            'version_plan' => 'required|integer|min:1',
        ]);

        $plan->update($validated);

        return redirect()->route('admin.planes.index')
            ->with('success', 'Plan actualizado exitosamente.');
    }

    /**
     * Elimina un plan de estudios.
     */
    public function destroy(Plan $plan)
    {
        $this->authorize('delete', $plan);
        try {

            Log::info('Deleting plan with ID ' . $plan->id_plan);
            $plan->delete();

            return redirect()->route('admin.planes.index')
                ->with('success', 'Plan eliminado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar plan: ' . $e->getMessage(), [
                'id_plan' => $plan->id_plan,
                'exception' => $e,
            ]);
            return redirect()->route('admin.planes.index')
                ->with('error', 'No se puede eliminar el plan: ' . $e->getMessage());
        }
    }
}
