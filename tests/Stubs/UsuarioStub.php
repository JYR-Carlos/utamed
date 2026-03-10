<?php

namespace App\Models\Usuario;

use App\Contracts\HasContext;
use App\Services\Authorization\PermissionValidator;

/**
 * Stub de Usuario para testing sin BD
 * 
 * Incluye métodos de autorización para tests de can()
 */
class Usuario
{
    public $id_usuario;

    public function getAttribute($key)
    {
        if ($key === 'id_usuario') {
            return $this->id_usuario;
        }
        return null;
    }

    /**
     * Requerido por las Policies base (HasBasePolicyMethods::before) que comprueban
     * superadmin antes de evaluar cualquier método de la Policy.
     * El stub no es superadmin por defecto; los tests de superadmin usan slug '*' directo.
     */
    public function isSuperAdmin(): bool
    {
        return false;
    }

    /**
     * Verificar permiso con resolución automática de contexto desde un recurso.
     * Espeja la implementación real del modelo Usuario.
     * 
     * @param \App\Support\Permissions $permission Enum del permiso
     * @param HasContext|null $resource Instancia del modelo (opcional)
     * @return bool
     */
    public function hasPermissionFor(\App\Support\Permissions $permission, ?HasContext $resource = null): bool
    {
        return app(PermissionValidator::class)
            ->validate($this, $permission, $resource);
    }

    /**
     * Override del método can() de Laravel para integrar con el sistema de permisos.
     * Espeja la implementación real del modelo Usuario:
     * 
     * - Enum Permissions o slug con ':' → PermissionValidator directo (Policy NO aplica)
     * - Habilidad estándar ('view', 'create') → Gate (Policy system)
     * 
     * @param string|\App\Support\Permissions $ability
     * @param array|mixed $arguments
     * @return bool
     */
    public function can($ability, $arguments = []): bool
    {
        // Normalizar: si ya es un enum Permissions, ir directo al validador
        if ($ability instanceof \App\Support\Permissions) {
            $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
            $resource = ($model instanceof HasContext) ? $model : null;
            return $this->hasPermissionFor($ability, $resource);
        }

        // String slug (contiene ':') o wildcard global ('*'): PermissionValidator directo
        $isSlug = str_contains($ability, ':');
        $isWildcard = $ability === \App\Support\Permissions::GLOBAL_WILDCARD->value;

        if ($isSlug || $isWildcard) {
            $permission = \App\Support\Permissions::tryFrom($ability);
            if ($permission === null) {
                return false;
            }
            $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
            $resource = ($model instanceof HasContext) ? $model : null;
            return $this->hasPermissionFor($permission, $resource);
        }

        // Habilidad estándar: delegar al Gate con $this como usuario (igual que Authorizable::can)
        // Gate::forUser($this) → Gate busca Policy del modelo → llama $policy->method($this, $model)
        return \Illuminate\Support\Facades\Gate::forUser($this)->check($ability, $arguments);
    }
}

