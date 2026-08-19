<?php

namespace App\Services\Authorization;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de los veredictos del motor de permisos.
 *
 * Cada `validate()` costaba ~13 consultas: una de SuperAdmin, una por contexto
 * de la cadena para UPE y otra para URA, más la resolución de ancestros. Como el
 * motor se invoca en cada policy, cada middleware y cada `share()`, es el punto
 * con más efecto de todo el informe.
 *
 * **Invalidación por generación.** El driver configurado es `database`, que no
 * soporta tags, así que no se puede "borrar todo lo del usuario 7". En su lugar
 * cada usuario tiene un contador; la generación forma parte de la clave, de modo
 * que incrementarlo deja inalcanzables de golpe todas sus entradas anteriores,
 * que caducan solas por TTL. Funciona con cualquier driver.
 */
class PermissionCache
{
    /**
     * Vida de cada veredicto.
     *
     * Corta a propósito: la invalidación explícita cubre los cambios de rol y
     * permiso, pero la vista `vw_permisos_usuario` también filtra por fechas de
     * vigencia, y una asignación que caduca por `fecha_fin_planificada` no
     * dispara ninguna escritura. El TTL acota esa ventana.
     */
    private const TTL_SEGUNDOS = 300;

    private const PREFIJO = 'perm';

    /** Veredicto UPE: true=GRANT, false=DENY, null=sin coincidencia. */
    public function recordarUpe(int $userId, string $permiso, int $contextId, Closure $callback): ?bool
    {
        // `Cache::remember` trata null como fallo de caché y repetiría la consulta
        // en cada llamada, así que el "no encontrado" se codifica como cadena.
        $valor = $this->recordar($userId, 'upe', $permiso, $contextId, function () use ($callback) {
            return match ($callback()) {
                true    => 'grant',
                false   => 'deny',
                default => 'null',
            };
        });

        return match ($valor) {
            'grant' => true,
            'deny'  => false,
            default => null,
        };
    }

    /** ¿Alguno de los roles del usuario concede el permiso en ese contexto? */
    public function recordarUra(int $userId, string $permiso, int $contextId, Closure $callback): bool
    {
        return (bool) $this->recordar(
            $userId,
            'ura',
            $permiso,
            $contextId,
            fn() => $callback() ? 1 : 0
        );
    }

    /** ¿El usuario tiene el wildcard global? */
    public function recordarSuperAdmin(int $userId, Closure $callback): bool
    {
        return (bool) $this->recordar($userId, 'super', '*', 0, fn() => $callback() ? 1 : 0);
    }

    /** Contextos donde el usuario tiene un permiso (para listados con scope). */
    public function recordarContextos(int $userId, string $permiso, Closure $callback): array
    {
        return (array) $this->recordar($userId, 'contexts', $permiso, 0, $callback);
    }

    /**
     * Invalida todo lo cacheado del usuario.
     *
     * Se llama desde los eventos `saved`/`deleted` de UsuarioRolAsignacion y
     * UsuarioPermisoEspecial, y explícitamente donde se escribe en masa (que no
     * dispara eventos de modelo).
     */
    public function olvidarUsuario(int $userId): void
    {
        $clave = $this->claveGeneracion($userId);

        // `increment` no crea la clave si no existe en todos los drivers.
        if (Cache::get($clave) === null) {
            Cache::forever($clave, 1);
            return;
        }

        Cache::increment($clave);
    }

    private function recordar(int $userId, string $tipo, string $permiso, int $contextId, Closure $callback): mixed
    {
        return Cache::remember(
            $this->clave($userId, $tipo, $permiso, $contextId),
            self::TTL_SEGUNDOS,
            $callback
        );
    }

    private function clave(int $userId, string $tipo, string $permiso, int $contextId): string
    {
        $generacion = $this->generacion($userId);

        return self::PREFIJO . ":g{$generacion}:{$userId}:{$tipo}:{$permiso}:{$contextId}";
    }

    private function generacion(int $userId): int
    {
        $clave = $this->claveGeneracion($userId);
        $generacion = Cache::get($clave);

        if ($generacion === null) {
            Cache::forever($clave, 1);

            return 1;
        }

        return (int) $generacion;
    }

    private function claveGeneracion(int $userId): string
    {
        return self::PREFIJO . ":gen:{$userId}";
    }
}
