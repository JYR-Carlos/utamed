<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use App\Services\Authorization\GlobalContextService;
use App\Services\Authorization\PermissionValidator;
use App\Services\ContextResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton: una sola consulta a BD por ciclo de vida del proceso
        $this->app->singleton(GlobalContextService::class);

        // ContextResolver necesita el contexto global para modelos de tipo 'global'
        $this->app->singleton(ContextResolver::class, function ($app) {
            return new ContextResolver(
                $app->make(GlobalContextService::class)
            );
        });

        // PermissionValidator depende de ambos
        $this->app->singleton(PermissionValidator::class, function ($app) {
            return new PermissionValidator(
                $app->make(ContextResolver::class),
                $app->make(GlobalContextService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set PostgreSQL search_path when connection is ready
        if (config('database.default') === 'pgsql') {
            $searchPath = config('database.connections.pgsql.search_path', 'public');
            DB::connection()->getPdo()->exec("SET search_path TO {$searchPath}");
        }
    }
}
