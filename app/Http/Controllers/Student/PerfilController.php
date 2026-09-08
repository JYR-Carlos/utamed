<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Ficha de datos del estudiante autenticado (sólo lectura).
 */
class PerfilController extends Controller
{
    public function show()
    {
        /** @var Usuario $user */
        $user = Auth::user();
        $estudiante = $user->estudiante()->with('carrera')->firstOrFail();

        $totalCursos = InscripcionCurso::where('id_estudiante', $estudiante->id_estudiante)
            ->where('estado_inscripcion', 'INSCRITO')
            ->count();

        return Inertia::render('student/Perfil', [
            'perfil' => [
                'nombre_completo' => $user->nombre_completo,
                'rut' => $user->rut,
                'email' => $user->email,
                'username' => $user->username,
                'carrera_nombre' => $estudiante->carrera->nombre,
                'agno_ingreso' => $estudiante->agno_ingreso,
                'total_cursos' => $totalCursos,
            ],
            'semestreActual' => Carbon::now()->month > 6 ? 2 : 1,
        ]);
    }
}
