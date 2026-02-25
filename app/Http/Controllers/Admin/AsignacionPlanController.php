<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Plan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\AsignacionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para la gestión de asignaciones de asignaturas a planes (Detalle Malla).
 * 
 * Tablas implicadas:
 * - administrativo.asignacion_plan: Instancia de la asignatura en el plan.
 * - administrativo.plan: Plan de estudios al que se asignan las asignaturas.
 * - administrativo.asignatura: Asignaturas disponibles para asignar a planes.
 * 
 * Las asignaturas pueden ser asignadas, editadas o eliminadas de un plan específico.
 * Las asignaturas no se diferencian por carrera, sino que son globales y pueden ser
 * asignadas a cualquier plan.
 */
class AsignacionPlanController extends Controller
{
    /**
     * Muestra el detalle de la malla curricular de un plan, mostrando datos ya sea del plan o de las asignaturas asignadas.
     */
    public function index(Request $request, Plan $plan)
    {
        $plan->load(['carrera', 'asignacionPlanes' => function($q) {
            $q-> with('asignatura');
        }]);

        $plan->setAttribute('creditos_sct_totales', $plan->calculateTotalCredits());

        // Organize by año and semestre
        $malla = $plan->asignacionPlanes->groupBy(fn($item) => 
        "{$item->agno_planificado}-{$item->semestre_planificado}");



        return Inertia::render('admin/DetalleMalla', [
            'plan' => $plan,
            'malla' => $malla,
            'asignaturas' => Inertia::lazy(fn () => 
            Asignatura::orderBy('cod_asignatura')
            ->get(['id_asignatura', 'cod_asignatura', 'nombre', 'creditos_sct']))
        ]);
    }

    /**
     * Asignar una asignatura a un plan.
     */
    public function store(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'id_asignatura' => ['required', Rule::exists(Asignatura::class, 'id_asignatura')],
            'agno_planificado' => 'required|integer|min:1|max:10',
            'semestre_planificado' => 'required|integer|in:1,2',
            'tipo_ramo' => 'nullable|integer',
        ]);

        // Se deja en null el atributo 'tipo_ramo',  05-02-2026 TABLA AUN NO DEFINIDA 'TIPO_RAMO'
        if (isset($validated['tipo_ramo']) && $validated['tipo_ramo'] === '') {
            $validated['tipo_ramo'] = null;
        }

        // Check if already assigned to THIS PLAN (allows recycling from other plans)
        $exists = AsignacionPlan::where('id_plan', $plan->id_plan)
            ->where('id_asignatura', $validated['id_asignatura'])
            ->withoutTrashed() // Also check non-deleted records
            ->exists();

        if ($exists) {
            return back()->with('error', 'Esta asignatura ya está asignada a este plan.');
        }

        // Add id_plan to validated data
        $validated['id_plan'] = $plan->id_plan;

        try {
            Log::info('Creating AsignacionPlan with data:', $validated);
            
            $asignacion = AsignacionPlan::create($validated);
            
            Log::info('AsignacionPlan created successfully:', $asignacion->toArray());
            return back()->with('success', 'Asignatura asignada al plan exitosamente.');
        } catch (\Illuminate\Database\QueryException $e) {
            $errorMsg = 'Error de base de datos: ' . $e->getMessage();
            Log::error('Database error assigning subject to plan:', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'data' => $validated
            ]);
            return back()->with('error', $errorMsg)->withInput();
        } catch (\Exception $e) {
            $errorMsg = 'Error al asignar la asignatura: ' . $e->getMessage();
            Log::error('Error assigning subject to plan:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return back()->with('error', $errorMsg)->withInput();
        }
    }

    /**
     * Editar asignatura asignada en un plan.
     */
    public function update(Request $request, Plan $plan, Asignatura $asignatura)
    {
        try {
            $validated = $request->validate([
                'agno_planificado' => 'required|integer|min:1|max:10',
                'semestre_planificado' => 'required|integer|in:1,2',
                'tipo_ramo' => 'nullable|integer',
            ]);
            // Se deja en null el atributo 'tipo_ramo',  05-02-2026 TABLA AUN NO DEFINIDA 'TIPO_RAMO'
            if (isset($validated['tipo_ramo']) && $validated['tipo_ramo'] === '') {
                $validated['tipo_ramo'] = null;
            }

            $asignacion = AsignacionPlan::where('id_plan', $plan->id_plan)
                ->where('id_asignatura', $asignatura->id_asignatura)
                ->firstOrFail();

            $asignacion->update($validated);

            return back()->with('success', 'Asignación actualizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error actualizando la asignación de la asignatura al plan/malla:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return back()->with('error', 'No se pudo actualizar la asignacaión de malla: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Eliminar asignatura de un plan. 
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
