<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Controlador para la gestión de departamentos.
 * 
 * Tablas implicadas:
 * - administrativo.departamento: Departamentos dentro de facultades.
 * - administrativo.facultad: Facultades que contienen departamentos.
 * 
 * Los departamentos pertenecen a facultades y contienen carreras.
 * Forman parte de la jerarquía: Facultad → Departamento → Carrera.
 */
class DepartamentoController extends Controller
{
    /**
     * Muestra un listado paginado de departamentos con búsqueda por nombre y facultad.
     */
    public function index(Request $request)
    {
        $query = Departamento::with([
            'facultad',
            'carreras' => fn($q) => $q->whereNull('fecha_eliminacion')
                ->select(['id_carrera', 'nombre', 'jornada', 'sede', 'modalidad', 'id_departamento', 'fecha_eliminacion']),
        ])->withCount([
            'carreras as carreras_count' => fn($q) => $q->whereNull('fecha_eliminacion'),
        ]);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('nombre', 'ilike', "%{$search}%");
        }

        // Filter by facultad
        if ($request->has('id_facultad')) {
            $query->where('id_facultad', $request->input('id_facultad'));
        }

        // Pagination
        $departamentos = $query->orderBy('nombre')
            ->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get all facultades for the filter
        $facultades = Facultad::orderBy('nombre')->get();

        return Inertia::render('admin/Departamentos', [
            'departamentos' => $departamentos,
            'facultades' => $facultades,
            'filters' => $request->only(['search', 'id_facultad'])
        ]);
    }

    /**
     * Obtiene departamentos para un select en cascada (por facultad).
     */
    public function byFacultad(Facultad $facultad)
    {
        $departamentos = $facultad->departamentos()
            ->orderBy('nombre')
            ->get();

        return response()->json($departamentos);
    }

    /**
     * Crea un nuevo departamento asociado a una facultad.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'id_facultad' => ['required', Rule::exists(Facultad::class, 'id_facultad')],
        ]);

        try {
            $departamento = Departamento::create($validated);

            return redirect()->route('admin.departamentos.index')
                ->with('success', 'Departamento creado exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al crear departamento: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene los datos de un departamento con su facultad y carreras.
     */
    public function show(Departamento $departamento)
    {
        $departamento->load(['facultad', 'carreras']);

        return response()->json($departamento);
    }

    /**
     * Actualiza los datos de un departamento.
     */
    public function update(Request $request, Departamento $departamento)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'id_facultad' => ['required', Rule::exists(Facultad::class, 'id_facultad')],
        ]);

        $departamento->update($validated);

        return redirect()->route('admin.departamentos.index')
            ->with('success', 'Departamento actualizado exitosamente.');
    }

    /**
     * Elimina un departamento.
     */
    public function destroy(Departamento $departamento)
    {
        try {
            $departamento->delete();

            return redirect()->route('admin.departamentos.index')
                ->with('success', 'Departamento eliminado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->route('admin.departamentos.index')
                ->with('error', 'No se puede eliminar el departamento porque tiene carreras asociadas.');
        }
    }
}
