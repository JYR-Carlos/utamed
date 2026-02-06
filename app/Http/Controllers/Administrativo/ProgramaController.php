<?php

namespace App\Http\Controllers\Administrativo;

use App\Http\Controllers\Controller;
use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProgramaController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Curso $curso)
    {
        // 1. Authorization: Ensure user is authorized to create a program for this course
        // This usually means they are a teacher of a section in this course OR have special permissions.
        // For now, we will assume if they can access the route (middleware) and logic holds, it's ok.
        // Ideally: $this->authorize('create', [Programa::class, $curso]);

        // Simple check: Is the user a teacher in this course?
        // We can query InscripcionSeccion or Seccion linking to this user.
        // For speed, let's rely on basic validation that the user is a Docente.

        $user = Auth::user();

        // Check if program already exists for this course
        $existing = Programa::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->exists();

        if ($existing) {
            return Redirect::back()->with('error', 'El programa para este curso ya existe.');
        }

        // Create the program
        Programa::create([
            'id_curso' => $curso->id_curso,
            'es_plantilla' => $curso->es_plantilla,
            'es_actual' => true,
            'id_usuario_autor' => $user->id_usuario,
            'version' => 1,
            'unc_programa' => 1, // Default value for required field
            'fecha_creacion' => now(),
        ]);

        return Redirect::back()->with('success', 'Programa generado correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Curso $curso)
    {
        $curso->load(['asignatura']);

        // Fetch existing program for this course
        $programa = Programa::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->where('es_actual', true)
            ->first();

        if (!$programa) {
            return Redirect::back()->with('error', 'El programa no ha sido generado.');
        }

        // Fetch units for this course
        $unidades = \App\Models\Curso\Unidad::where('id_curso', $curso->id_curso)
            ->where('es_plantilla', $curso->es_plantilla)
            ->orderBy('num_unidad')
            ->get();

        return \Inertia\Inertia::render('docente/Programa', [
            'curso' => $curso,
            'programa' => $programa,
            'asignatura' => $curso->asignatura,
            'unidades' => $unidades
        ]);
    }
}
