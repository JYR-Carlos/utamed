<?php

namespace App\Console\Commands;

use App\Models\Usuario\Usuario;
use App\Support\Rut;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ForzarResetPasswordUsuario extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usuario:forzar-reset-password
                            {termino? : Nombre, RUT o ID del usuario a buscar}
                            {--force : No solicitar confirmación antes de aplicar el reseteo}';

    /**
     * Aliases for the command.
     *
     * @var array<int, string>
     */
    protected $aliases = ['usuario:reset-password'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fuerza el reseteo de la contraseña de un usuario a su RUT (sin DV, guion ni puntos) y exige cambio en el próximo login';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->output->title('UTAMED - Reseteo Forzado de Contraseña de Usuario');

        $termino = (string) ($this->argument('termino') ?? '');

        if (trim($termino) === '') {
            $termino = (string) $this->ask('Ingrese el Nombre, RUT o ID del usuario a buscar');
        }

        $termino = trim($termino);
        if ($termino === '') {
            $this->error('No se ingresó ningún término de búsqueda.');
            return self::FAILURE;
        }

        // Búsqueda de candidatos
        $candidatos = $this->buscarCandidatos($termino);

        if ($candidatos->isEmpty()) {
            $this->warn("No se encontraron usuarios coincidentes con: '{$termino}'");
            return self::FAILURE;
        }

        /** @var Usuario $usuarioSeleccionado */
        $usuarioSeleccionado = null;

        if ($candidatos->count() === 1) {
            $usuarioSeleccionado = $candidatos->first();
            $this->info("Se encontró 1 coincidencia exacta.");
        } else {
            $this->warn("Se encontraron {$candidatos->count()} coincidencias (posibles colisiones por match incompleto):");
            $this->newLine();

            $headers = ['#', 'ID', 'RUT', 'Nombre Completo', 'Username / Email', 'Estado'];
            $rows = [];

            foreach ($candidatos as $index => $candidato) {
                $num = $index + 1;
                $nombreCompleto = trim("{$candidato->nombre1} {$candidato->nombre2} {$candidato->apellido1} {$candidato->apellido2}");
                $estado = $candidato->esta_activo ? 'ACTIVO' : 'INACTIVO';
                $rows[] = [
                    "[{$num}]",
                    $candidato->id_usuario,
                    $candidato->rut ?? 'S/RUT',
                    $nombreCompleto,
                    "{$candidato->username} ({$candidato->email})",
                    $estado,
                ];
            }

            $this->table($headers, $rows);
            $this->newLine();

            $opcion = (int) $this->ask("Seleccione el número del usuario a modificar (1 - {$candidatos->count()}, 0 para cancelar)", '0');

            if ($opcion < 1 || $opcion > $candidatos->count()) {
                $this->info('Operación cancelada por el usuario.');
                return self::SUCCESS;
            }

            $usuarioSeleccionado = $candidatos->values()->get($opcion - 1);
        }

        if (!$usuarioSeleccionado) {
            $this->error('Error al seleccionar el usuario.');
            return self::FAILURE;
        }

        // Validar RUT del usuario seleccionado
        $rutOriginal = $usuarioSeleccionado->rut;
        if (empty($rutOriginal)) {
            $this->error("El usuario seleccionado (ID: {$usuarioSeleccionado->id_usuario}) no posee un RUT registrado. No es posible generar la contraseña provisoria.");
            return self::FAILURE;
        }

        $nuevaPassword = $this->extraerPasswordDesdeRut($rutOriginal);

        if (empty($nuevaPassword)) {
            $this->error("No se pudo extraer el cuerpo del RUT '{$rutOriginal}' para la contraseña.");
            return self::FAILURE;
        }

        $nombreCompleto = trim("{$usuarioSeleccionado->nombre1} {$usuarioSeleccionado->nombre2} {$usuarioSeleccionado->apellido1} {$usuarioSeleccionado->apellido2}");

        // Mostrar ficha de confirmación
        $this->newLine();
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID Usuario', $usuarioSeleccionado->id_usuario],
                ['Nombre Completo', $nombreCompleto],
                ['RUT Registrado', $usuarioSeleccionado->rut],
                ['Username', $usuarioSeleccionado->username],
                ['Email', $usuarioSeleccionado->email],
                ['Estado', $usuarioSeleccionado->esta_activo ? 'Activo' : 'Inactivo'],
                ['Fecha Cambio Passhash Previa', $usuarioSeleccionado->fecha_cambio_passhash?->toDateTimeString() ?? 'NULL (Requiere cambio)'],
                ['Nueva Contraseña Provisoria', $nuevaPassword],
                ['Nuevo Valor fecha_cambio_passhash', 'NULL (Forzará cambio obligatorio en siguiente login)'],
            ]
        );
        $this->newLine();

        if (!$this->option('force')) {
            $confirmar = $this->confirm("¿Está seguro de forzar el reseteo de contraseña para '{$nombreCompleto}'?", false);
            if (!$confirmar) {
                $this->info('Operación cancelada.');
                return self::SUCCESS;
            }
        }

        // Aplicar reseteo en base de datos
        DB::transaction(function () use ($usuarioSeleccionado, $nuevaPassword) {
            $usuarioSeleccionado->passhash = Hash::make($nuevaPassword);
            $usuarioSeleccionado->fecha_cambio_passhash = null;
            $usuarioSeleccionado->save();
        });

        // Refrescar y comprobar estado
        $usuarioSeleccionado->refresh();

        $this->newLine();
        $this->info('===============================================================');
        $this->info("✅ CONTRASEÑA RESETEADA CON ÉXITO");
        $this->info('===============================================================');
        $this->line("• Usuario: <fg=yellow>{$nombreCompleto}</> (ID: {$usuarioSeleccionado->id_usuario})");
        $this->line("• RUT: <fg=yellow>{$usuarioSeleccionado->rut}</>");
        $this->line("• Nueva Contraseña Provisoria: <fg=green;options=bold>{$nuevaPassword}</>");
        $this->line("• Exigir cambio de contraseña: <fg=cyan>Activado (fecha_cambio_passhash = NULL)</>");
        $this->line("• Motivo registrado: <fg=cyan>" . ($usuarioSeleccionado->motivoCambioPasswordObligatorio() ?? 'N/A') . "</>");
        $this->info('===============================================================');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Busca candidatos según ID, RUT o texto libre (nombres, apellidos, username, email).
     *
     * @return \Illuminate\Support\Collection<int, Usuario>
     */
    public function buscarCandidatos(string $termino)
    {
        $candidatos = collect();

        // 1. Si es numérico puro, buscar por ID exacto
        if (ctype_digit($termino) && (int) $termino > 0) {
            $porId = Usuario::find((int) $termino);

            // Si tiene menos de 7 dígitos y existe por ID, es una búsqueda directa por clave primaria.
            // Esto evita que buscar el ID "1" o "2" coincida parcialmente con el dígito "1" en los
            // RUTs o correos de cientos de usuarios no relacionados.
            if ($porId && strlen($termino) < 7) {
                return collect([$porId]);
            }

            if ($porId) {
                $candidatos->push($porId);
            }
        }

        // 2. Si tiene formato de RUT o dígitos de RUT (7+ dígitos), buscar por RUT
        $rutNormalizado = Rut::normalizar($termino);
        if ($rutNormalizado && Rut::esValido($rutNormalizado)) {
            $porRut = Usuario::where('rut', $rutNormalizado)->get();
            $candidatos = $candidatos->merge($porRut);
        }

        // Búsqueda por cuerpo de RUT limpio (solo si tiene 7 o más dígitos)
        $soloDigitos = Rut::soloDigitos($termino);
        if (strlen($soloDigitos) >= 7) {
            $porRutLimpio = Usuario::whereRaw(
                "regexp_replace(lower(rut), '[^0-9k]', '', 'g') = ?",
                [strtolower($soloDigitos)]
            )->get();
            $candidatos = $candidatos->merge($porRutLimpio);

            // También buscar coincidencias que comiencen con ese cuerpo
            $porRutPrefijo = Usuario::whereRaw(
                "regexp_replace(lower(rut), '[^0-9k]', '', 'g') like ?",
                [strtolower($soloDigitos) . '%']
            )->get();
            $candidatos = $candidatos->merge($porRutPrefijo);
        }

        // 3. Buscar usando scopeBuscar (nombre1, nombre2, apellido1, apellido2, username, email, rut)
        $porTexto = Usuario::buscar($termino)->limit(25)->get();
        $candidatos = $candidatos->merge($porTexto);

        // Unificar por id_usuario y retornar colección indexada
        return $candidatos->unique('id_usuario')->values();
    }

    /**
     * Extrae el cuerpo del RUT (sin puntos, sin guion y sin DV) para usarlo como contraseña provisoria.
     */
    public function extraerPasswordDesdeRut(string $rut): string
    {
        $normalizado = Rut::normalizar($rut);

        if ($normalizado && str_contains($normalizado, '-')) {
            $partes = explode('-', $normalizado);
            return preg_replace('/\D/', '', $partes[0]);
        }

        // Si no tiene guion pero es texto plano (ej: 123456789), quitar el último carácter (DV)
        $limpio = Rut::soloDigitos($rut);
        if (strlen($limpio) >= 2) {
            return substr($limpio, 0, -1);
        }

        return $limpio;
    }
}
