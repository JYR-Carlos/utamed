<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use Illuminate\Http\RedirectResponse;

/**
 * Bitácora del curso (T41). Es la pestaña «Bitácora» de la ficha del curso
 * (la arma {@see \App\Services\Student\BitacoraCursoEstudiante}); la URL
 * antigua redirige a esa pestaña.
 */
class BitacoraController extends Controller
{
    /**
     * GET estudiante/cursos/{curso}/bitacora
     */
    public function show(Curso $curso): RedirectResponse
    {
        return redirect()->route('estudiante.cursos.show', [
            'curso'   => $curso->id_curso,
            'seccion' => 'bitacora',
        ]);
    }
}
