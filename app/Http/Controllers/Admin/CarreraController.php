<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CarreraController extends Controller
{
    /**
     * Display a listing of carreras.
     */
    public function index(Request $request)
    {
        $query = Carrera::with(['departamento', 'departamento.facultad']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('nombre', 'ilike', "%{$search}%");
        }

        // Filter by departamento
        if ($request->has('id_departamento')) {
            $query->where('id_departamento', $request->input('id_departamento'));
        }

        // Filter by facultad
        if ($request->has('id_facultad')) {
            $query->where('id_facultad', $request->input('id_facultad'));
        }

        // Pagination
        $carreras = $query->orderBy('nombre')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get all facultades for the filter
        $facultades = Facultad::orderBy('nombre')->get();

        return Inertia::render('admin/Carreras', [
            'carreras' => $carreras,
            'facultades' => $facultades,
            'filters' => $request->only(['search', 'id_departamento', 'id_facultad'])
        ]);
    }

    /**
     * Get carreras by departamento (for cascading selects).
     */
    public function byDepartamento(Departamento $departamento)
    {
        $carreras = $departamento->carreras()
            ->orderBy('nombre')
            ->get();

        return response()->json($carreras);
    }

    /**
     * Store a newly created carrera.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'jornada' => 'nullable|string|max:100',
            'sede' => 'nullable|string|max:100',
            'modalidad' => 'nullable|string|max:100',
            'id_departamento' => ['required', Rule::exists(Departamento::class, 'id_departamento')],
            'id_facultad' => ['required', Rule::exists(Facultad::class, 'id_facultad')],
        ]);

        try {
            $carrera = Carrera::create($validated);

            return redirect()->route('admin.carreras.index')
                ->with('success', 'Carrera creada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear carrera: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified carrera.
     */
    public function show(Carrera $carrera)
    {
        $carrera->load(['departamento', 'departamento.facultad', 'planes']);

        return response()->json($carrera);
    }

    /**
     * Update the specified carrera.
     */
    public function update(Request $request, Carrera $carrera)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'jornada' => 'nullable|string|max:100',
            'sede' => 'nullable|string|max:100',
            'modalidad' => 'nullable|string|max:100',
            'id_departamento' => ['required', Rule::exists(Departamento::class, 'id_departamento')],
            'id_facultad' => ['required', Rule::exists(Facultad::class, 'id_facultad')],
        ]);

        $carrera->update($validated);

        return redirect()->route('admin.carreras.index')
            ->with('success', 'Carrera actualizada exitosamente.');
    }

    /**
     * Remove the specified carrera.
     */
    public function destroy(Carrera $carrera)
    {
        try {
            $carrera->delete();

            return redirect()->route('admin.carreras.index')
                ->with('success', 'Carrera eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.carreras.index')
                ->with('error', 'No se puede eliminar la carrera porque tiene planes o estudiantes asociados.');
        }
    }
}
