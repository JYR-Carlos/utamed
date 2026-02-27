<?php

namespace App\Policies\Base\Traits;

use App\Models\Usuario\Usuario;
use App\Services\Authorization\PermissionValidator;
use App\Support\Permissions;

/**
 * Trait que provee los métodos base para todas las Policies autogeneradas.
 *
 * Soporta tres patrones de extensión desde la Extended Policy:
 *
 *   Patrón 1 - Lógica extra después (hooks):
 *     Sobrescribir customXXX() → la base corre primero, el hook es el fallback.
 *
 *   Patrón 2 - Tu lógica primero, base como fallback:
 *     Sobrescribir el método CRUD completo y llamar parent:: al final.
 *
 *   Patrón 3 - Reemplazar completamente:
 *     Sobrescribir el método CRUD sin llamar parent::.
 */
trait HasBasePolicyMethods
{
  /**
   * Obtiene la instancia del validador de permisos (lazy via container).
   */
  protected function validator(): PermissionValidator
  {
    return app(PermissionValidator::class);
  }

  /**
   * Bypass para superadmin.
   * Laravel lo invoca antes de cualquier método de policy.
   * Retornar null (sin return) deja continuar la evaluación normal.
   */
  public function before(Usuario $user, string $ability): ?bool
  {
    if ($user->isSuperAdmin()) {
      return true;
    }

    return null;
  }

  /**
   * Verifica si el usuario es superadmin.
   * 
   * @deprecated Usar directamente $user->isSuperAdmin() en su lugar.
   */
  protected function isSuperAdmin(Usuario $user): bool
  {
    return $user->isSuperAdmin();
  }

  // =========================================================
  // HOOKS CUSTOMIZABLES (Patrón 1)
  // Sobrescribir en la Extended Policy para lógica extra.
  // Solo se ejecutan si la validación base falla.
  // =========================================================

  protected function customViewAny(Usuario $user): bool
  {
    return false;
  }

  protected function customView(Usuario $user, $model): bool
  {
    return false;
  }

  protected function customCreate(Usuario $user, $parent = null): bool
  {
    return false;
  }

  protected function customUpdate(Usuario $user, $model): bool
  {
    return false;
  }

  protected function customDelete(Usuario $user, $model): bool
  {
    return false;
  }

  // =========================================================
  // HELPERS DE UTILIDAD
  // =========================================================

  /**
   * Resuelve el/los id_contexto de un recurso (directo o jerárquico).
   * Requiere que el modelo use el trait ContextAware.
   *
   * Retorna el array completo de contextos o null si el modelo no tiene contexto.
   */
  protected function resolveContextId($model): ?array
  {
    return $model?->getContextId();
  }

  /**
   * Construye el slug de permiso con formato "recurso:acción" y lo resuelve a un Permissions enum.
   *
   * @throws \ValueError Si el slug generado no existe en el enum Permissions
   */
  protected function buildPermissionSlug(string $resource, string $action): Permissions
  {
    return Permissions::from("{$resource}:{$action}");
  }
}
