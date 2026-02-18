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

            // Use the query event's connection instead of calling DB::connection()
            $connection = $query->connection;
            $connectionName = $query->connectionName;

            // Only set search_path once per connection
            if (!isset($searchPathSet[$connectionName]) && $connection->getDriverName() === 'pgsql') {
                try {
                    // Use PDO directly to avoid triggering another query event
                    $pdo = $connection->getPdo();
                    $searchPath = config('database.connections.pgsql.search_path', 'public');
                    $pdo->exec("SET search_path TO {$searchPath}");
                    $searchPathSet[$connectionName] = true;
                } catch (\Exception $e) {
                    // Silently fail
                }
            }
        });
    }
}
