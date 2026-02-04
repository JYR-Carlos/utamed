<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AsignaturaController extends Controller
{
    /**
     * Display a listing of asignaturas.
     */
    public function index(Request $request)
    {
        $query = Asignatura::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('cod_asignatura', 'ilike', "%{$search}%");
            });
        }

        // Pagination
        $asignaturas = $query->orderBy('cod_asignatura')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('admin/Asignaturas', [
            'asignaturas' => $asignaturas,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * Store a newly created asignatura.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cod_asignatura' => ['required', 'string', 'max:50', Rule::unique(Asignatura::class, 'cod_asignatura')],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'creditos_sct' => 'nullable|integer|min:0',
            'horas_catedra' => 'nullable|integer|min:0',
            'horas_taller' => 'nullable|integer|min:0',
            'horas_laboratorio' => 'nullable|integer|min:0',
            'horas_dirigidas' => 'nullable|integer|min:0',
            'horas_autonomas' => 'nullable|integer|min:0',
        ]);

        try {
            $asignatura = Asignatura::create($validated);

            return redirect()->route('admin.asignaturas.index')
                ->with('success', 'Asignatura creada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear asignatura: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified asignatura.
     */
    public function show(Asignatura $asignatura)
    {
        $asignatura->load('asignacionPlanes.plan.carrera');

        return response()->json($asignatura);
    }

    /**
     * Update the specified asignatura.
     */
    public function update(Request $request, Asignatura $asignatura)
    {
        $validated = $request->validate([
            'cod_asignatura' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Asignatura::class, 'cod_asignatura')->ignore($asignatura->id_asignatura, 'id_asignatura')
            ],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'creditos_sct' => 'nullable|integer|min:0',
            'horas_catedra' => 'nullable|integer|min:0',
            'horas_taller' => 'nullable|integer|min:0',
            'horas_laboratorio' => 'nullable|integer|min:0',
            'horas_dirigidas' => 'nullable|integer|min:0',
            'horas_autonomas' => 'nullable|integer|min:0',
        ]);

        $asignatura->update($validated);

        return redirect()->route('admin.asignaturas.index')
            ->with('success', 'Asignatura actualizada exitosamente.');
    }

    /**
     * Remove the specified asignatura.
     */
    public function destroy(Asignatura $asignatura)
    {
        try {
            $asignatura->delete();

            return redirect()->route('admin.asignaturas.index')
                ->with('success', 'Asignatura eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.asignaturas.index')
                ->with('error', 'No se puede eliminar la asignatura porque está asignada a planes.');
        }
    }
}
