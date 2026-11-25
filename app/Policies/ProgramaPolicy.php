<?php

namespace App\Policies;

use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Models\Usuario\Usuario;
use App\Policies\Base\BaseProgramaPolicy;
use App\Services\Authorization\JefaturaCarreraResolver;
use App\Support\Permissions;

/**
 * Policy para Programa basada en permisos efectivos en el contexto del curso
 * (y Jefatura de Carrera acotada para aprobación/rechazo).
 */
class ProgramaPolicy extends BaseProgramaPolicy
{
    private const MODULO_PERMS = [
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8,
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9,
    ];

    /**
     * Verifica si el usuario puede abrir la vista/gestión de programas del curso.
     */
    public function viewPrograma(Usuario $user, $curso): bool
    {
        if (!($curso instanceof Curso)) {
            return $this->validator()->isSuperAdmin($user);
        }

        return app(CursoPolicy::class)->viewPrograma($user, $curso);
    }

    public function view(Usuario $user, Programa $model): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = $model->curso;
        if (!$curso) {
            return false;
        }

        return $this->validator()->validate($user, Permissions::CURSOS_PROGRAMAS_VER_TODOS, $curso)
            || $this->validator()->validate($user, Permissions::CURSOS_PROGRAMAS_VER_ULTIMO, $curso)
            || app(CursoPolicy::class)->viewPrograma($user, $curso);
    }

    public function update(Usuario $user, Programa $model): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = $model->curso;
        if (!$curso) {
            return false;
        }

        if ($this->validator()->validate($user, Permissions::CURSOS_PROGRAMAS_AGREGAR, $curso)) {
            return true;
        }

        foreach (self::MODULO_PERMS as $moduloPerm) {
            if ($this->validator()->validate($user, $moduloPerm, $curso)) {
                return true;
            }
        }

        return false;
    }

    public function delete(Usuario $user, Programa $model): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = $model->curso;
        if (!$curso) {
            return false;
        }

        return $this->validator()->validate($user, Permissions::CURSOS_PROGRAMAS_ELIMINAR, $curso);
    }

    public function create(Usuario $user, $parent = null): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        if ($parent instanceof Curso) {
            return $this->validator()->validate($user, Permissions::CURSOS_PROGRAMAS_AGREGAR, $parent);
        }

        return $this->validator()->validate($user, Permissions::CURSOS_PROGRAMAS_AGREGAR);
    }

    public function approve(Usuario $user, Programa $model): bool
    {
        return $this->puedeResolverPrograma($user, $model);
    }

    public function reject(Usuario $user, Programa $model): bool
    {
        return $this->puedeResolverPrograma($user, $model);
    }

    private function puedeResolverPrograma(Usuario $user, Programa $programa): bool
    {
        if ($this->validator()->isSuperAdmin($user)) {
            return true;
        }

        $curso = $programa->curso;
        if ($curso && $this->validator()->validate($user, Permissions::CURSOS_PROGRAMAS_AGREGAR)) {
            return true;
        }

        $carreraId = app(JefaturaCarreraResolver::class)->carreraId($user);
        if ($carreraId === null) {
            return false;
        }

        return Curso::where('id_curso', $programa->id_curso)
            ->whereHas('asignacionPlan.plan', fn ($q) => $q->where('id_carrera', $carreraId))
            ->exists();
    }
}
