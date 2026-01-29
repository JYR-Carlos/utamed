<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Carrera;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PlanController extends Controller
{
    /**
     * Display a listing of planes.
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
        $planes = $query->orderBy('agno', 'desc')
            ->orderBy('version', 'desc')
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
     * Get planes by carrera (for cascading selects).
     */
    public function byCarrera(Carrera $carrera)
    {
        $planes = $carrera->planes()
            ->orderBy('agno', 'desc')
            ->orderBy('version', 'desc')
            ->get();

        return response()->json($planes);
    }

    /**
     * Store a newly created plan.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_carrera' => ['required', Rule::exists(Carrera::class, 'id_carrera')],
            'agno' => 'required|integer|min:1900|max:2100',
            'version' => 'required|integer|min:1',
        ]);

        $plan = Plan::create($validated);

        return redirect()->route('admin.planes.index')
            ->with('success', 'Plan creado exitosamente.');
    }

    /**
     * Display the specified plan.
     */
    public function show(Plan $plan)
    {
        $plan->load(['carrera', 'asignacionPlanes.asignatura']);

        return response()->json($plan);
    }

    /**
     * Update the specified plan.
     */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'id_carrera' => ['required', Rule::exists(Carrera::class, 'id_carrera')],
            'agno' => 'required|integer|min:1900|max:2100',
            'version' => 'required|integer|min:1',
        ]);

        $plan->update($validated);

        return redirect()->route('admin.planes.index')
            ->with('success', 'Plan actualizado exitosamente.');
    }

    /**
     * Remove the specified plan.
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
