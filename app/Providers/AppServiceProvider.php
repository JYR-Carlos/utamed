<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use App\Services\Authorization\GlobalContextService;
use App\Services\Authorization\PermissionCache;
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

        $this->app->singleton(PermissionCache::class);

        // PermissionValidator depende de los tres
        $this->app->singleton(PermissionValidator::class, function ($app) {
            return new PermissionValidator(
                $app->make(ContextResolver::class),
                $app->make(GlobalContextService::class),
                $app->make(PermissionCache::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El `search_path` lo aplica el propio conector pgsql a partir de
        // `config/database.php` ('search_path' => …). Hacerlo aquí con
        // `DB::connection()->getPdo()` abría la conexión en **cada** request
        // —incluidos los que no tocan la BD— y anulaba la conexión perezosa;
        // además cualquier comando de consola fallaba si la BD no respondía.

        // Política de contraseñas del sistema. Sin esto, `Password::defaults()`
        // equivale a un simple min(8) y cada formulario inventaba su propio
        // `min:6`. Fuera de local se comprueba además contra filtraciones
        // conocidas (HIBP); el verificador de Laravel no bloquea si la API no
        // responde, así que no rompe en redes sin salida.
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->numbers();

            return $this->app->isLocal() ? $rule : $rule->uncompromised();
        });

        // `serve` (sin --no-reload) recorta el entorno del servidor embebido a
        // ServeCommand::$passthroughVariables, y compara con `in_array`, que
        // distingue mayúsculas. Windows expone `SystemRoot` y `Path` con esa
        // capitalización, así que no casan con 'SYSTEMROOT'/'PATH' de la lista
        // y se eliminan del proceso hijo. Sin `SystemRoot`, Winsock no puede
        // inicializarse y el servidor falla con «Failed to listen … (reason: ?)»
        // en todos los puertos. Reañadimos las claves tal como las nombra el SO.
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['SystemRoot', 'Path'] as $variable) {
                if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                    ServeCommand::$passthroughVariables[] = $variable;
                }
            }
        }
    }
}
