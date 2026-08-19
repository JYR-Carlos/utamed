<?php

namespace App\Models\Usuario;

use App\Models\Base\Usuario\BaseUsuarioPermisoEspecial;
use App\Services\Authorization\PermissionCache;

/**
 * Modelo UsuarioPermisoEspecial
 *
 * Extiende de BaseUsuarioPermisoEspecial (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class UsuarioPermisoEspecial extends BaseUsuarioPermisoEspecial
{
    /**
     * Igual que en UsuarioRolAsignacion: toda escritura sobre un permiso especial
     * invalida la caché del usuario afectado. Las actualizaciones masivas no
     * disparan eventos y se invalidan a mano.
     */
    protected static function booted(): void
    {
        $olvidar = fn(self $permiso) => app(PermissionCache::class)
            ->olvidarUsuario((int) $permiso->id_usuario);

        static::saved($olvidar);
        static::deleted($olvidar);
    }
}