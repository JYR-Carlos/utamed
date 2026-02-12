<?php

namespace App\Services\Authorization;

/**
 * Utilidad para matching de slugs de permisos con soporte de wildcards.
 * 
 * Soporta:
 * - Match exacto: 'facultad:editar' == 'facultad:editar'
 * - Wildcard recurso: 'facultad:*' match 'facultad:editar', 'facultad:ver', etc.
 * - Wildcard global: '*' match cualquier slug
 * 
 * @package App\Services\Authorization
 */
class WildcardMatcher
{
    /**
     * Separador de slugs (recurso:accion)
     */
    public const SEPARATOR = ':';

    /**
     * Wildcard global
     */
    public const GLOBAL_WILDCARD = '*';

    /**
     * Verificar si un slug solicitado coincide con un patrón de permiso.
     * 
     * @param string $requestedSlug Slug solicitado (ej: 'facultad:editar')
     * @param string $patternSlug Patrón del permiso (puede tener wildcards)
     * @return bool
     */
    public static function matches(string $requestedSlug, string $patternSlug): bool
    {
        // Match exacto
        if ($requestedSlug === $patternSlug) {
            return true;
        }

        // Wildcard global
        if (self::isGlobalWildcard($patternSlug)) {
            return true;
        }

        // Wildcard de recurso (ej: 'facultad:*')
        if (self::isResourceWildcard($patternSlug)) {
            $requestedResource = self::extractResource($requestedSlug);
            $patternResource = self::extractResource($patternSlug);
            
            return $requestedResource === $patternResource;
        }

        return false;
    }

    /**
     * Extraer el recurso del slug (parte antes de ':').
     * 
     * 'facultad:editar' → 'facultad'
     * 'facultad:*' → 'facultad'
     * '*' → '*'
     * 
     * @param string $slug
     * @return string
     */
    public static function extractResource(string $slug): string
    {
        if (self::isGlobalWildcard($slug)) {
            return self::GLOBAL_WILDCARD;
        }

        $parts = explode(self::SEPARATOR, $slug, 2);
        return $parts[0] ?? '';
    }

    /**
     * Extraer la acción del slug (parte después de ':').
     * 
     * 'facultad:editar' → 'editar'
     * 'facultad:*' → '*'
     * '*' → null
     * 
     * @param string $slug
     * @return string|null
     */
    public static function extractAction(string $slug): ?string
    {
        if ($slug === self::GLOBAL_WILDCARD) {
            return null;
        }

        $parts = explode(self::SEPARATOR, $slug, 2);
        return $parts[1] ?? null;
    }

    /**
     * Verificar si es wildcard global ('*').
     * 
     * @param string $string
     * @return bool
     */
    public static function isGlobalWildcard(string $string): bool
    {
        return $string === self::GLOBAL_WILDCARD;
    }

    /**
     * Verificar si un slug es wildcard de recurso ('recurso:*').
     * 
     * @param string $slug
     * @return bool
     */
    public static function isResourceWildcard(string $slug): bool
    {
        if (self::isGlobalWildcard($slug)) {
            return false;
        }

        $action = self::extractAction($slug);
        return self::isGlobalWildcard($action);
    }

    /**
     * Generar el slug wildcard de recurso para un slug dado.
     * 
     * 'facultad:editar' → 'facultad:*'
     * 'facultad:ver' → 'facultad:*'
     * '*' → '*'
     * 
     * @param string $slug
     * @return string
     */
    public static function toResourceWildcard(string $slug): string
    {
        if (self::isGlobalWildcard($slug)) {
            return self::GLOBAL_WILDCARD;
        }

        $resource = self::extractResource($slug);
        return $resource . self::SEPARATOR . self::GLOBAL_WILDCARD;
    }

    /**
     * Obtener la prioridad de un slug para ordenamiento.
     * 
     * Menor número = mayor prioridad
     * 1: Match exacto (ej: 'facultad:editar')
     * 2: Wildcard de recurso (ej: 'facultad:*')
     * 3: Wildcard global (ej: '*')
     * 
     * @param string $requestedSlug Slug solicitado
     * @param string $patternSlug Patrón del permiso
     * @return int
     */
    public static function getPriority(string $requestedSlug, string $patternSlug): int
    {
        // Match exacto
        if ($requestedSlug === $patternSlug) {
            return 1;
        }

        // Wildcard global
        elseif (self::isGlobalWildcard($patternSlug)) {
            return 3;
        }

        // Wildcard de recurso
        elseif (self::isResourceWildcard($patternSlug)) {
            return 2;
        }

        // No coincide
        return 999;
    }
}
