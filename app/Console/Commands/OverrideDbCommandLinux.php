<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class OverrideDbCommandLinux extends Command
{
    /**
     * Interceptamos el comando nativo db:wipe
     */
    protected $signature = 'db:wipe-linux';

    /**
     * Descripción para artisan list
     */
    protected $description = '[DEPRECADO] Usa db:wipe en su lugar (funciona en Linux y Windows)';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle()
    {
        $this->warn("⚠️  [DEPRECADO] El comando 'db:wipe-linux' está deprecado.");
        $this->line("   Usa 'php artisan db:wipe' o 'composer db:soft-reset', que funcionan de forma nativa en Linux.\n");

        // passthru ejecuta el comando y canaliza la entrada/salida de la terminal de forma nativa
        passthru('composer db:soft-reset', $returnCode);

        // Retornamos el mismo código de estado que devolvió Composer (0 es éxito)
        return $returnCode === 0 ? SymfonyCommand::SUCCESS : SymfonyCommand::FAILURE;
    }
}
