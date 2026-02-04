<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set PostgreSQL search_path for each new connection
        DB::listen(function ($query) {
            static $searchPathSet = [];

            $connectionName = DB::connection()->getName();

            // Only set search_path once per connection
            if (!isset($searchPathSet[$connectionName]) && DB::connection()->getDriverName() === 'pgsql') {
                try {
                    // Use PDO directly to avoid triggering another query event
                    $pdo = DB::connection()->getPdo();
                    $pdo->exec('SET search_path TO "utamed.Usuario", "utamed.Administrativo", "utamed.Curso", "utamed.Agenda", public');
                    $searchPathSet[$connectionName] = true;
                } catch (\Exception $e) {
                    // Silently fail
                }
            }
        });
    }
}
