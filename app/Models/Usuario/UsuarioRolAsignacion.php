<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuarioRolAsignacion;
use App\Services\Authorization\PermissionCache;

/**
 * Modelo UsuarioRolAsignacion
 *
 * Extiende de BaseUsuarioRolAsignacion (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class UsuarioRolAsignacion extends BaseUsuarioRolAsignacion
{
    /**
     * Toda alta, baja o modificación de una asignación de rol invalida los
     * veredictos cacheados del usuario afectado.
     *
     * Va por eventos de modelo para cubrir cualquier vía de escritura
     * (RoleAssignmentBuilder, invalidateRole, updateOrCreate…) sin tener que
     * acordarse en cada una. **No cubre** las actualizaciones masivas del tipo
     * `Model::where(...)->update(...)`, que no disparan eventos: ésas se
     * invalidan a mano (ver `UsuarioController::syncPermissions`).
     */
    protected static function booted(): void
    {
        $olvidar = fn(self $asignacion) => app(PermissionCache::class)
            ->olvidarUsuario((int) $asignacion->id_usuario);

        static::saved($olvidar);
        static::deleted($olvidar);
    }
}