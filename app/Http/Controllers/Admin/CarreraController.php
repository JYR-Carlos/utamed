<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Controlador para la gestión de carreras.
 * 
 * Tablas implicadas:
 * - administrativo.carrera: Carreras académicas.
 * - administrativo.departamento: Departamentos que contienen carreras.
 * - administrativo.facultad: Facultades que contienen departamentos.
 * 
 * Las carreras pertenecen a departamentos y contienen planes de estudio.
 * Forman parte de la jerarquía: Facultad → Departamento → Carrera → Plan.
 */
class CarreraController extends Controller
{
    /**
     * Muestra un listado paginado de carreras con búsqueda y filtros.
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
     * Obtiene carreras para un select en cascada (por departamento).
     */
    public function byDepartamento(Departamento $departamento)
    {
        $carreras = $departamento->carreras()
            ->orderBy('nombre')
            ->get();

        return response()->json($carreras);
    }

    /**
     * Crea una nueva carrera.
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
     * Obtiene una carrera con sus relaciones (departamento, facultad, planes).
     */
    public function show(Carrera $carrera)
    {
        $carrera->load(['departamento', 'departamento.facultad', 'planes']);

        return response()->json($carrera);
    }

    /**
     * Actualiza los datos de una carrera.
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
     * Elimina una carrera.
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
