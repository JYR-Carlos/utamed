<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Curso\Unidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DocenteUnidadController extends Controller
{
    public function index(Curso $curso)
    {
        $user = auth()->user();
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para acceder a este curso.');
        }

        $unidades = Unidad::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->orderBy('num_unidad')
            ->get();

        return response()->json([
            'curso' => [
                'id_curso' => $curso->id_curso,
                'es_plantilla' => (bool) $curso->es_plantilla,
                'nombre' => $curso->nombre,
                'cod_curso' => $curso->cod_curso,
            ],
            'unidades' => $unidades,
        ]);
    }

    public function store(Request $request, Curso $curso)
    {
        $user = auth()->user();
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para crear unidades en este curso.');
        }

        if (!$curso->es_plantilla) {
            return back()->withErrors(['error' => 'El programa aprobado bloquea la creación/edición de unidades.']);
        }

        $validated = $request->validate([
            'num_unidad' => 'nullable|integer|min:1|max:32767',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $validated['id_curso'] = $curso->id_curso;
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
            return back()
                ->withErrors(['error' => 'Error al crear la unidad: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, Curso $curso, Unidad $unidad)
    {
        $user = auth()->user();
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para editar unidades en este curso.');
        }

        if (!$curso->es_plantilla) {
            return back()->withErrors(['error' => 'El programa aprobado bloquea la creación/edición de unidades.']);
        }

        if ($unidad->id_curso !== $curso->id_curso || $unidad->es_plantilla !== $curso->es_plantilla) {
            abort(404, 'Unidad no pertenece a este curso/estado.');
        }

        $validated = $request->validate([
            'num_unidad' => 'required|integer|min:1|max:32767',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        try {
            $unidad->update($validated);
            return back()->with('success', 'Unidad actualizada correctamente.');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Error al actualizar la unidad: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Curso $curso, Unidad $unidad)
    {
        $user = auth()->user();
        if (!$user->docente) {
            abort(403, 'No tienes un perfil docente.');
        }

        $isDocente = $curso->secciones()
            ->where('id_docente', $user->docente->id_docente)
            ->exists();

        if (!$isDocente && !$user->is_admin) {
            abort(403, 'No tienes permiso para eliminar unidades en este curso.');
        }

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
            return back()
                ->withErrors(['error' => 'Error al eliminar la unidad: ' . $e->getMessage()]);
        }
    }
}
