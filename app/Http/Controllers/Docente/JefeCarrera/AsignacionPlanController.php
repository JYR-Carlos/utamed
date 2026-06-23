<?php

namespace App\Http\Controllers\Docente\JefeCarrera;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Editor de malla (asignación de asignaturas a planes) para el Jefe de Carrera.
 *
 * Reutiliza la vista admin/DetalleMalla con routePrefix = '/docente/jefe-carrera'.
 * Cada acción verifica que el plan pertenezca a la carrera del jefe (403 si no).
 */
class AsignacionPlanController extends Controller
{
    use ResolvesJefaturaCarrera;

    private const PREFIX = '/docente/jefe-carrera';

    public function index(Request $request, Plan $plan)
    {
        $this->assertPlanDeCarrera($plan);

        ['plan' => $plan, 'malla' => $malla] = $this->prepareMallaData($plan);

        return Inertia::render('admin/DetalleMalla', [
            'plan' => $plan,
            'malla' => $malla,
            'asignaturas' => Asignatura::active()
                ->orderBy('cod_asignatura')
                ->get(['id_asignatura', 'cod_asignatura', 'nombre', 'creditos_sct']),
            'routePrefix' => self::PREFIX,
        ]);
    }

    public function mallaJson(Plan $plan)
    {
        $this->assertPlanDeCarrera($plan);

        ['plan' => $plan, 'malla' => $malla] = $this->prepareMallaData($plan);

        return response()->json([
            'plan' => $plan,
            'malla' => $malla,
        ]);
    }

    public function store(Request $request, Plan $plan)
    {
        $this->assertPlanDeCarrera($plan);

        $requestData = $request->all();
        if (empty($requestData['tipo_ramo'])) {
            $requestData['tipo_ramo'] = null;
        } else {
            $requestData['tipo_ramo'] = (int) $requestData['tipo_ramo'];
        }
        $request->merge($requestData);

        $validated = $request->validate([
            'id_asignatura' => ['required', function ($attribute, $value, $fail) {
                $asignatura = Asignatura::where('id_asignatura', $value)->active()->first();
                if (!$asignatura) {
                    $fail('La asignatura seleccionada no es válida o ha sido reemplazada por una versión más reciente.');
                }
            }],
            'agno_planificado' => 'required|integer|min:1|max:10',
            'semestre_planificado' => 'required|integer|in:1,2',
            'tipo_ramo' => 'nullable|integer',
        ]);

        $exists = AsignacionPlan::where('id_plan', $plan->id_plan)
            ->where('id_asignatura', $validated['id_asignatura'])
            ->withoutTrashed()
            ->exists();

        if ($exists) {
            return back()->with('error', 'Esta asignatura ya está asignada a este plan.');
        }

        $validated['id_plan'] = $plan->id_plan;

        try {
            AsignacionPlan::create($validated);

            return back()->with('success', 'Asignatura asignada al plan exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al asignar asignatura a plan (jefe de carrera):', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return back()->with('error', 'Error al asignar la asignatura: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, Plan $plan, Asignatura $asignatura)
    {
        $this->assertPlanDeCarrera($plan);

        $validated = [];

        try {
            $requestData = $request->all();
            if (empty($requestData['tipo_ramo'])) {
                $requestData['tipo_ramo'] = null;
            } else {
                $requestData['tipo_ramo'] = (int) $requestData['tipo_ramo'];
            }
            $request->merge($requestData);

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
        } catch (\Exception $e) {
            Log::error('Error actualizando asignación de malla (jefe de carrera):', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return back()->with('error', 'No se pudo actualizar la asignación de malla: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Plan $plan, Asignatura $asignatura)
    {
        $this->assertPlanDeCarrera($plan);

        $asignacion = AsignacionPlan::where('id_plan', $plan->id_plan)
            ->where('id_asignatura', $asignatura->id_asignatura)
            ->firstOrFail();

        $asignacion->delete();

        return back()->with('success', 'Asignatura removida del plan exitosamente.');
    }

    /**
     * Prepara los datos de la malla (idéntico al admin).
     */
    private function prepareMallaData(Plan $plan): array
    {
        $plan->load(['carrera', 'asignacionPlanes' => function ($q) {
            $q->with('asignatura');
        }]);

        $plan->setAttribute('creditos_sct_totales', $plan->calculateTotalCredits());

        $malla = $plan->asignacionPlanes->groupBy(
            fn($item) => "{$item->agno_planificado}-{$item->semestre_planificado}"
        );

        return compact('plan', 'malla');
    }

    /**
     * Aborta con 403 si el plan no pertenece a la carrera del jefe.
     */
    private function assertPlanDeCarrera(Plan $plan): void
    {
        $carreraId = $this->carreraIdOrAbort();

        if ((int) $plan->id_carrera !== $carreraId) {
            abort(403, 'Este plan no pertenece a tu carrera.');
        }
    }
}
