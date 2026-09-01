<?php

namespace App\Services\Authorization;

use App\Models\Usuario\Usuario;
use App\Support\Permissions;

/**
 * Único punto de verdad para "¿puede este actor delegar este permiso/rol, en estos contextos?".
 *
 * Extraído de RoleAssignmentBuilder::validateActorAuthorization() /
 * PermissionAssignmentBuilder::validateActorAuthorization() para que cualquier vía de
 * escritura (builders fluidos, sincronización masiva desde UsuarioController, futuros
 * endpoints) use la misma regla en vez de reimplementar su propio guard.
 *
 * @see docs/auditoria-arquitectura/auditoria_relbac_autorizacion.md R-5
 */
class DelegationAuthorizer
{
    public function __construct(private readonly PermissionValidator $validator)
    {
    }

    /**
     * @param  Permissions[]  $permissions  Permisos que otorgaría el rol/permiso a asignar
     * @param  int[]          $contextIds   Contextos de destino de la asignación
     */
    public function actorPuedeDelegar(Usuario $actor, array $permissions, array $contextIds): bool
    {
        if ($this->validator->isSuperAdmin($actor)) {
            return true;
        }

        foreach ($permissions as $permission) {
            $delegables = $this->validator->getContextsWhereDelegablePermission($actor, $permission);

            if (!empty(array_diff($contextIds, $delegables))) {
                return false;
            }
        }

        return true;
    }
}
