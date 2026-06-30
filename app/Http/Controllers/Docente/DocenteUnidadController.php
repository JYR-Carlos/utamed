<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestión de unidades de un curso (perspectiva del docente).
 *
 * Las unidades sólo son editables mientras el curso es plantilla (`es_plantilla`);
 * un programa aprobado bloquea su creación, edición y eliminación. El acceso se
 * controla mediante las policies de `Unidad`.
 */
class DocenteUnidadController extends Controller
{
    /**
     * Lista las unidades del curso.
     *
     * GET docente/cursos/{curso}/unidades
     */
    public function index(Curso $curso): JsonResponse
    {
        $this->authorize('viewPrograma', $curso);

        $unidades = Unidad::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->orderBy('num_unidad')
            ->get();

        return response()->json([
            'curso' => [
                'id_curso'     => $curso->id_curso,
                'es_plantilla' => (bool) $curso->es_plantilla,
                'nombre'       => $curso->nombre,
                'cod_curso'    => $curso->cod_curso,
            ],
            'unidades' => $unidades,
        ]);
    }

    /**
     * Crea una unidad en el curso (sólo si es plantilla).
     *
     * POST docente/cursos/{curso}/unidades
     */
    public function store(Request $request, Curso $curso): RedirectResponse
    {
        $this->authorize('create', [Unidad::class, $curso]);

        if (!$curso->es_plantilla) {
            return back()->withErrors(['error' => 'El programa aprobado bloquea la creación/edición de unidades.']);
        }

        $validated = $request->validate([
            'num_unidad'  => 'nullable|integer|min:1|max:32767',
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $validated['id_curso']     = $curso->id_curso;
        $validated['es_plantilla'] = (bool) $curso->es_plantilla;

        $nextId = (int) Unidad::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->max('id_unidad');
        $validated['id_unidad'] = $nextId ? ($nextId + 1) : 1;

        if (!isset($validated['num_unidad'])) {
            $validated['num_unidad'] = $validated['id_unidad'];
        }

        try {
            $unidad = Unidad::create($validated);
            return back()->with('success', "Unidad '{$unidad->nombre}' creada correctamente.");
        } catch (\Exception $e) {
            Log::error('Error al crear la unidad: ' . $e->getMessage());

            return back()
                ->withErrors(['error' => 'No se pudo crear la unidad. Por favor, inténtalo nuevamente.'])
                ->withInput();
        }
    }

    /**
     * Actualiza una unidad del curso (sólo si es plantilla).
     *
     * PUT docente/cursos/{curso}/unidades/{unidad}
     */
    public function update(Request $request, Curso $curso, Unidad $unidad): RedirectResponse
    {
        $this->authorize('update', $unidad);

        if (!$curso->es_plantilla) {
            return back()->withErrors(['error' => 'El programa aprobado bloquea la creación/edición de unidades.']);
        }

        if ($unidad->id_curso !== $curso->id_curso || $unidad->es_plantilla !== $curso->es_plantilla) {
            abort(404, 'Unidad no pertenece a este curso/estado.');
        }

        $validated = $request->validate([
            'num_unidad'  => 'required|integer|min:1|max:32767',
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        try {
            $unidad->update($validated);
            return back()->with('success', 'Unidad actualizada correctamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar la unidad: ' . $e->getMessage());

            return back()
                ->withErrors(['error' => 'No se pudo actualizar la unidad. Por favor, inténtalo nuevamente.'])
                ->withInput();
        }
    }

    /**
     * Elimina una unidad y sus actividades (sólo si el curso es plantilla).
     *
     * DELETE docente/cursos/{curso}/unidades/{unidad}
     */
    public function destroy(Curso $curso, Unidad $unidad): RedirectResponse
    {
        $this->authorize('delete', $unidad);

        if (!$curso->es_plantilla) {
            return back()->withErrors(['error' => 'El programa aprobado bloquea la eliminación de unidades.']);
        }

        if ($unidad->id_curso !== $curso->id_curso || $unidad->es_plantilla !== $curso->es_plantilla) {
            abort(404, 'Unidad no pertenece a este curso/estado.');
        }

        try {
            $nombre = $unidad->nombre;
            DB::transaction(function () use ($unidad) {
                $unidad->actividades()->delete();
                $unidad->delete();
            });
            return back()->with('success', "Unidad '{$nombre}' eliminada correctamente.");
        } catch (\Exception $e) {
            Log::error('Error al eliminar la unidad: ' . $e->getMessage());

            return back()
                ->withErrors(['error' => 'No se pudo eliminar la unidad. Por favor, inténtalo nuevamente.']);
        }
    }
}
