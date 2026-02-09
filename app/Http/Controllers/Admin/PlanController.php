<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Carrera;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

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
    /**
     * Muestra un listado paginado de planes con búsqueda y filtros por carrera.
     */
    public function index(Request $request)
    {
        $query = Plan::with('carrera');

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('carrera', function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%");
            });
        }

        // Filter by carrera
        if ($request->has('id_carrera')) {
            $query->where('id_carrera', $request->input('id_carrera'));
        }

        // Pagination
        // Calculate total SCT credits summing Asignatura.creditos_sct via AsignacionPlan
        $planes = $query->orderBy('agno', 'desc')
            ->orderBy('version_plan', 'desc')
            ->addSelect([
                'creditos_sct_totales' => function ($q) {
                    // Use explicit schema if needed, but standard strings usually work if search_path is set.
                    // Safest to rely on what works for Plan, but since this is raw SQL in addSelect,
                    // we should be careful. Assuming default search path works for now.
                    $q->selectRaw('sum(creditos_sct)')
                        ->from('Asignacion_Plan')
                        ->join('Asignatura', 'Asignacion_Plan.id_asignatura', '=', 'Asignatura.id_asignatura')
                        ->whereColumn('Asignacion_Plan.id_plan', 'Plan.id_plan')
                        ->whereNull('Asignatura.fecha_eliminacion')
                        ->whereNull('Asignacion_Plan.fecha_eliminacion');
                }
            ])
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get all carreras for the filter
        $carreras = Carrera::orderBy('nombre')->get();

        return Inertia::render('admin/Planes', [
            'planes' => $planes,
            'carreras' => $carreras,
            'filters' => $request->only(['search', 'id_carrera'])
        ]);
    }

    /**
     * Obtiene planes para un select en cascada (por carrera).
     */
    public function byCarrera(Carrera $carrera)
    {
        $planes = $carrera->planes()
            ->orderBy('agno', 'desc')
            ->orderBy('version_plan', 'desc')
            ->get();

        return response()->json($planes);
    }

    /**
     * Crea un nuevo plan de estudios para una carrera.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_carrera' => ['required', Rule::exists(Carrera::class, 'id_carrera')],
            'agno' => 'required|integer|min:1900|max:2100',
            'version_plan' => 'required|integer|min:1',
        ]);

        try {
            $plan = Plan::create($validated);

            return redirect()->route('admin.planes.index')
                ->with('success', 'Plan creado exitosamente para el año ' . $validated['agno'] . ' versión ' . $validated['version_plan'] . '.');
        } catch (\Exception $e) {
            \Log::error('Error al crear plan: ' . $e->getMessage(), [
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
        $plan->load(['carrera', 'asignacionPlanes.asignatura']);
        $plan->setAttribute('creditos_sct_totales', $plan->calculateTotalCredits());

        return response()->json($plan);
    }

    /**
     * Actualiza los datos de un plan de estudios.
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'id_carrera' => ['required', Rule::exists(Carrera::class, 'id_carrera')],
            'agno' => 'required|integer|min:1900|max:2100',
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
        try {
            $plan->delete();

            return redirect()->route('admin.planes.index')
                ->with('success', 'Plan eliminado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.planes.index')
                ->with('error', 'No se puede eliminar el plan porque tiene asignaturas asignadas.');
        }
    }
}
