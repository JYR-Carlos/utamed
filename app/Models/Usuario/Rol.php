<?php

namespace App\Models\Usuario;

use App\Enums\ContextType;
use App\Models\Base\Usuario\BaseRol;
use App\Services\Authorization\PermissionContextConstraints;
use App\Support\Permissions;

/**
 * Modelo Rol
 * 
 * Extiende de BaseRol (auto-generado)
 * Agrega aquí tus personalizaciones, relaciones adicionales, etc.
 */
class Rol extends BaseRol
{
    // Agrega aquí tus métodos personalizados
    // Scopes personalizados
    // Relaciones adicionales
    // Accessors/Mutators
    // etc.

    /**
     * Obtiene los tipos compatibles de un rol llamando a las funciones subyacentes.
     * 
     * @throws \ValueError Si el tipo de permiso no es válido en Permissions enum
     *
     * @return ContextType[] Array de ContextType compatibles con los permisos de este rol
     * 
     * @see PermissionContextConstraints::getCompatibleContexts() 
     * para entender cómo se determinan los contextos compatibles a partir de los permisos.
     */
    public function getCompatibleContexts(): array
    {
        try {
            $permisos = $this->permisos()
                ->pluck('slug')
                ->map(Permissions::from(...))
                ->all();

            $permissionContextConstraint = app(PermissionContextConstraints::class);

            return $permissionContextConstraint->getCompatibleContexts($permisos);

        } catch (\ValueError $e) {
            \Log::error(
                "Se ha detectado un permiso malformado al obtener los permisos de este rol: {$this->id_rol}: " .
                $e->getMessage()
            );
            throw new \Exception("Error al obtener tipos compatibles: " . $e->getMessage());
        }
    }

    /**
     * Obtiene todos los roles asignables (todos excepto SuperAdmin).
     * 
     * @return \Illuminate\Support\Collection<
     *  int, 
     *  array{
     *      id_rol: mixed, 
     *      nombre: mixed, 
     *      valid_types: array
     *  }
     * > Colección de roles con su id, nombre y tipos compatibles.
     */
    public static function getAllAssignableRoles()
    {
        $availableRoles = Rol::
            whereDoesntHave('permisos', function ($query) {
                $query->where('slug', [Permissions::GLOBAL_WILDCARD->value]);
            })
            ->orderBy('nombre')
            ->get()
            ->map(fn(Rol $rol) => [
                'id_rol' => $rol->id_rol,
                'nombre' => $rol->nombre,
                'valid_types' => $rol->getCompatibleContexts()
            ])
            ->values();

        return $availableRoles;
    }

}