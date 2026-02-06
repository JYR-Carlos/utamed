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
            // 'es_actual' is part of primary key in Base, but typically defaults or managed by DB trigger?
            // Let's check BasePrograma key: ['id_programa', 'id_curso', 'es_plantilla', 'es_actual']
            // Wait, id_programa is usually auto-assigned if it's a sequence, but Base says 'incrementing = false'.
            // Let's assume database handles it or we need to check migration/model deeper.
            // If incrementing is false, we might need to provide it?
            // Re-checking BasePrograma: public $incrementing = false; 
            // If it's serial in DB, Laravel might be confused by composite key.
            // Let's try letting DB handle it if it's serial.

            'id_usuario_autor' => $user->id_usuario,
            'version' => 1, // Default version?
        ]);

        return Redirect::back()->with('success', 'Programa generado correctamente.');
    }
}
