<?php

namespace App\Policies;

use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Usuario;
use App\Policies\Base\BaseInscripcionCursoPolicy;
use App\Support\Permissions;

/**
 * Policy para autorización de InscripcionCurso basada en permisos efectivos.
 */
class InscripcionCursoPolicy extends BaseInscripcionCursoPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->validator()->isSuperAdmin($user)
            || !empty($this->validator()->getContextsFromPermission($user, Permissions::CURSOS_INSCRIPCIONES_VER));
    }

    public function view(Usuario $user, InscripcionCurso $inscripcion): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = $inscripcion->curso;
        if ($curso && $this->validator()->validate($user, Permissions::CURSOS_INSCRIPCIONES_VER, $curso)) {
            return true;
        }

        return (bool) ($user->estudiante && $inscripcion->id_estudiante === $user->estudiante->id_estudiante);
    }

    public function create(Usuario $user, $parent = null): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        if ($parent instanceof Curso) {
            return $this->validator()->validate($user, Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS, $parent);
        }

        return !empty($this->validator()->getContextsFromPermission($user, Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS));
    }

    public function createForCurso(Usuario $user, int $idCurso): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = Curso::find($idCurso);
        if (!$curso) {
            return false;
        }

        return $this->validator()->validate($user, Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS, $curso);
    }

    public function update(Usuario $user, InscripcionCurso $inscripcion): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = $inscripcion->curso;
        if (!$curso) {
            return false;
        }

        return $this->validator()->validate($user, Permissions::CURSOS_INSCRIPCIONES_INSCRIBIR_ALUMNOS, $curso);
    }

    public function delete(Usuario $user, InscripcionCurso $inscripcion): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = $inscripcion->curso;
        if (!$curso) {
            return false;
        }

        return $this->validator()->validate($user, Permissions::CURSOS_INSCRIPCIONES_ELIMINAR_INSCRIPCIONES, $curso);
    }

    public function export(Usuario $user): bool
    {
        return $this->viewAny($user);
    }
}
