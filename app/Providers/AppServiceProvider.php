<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set PostgreSQL search_path when connection is ready
        if (config('database.default') === 'pgsql') {
            DB::connection()->getPdo()->exec('SET search_path TO "utamed.Usuario","utamed.Administrativo","utamed.Curso","utamed.Agenda",public');
        }
    }
}
