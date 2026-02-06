<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Policies\CursoPolicy;
use App\Policies\InscripcionCursoPolicy;

/**
 * Proveedor de servicios de autenticación y autorización.
 * 
 * Registra las políticas (Policies) de Laravel para control de acceso basado en roles.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de modelos a políticas de autorización.
     * 
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Curso::class => CursoPolicy::class,
        InscripcionCurso::class => InscripcionCursoPolicy::class,
    ];

    /**
     * Registra servicios de autenticación/autorización.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
