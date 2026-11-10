<?php

namespace App\Policies\Base;

use App\Models\Usuario\Usuario;
use App\Models\Curso\InscripcionCurso;
use App\Policies\Base\Traits\HasBasePolicyMethods;

/**
 * Policy Base para InscripcionCurso - AUTOGENERADA
 * ⚠️ NO EDITAR - Se sobrescribe al regenerar
 *
 * Para personalizaciones, extender en app/Policies/InscripcionCursoPolicy.php:
 *   - Patrón 1: Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   - Patrón 2: Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   - Patrón 3: Sobrescribir sin parent::      → reemplaza la base completamente
 */
abstract class BaseInscripcionCursoPolicy
{
    use HasBasePolicyMethods;

    protected string $resource = 'cursos/inscripciones';

    public function viewAny(Usuario $user): bool
    {
        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'ver'))) {
            return true;
        }

        return $this->customViewAny($user);
    }

    public function view(Usuario $user, InscripcionCurso $model): bool
    {
        $contextId = $this->resolveContextId($model);

        if ($this->validator()->validate($user, $this->buildPermissionSlug($this->resource, 'ver'), $model, $contextId)) {
            return true;
        }

        return $this->customView($user, $model);
    }

    public function create(Usuario $user, $parent = null): bool
    {
        // 'cursos/inscripciones:crear' no existe en permissions_config.php; delega al hook/override
        return $this->customCreate($user, $parent);
    }

    public function update(Usuario $user, InscripcionCurso $model): bool
    {
        // 'cursos/inscripciones:editar' no existe en permissions_config.php; delega al hook/override
        return $this->customUpdate($user, $model);
    }

    public function delete(Usuario $user, InscripcionCurso $model): bool
    {
        // 'cursos/inscripciones:eliminar' no existe en permissions_config.php; delega al hook/override
        return $this->customDelete($user, $model);
    }
}
