<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\AsignacionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AsignacionPlanController extends Controller
{
    /**
     * Display asignaturas assigned to a plan (Detalle Malla).
     */
    public function index(Request $request, Plan $plan)
    {
        $plan->load(['carrera', 'asignacionPlanes.asignatura']);
        $plan->setAttribute('creditos_sct_totales', $plan->calculateTotalCredits());

        // Organize by año and semestre
        $malla = $plan->asignacionPlanes->groupBy(function ($item) {
            return $item->agno_planificado . '-' . $item->semestre_planificado;
        });

        // Get all asignaturas for adding new ones
        $asignaturas = Asignatura::orderBy('cod_asignatura')->get();

        return Inertia::render('admin/DetalleMalla', [
            'plan' => $plan,
            'malla' => $malla,
            'asignaturas' => $asignaturas
        ]);
    }

    /**
     * Assign an asignatura to a plan.
     */
    public function store(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'id_asignatura' => ['required', Rule::exists(Asignatura::class, 'id_asignatura')],
            'agno_planificado' => 'required|integer|min:1|max:10',
            'semestre_planificado' => 'required|integer|in:1,2',
            'tipo_ramo' => 'nullable|integer',
        ]);

        // Check if already assigned
        $exists = AsignacionPlan::where('id_plan', $plan->id_plan)
            ->where('id_asignatura', $validated['id_asignatura'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Esta asignatura ya está asignada a este plan.');
        }

        // Add id_plan to validated data
        $validated['id_plan'] = $plan->id_plan;

        AsignacionPlan::create($validated);

        return back()->with('success', 'Asignatura asignada al plan exitosamente.');
    }

    /**
     * Update an asignatura assignment in a plan.
     */
    public function update(Request $request, Plan $plan, Asignatura $asignatura)
    {
        $validated = $request->validate([
            'agno_planificado' => 'required|integer|min:1|max:10',
            'semestre_planificado' => 'required|integer|in:1,2',
            'tipo_ramo' => 'nullable|integer',
        ]);

        $asignacion = AsignacionPlan::where('id_plan', $plan->id_plan)
            ->where('id_asignatura', $asignatura->id_asignatura)
            ->firstOrFail();

        $asignacion->update($validated);

        return back()->with('success', 'Asignación actualizada exitosamente.');
    }

    /**
     * Remove an asignatura from a plan.
     */
    public function destroy(Plan $plan, Asignatura $asignatura)
    {
        $asignacion = AsignacionPlan::where('id_plan', $plan->id_plan)
            ->where('id_asignatura', $asignatura->id_asignatura)
            ->firstOrFail();

        $asignacion->delete();

        return back()->with('success', 'Asignatura removida del plan exitosamente.');
    }
}
