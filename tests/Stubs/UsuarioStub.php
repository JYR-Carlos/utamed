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
     * Verificar permiso con resolución automática de contexto desde un recurso.
     * 
     * @param string $permission Slug del permiso
     * @param HasContext|null $resource Instancia del modelo (opcional)
     * @return bool
     */
    public function hasPermissionFor(string $permission, ?HasContext $resource = null): bool
    {
        return app(PermissionValidator::class)
            ->validate($this, $permission, $resource);
    }

    /**
     * Override del método can() de Laravel para integrar con el sistema de permisos.
     * 
     * FLUJO DE AUTORIZACIÓN:
     * 1. PRIORIDAD: Policies registradas en AuthServiceProvider
     *    - Si existe Policy para el modelo, Laravel la ejecuta automáticamente
     *    - La Policy internamente debe usar PermissionValidator
     * 
     * 2. FALLBACK: PermissionValidator directo (solo si NO hay Policy)
     *    - Para slugs de permisos ('recurso:accion') sin Policy registrada
     *    - Permite usar $user->can('facultad:ver', $facultad) sin Policy
     * 
     * @param string $ability Nombre de la habilidad ('view', 'create') o slug ('facultad:ver')
     * @param array|mixed $arguments Argumentos (modelo, contexto, etc.)
     * @return bool
     */
    public function can($ability, $arguments = []): bool
    {
        // Para tests, simplificar: parent::can() siempre retorna false (no hay Gate real)
        // Entonces pasamos directo al fallback
        
        // Verificar si es un slug de permiso (contiene ':' o es wildcard global '*')
        if (str_contains($ability, ':') || $ability === '*') {
            $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
            
            // Verificar si existe una Policy registrada para el modelo
            if (is_object($model)) {
                $gate = app(\Illuminate\Contracts\Auth\Access\Gate::class);
                
                // Si hay Policy, respetar su decisión (false)
                if ($gate->getPolicyFor($model) !== null) {
                    return false;
                }
            }
            
            // No hay Policy registrada, usar PermissionValidator como fallback
            $resource = ($model instanceof HasContext) ? $model : null;
            return $this->hasPermissionFor($ability, $resource);
        }
        
        // Para habilidades estándar sin Policy, retornar false
        return false;
    }
}

