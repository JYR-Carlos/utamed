<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PerfilController extends Controller
{
    public function show(): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();
        $docente = $user->docente;

        $idDocente = (int) $docente->id_docente;

        $totalCursos = Curso::where('es_plantilla', false)
            ->whereNull('fecha_eliminacion')
            ->where(function ($q) use ($idDocente) {
                $q->where('id_docente_titular', $idDocente)
                    ->orWhereHas('componentes.docenteComponentes', fn($dq) => $dq->where('id_docente', $idDocente));
            })
            ->count();

        return Inertia::render('docente/Perfil', [
            'perfil' => [
                'nombre_completo' => $user->nombre_completo,
                'rut' => $user->rut,
                'email' => $user->email,
                'username' => $user->username,
                'grado' => $docente->grado,
                'titulo' => $docente->titulo,
                'cargo' => $docente->cargo,
                'total_cursos' => $totalCursos,
            ],
            'semestreActual' => Carbon::now()->month > 6 ? 2 : 1,
        ]);
    }
}
