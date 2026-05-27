<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class OverrideDbCommandTesting extends Command
{
    /**
     * Interceptamos el comando nativo db:wipe
     */
    protected $signature = 'db:wipe:testing';

    /**
     * Descripción para artisan list
     */
    protected $description = 'Alias directo para composer db:soft-reset-testing';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle()
    {
        // passthru ejecuta el comando y canaliza la entrada/salida de la terminal de forma nativa
        passthru('composer db:soft-reset-testing', $returnCode);

        // Retornamos el mismo código de estado que devolvió Composer (0 es éxito)
        return $returnCode === 0 ? SymfonyCommand::SUCCESS : SymfonyCommand::FAILURE;
    }
}
