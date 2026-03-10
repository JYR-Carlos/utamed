<?php

namespace Tests\Stubs;

/**
 * Policy stub de Carrera para tests de autorización.
 * 
 * Simula una Policy registrada para CarreraStub (App\Models\Administrativo\Carrera).
 * Los tests lo usan para verificar que can() delega a la Policy para habilidades estándar.
 */
class CarreraPolicyStub
{
  /**
   * view() permite siempre — para probar que la Policy puede autorizar.
   */
  public function view($user, $model): bool
  {
    return true;
  }

  /**
   * delete() deniega siempre — para probar que la Policy puede denegar.
   */
  public function delete($user, $model): bool
  {
    return false;
  }
}

/**
 * Policy stub que simula un before() hook que verifica superadmin
 * para tests de integración de hooks.
 */
class CarreraPolicySuperadminStub
{
    /**
     * before() hook: intercepta todas las autorizaciones
     * Si el usuario es superadmin, autoriza TODO
     * Retorna null para continuar con la evaluación normal si no es superadmin
     */
    public function before($user, string $ability): ?bool
    {
        // Si user tiene isSuperAdmin() y retorna true, autorizar TODO
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }
        return null; // Continuar con el método correspondiente
    }

    /**
     * delete() deniega siempre
     */
    public function delete($user, $model): bool
    {
        return false;
    }
}