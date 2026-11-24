<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Agenda\Actividad;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;

/**
 * Quién puede escribir en la evaluación de una actividad (grupos, notas): el
 * administrador, el titular del curso o el docente del componente del que
 * cuelga la actividad.
 */
trait AutorizaEvaluacion
{
    protected function puedeEditarEvaluacion(Usuario $user, Curso $curso, Actividad $actividad): bool
    {
        if ($user->isSuperAdmin() || $user->hasAnyRoleGlobally(Usuario::ROLES_ADMINISTRATIVOS)) {
            return true;
        }

        $docente = $user->docente;

        if (! $docente) {
            return false;
        }

        // El titular manda sobre todas las actividades del curso.
        if ($curso->id_docente_titular === $docente->id_docente) {
            return true;
        }

        $actividad->loadMissing('componente');

        return $actividad->componente !== null
            && $actividad->componente->docenteComponentes()
                ->where('id_docente', $docente->id_docente)
                ->exists();
    }
}
