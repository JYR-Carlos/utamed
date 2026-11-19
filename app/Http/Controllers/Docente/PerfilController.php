<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PerfilController extends Controller
{
    /**
     * Mi perfil del docente: datos de la Intranet en sólo lectura (RUT, correo,
     * cargo, grado y título). Desde la revisión «Mi UTAmed» no muestra el
     * semestre, el usuario ni los cursos asignados; la contraseña se cambia
     * en un modal que usa el mismo PUT /settings/password.
     */
    public function show(): Response
    {
        /** @var Usuario $user */
        $user = Auth::user();
        $docente = $user->docente;

        return Inertia::render('docente/Perfil', [
            'perfil' => [
                'nombre_completo' => $user->nombre_completo,
                'rut' => $user->rut,
                'email' => $user->email,
                'grado' => $docente->grado,
                'titulo' => $docente->titulo,
                'cargo' => $docente->cargo,
                'ultimo_cambio_clave' => $user->fecha_cambio_passhash?->toIso8601String(),
            ],
        ]);
    }
}
