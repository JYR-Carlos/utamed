<?php

namespace App\Services\Authorization;

/**
 * Utilidad para matching de slugs de permisos con soporte de wildcards y recursos anidados.
 *
 * Formato de slug: 'recurso:accion'  o  'recurso/subrecurso/.../subN:accion'
 *
 * Soporta:
 * - Match exacto:                   'facultad:editar'            == 'facultad:editar'
 * - Match exacto anidado:           'cursos/inscripciones:ver'   == 'cursos/inscripciones:ver'
 * - Wildcard mismo recurso:         'facultad:*'                 matchea 'facultad:editar', 'facultad:ver', ...
 * - Wildcard recurso anidado:       'cursos/inscripciones:*'     matchea 'cursos/inscripciones:ver', ...
 * - Wildcard recurso ancestro:      'cursos:*'                   matchea 'cursos/inscripciones:ver',
 *                                                                'cursos/actividades/grupos:crear', ...
 * - Wildcard global:                '*'                          matchea cualquier slug
 *
 * Regla: los wildcards solo aplican en la parte de acción (':*'), no en la ruta de recurso.
 *
 * @package App\Services\Authorization
 */
class WildcardMatcher
{
    /**
     * Separador de acción (recurso:accion)
     */
    public const SEPARATOR = ':';

    /**
     * Separador de ruta de recurso (recurso/subrecurso)
     */
    public const RESOURCE_SEPARATOR = '/';

    /**
     * Wildcard global
     */
    public const GLOBAL_WILDCARD = '*';

    /**
     * Verificar si un slug solicitado coincide con un patrón de permiso.
     *
     * @param string $requestedSlug Slug solicitado (ej: 'cursos/inscripciones:ver')
     * @param string $patternSlug   Patrón del permiso, puede tener wildcard de acción
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

        // Wildcard de recurso (ej: 'facultad:*', 'cursos/inscripciones:*', 'cursos:*')
        if (self::isResourceWildcard($patternSlug)) {
            $requestedResource = self::extractResource($requestedSlug);
            $patternResource = self::extractResource($patternSlug);

            // Match exacto del recurso (mismo nivel)
            if ($requestedResource === $patternResource) {
                return true;
            }

            // Match ancestro: el patrón cubre todos los descendientes de ese recurso.
            // ej: 'cursos:*' matchea 'cursos/inscripciones:ver', 'cursos/actividades/grupos:crear'
            return self::isDescendantResource($requestedResource, $patternResource);
        }

        return false;
    }

    /**
     * Verificar si un recurso es descendiente (hijo, nieto, etc.) de otro recurso.
     *
     * 'cursos/inscripciones'      es descendiente de 'cursos'              ✓
     * 'cursos/actividades/grupos' es descendiente de 'cursos'              ✓
     * 'cursos/actividades/grupos' es descendiente de 'cursos/actividades'  ✓
     * 'cursos'                    NO es descendiente de 'cursos'           (mismo nivel)
     * 'cursos'                    NO es descendiente de 'cursos/sub'       (dirección inversa)
     *
     * @param string $requestedResource Ruta del recurso solicitado (ej: 'cursos/inscripciones')
     * @param string $ancestorResource  Ruta del posible ancestro   (ej: 'cursos')
     * @return bool
     */
    public static function isDescendantResource(string $requestedResource, string $ancestorResource): bool
    {
        if ($requestedResource === $ancestorResource) {
            return false;
        }

        return str_starts_with($requestedResource, $ancestorResource . self::RESOURCE_SEPARATOR);
    }

    /**
     * Extraer el recurso del slug (parte antes de ':').
     * Soporta rutas anidadas.
     *
     * 'facultad:editar'            → 'facultad'
     * 'cursos/inscripciones:ver'   → 'cursos/inscripciones'
     * 'facultad:*'                 → 'facultad'
     * '*'                          → '*'
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
     * Extraer los segmentos de la ruta del recurso.
     *
     * 'cursos/inscripciones:ver'       → ['cursos', 'inscripciones']
     * 'cursos/actividades/grupos:ver'  → ['cursos', 'actividades', 'grupos']
     * 'facultad:ver'                   → ['facultad']
     * '*'                              → ['*']
     *
     * @param string $slug
     * @return string[]
     */
    public static function extractResourceSegments(string $slug): array
    {
        $resource = self::extractResource($slug);
        return explode(self::RESOURCE_SEPARATOR, $resource);
    }

    /**
     * Extraer la acción del slug (parte después de ':').
     *
     * 'facultad:editar'           → 'editar'
     * 'cursos/inscripciones:ver'  → 'ver'
     * 'facultad:*'                → '*'
     * '*'                         → null
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
     * Verificar si un slug es wildcard de recurso ('recurso:*' o 'recurso/sub:*').
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
     * Preserva la ruta de recurso completa (incluyendo anidamiento).
     *
     * 'facultad:editar'           → 'facultad:*'
     * 'cursos/inscripciones:ver'  → 'cursos/inscripciones:*'
     * '*'                         → '*'
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
     * Generar todas las versiones de patrones para un slug solicitado.
     *
     * Retorna un array de patrones ordenados por especificidad (mayor a menor).
     * Utilizado para buscar en la DB si el usuario tiene permiso en cualquier nivel ancestro.
     *
     * Ejemplo: 'cursos/inscripciones/alumno:ver' genera:
     * [
     *   0 => 'cursos/inscripciones/alumno:ver'      (exacto, prioridad 1)
     *   1 => 'cursos/inscripciones/alumno:*'        (mismo recurso wildcard, prioridad 2)
     *   2 => 'cursos/inscripciones:*'               (ancestro 1 wildcard, prioridad 3)
     *   3 => 'cursos:*'                             (ancestro 2 wildcard, prioridad 3)
     *   4 => '*'                                    (global wildcard, prioridad 4)
     * ]
     *
     * @param string $slug Slug del permiso (ej: 'cursos/inscripciones/alumno:ver')
     * @return string[]
     */
    public static function generatePermissionPatterns(string $slug): array
    {
        // Extraer recurso y acción
        $resource = self::extractResource($slug);
        $action = self::extractAction($slug);

        $patterns = [];

        // 1. Match exacto
        $patterns[] = $slug;

        // 2. Wildcard del mismo recurso (recurso:*)
        if ($action !== null) {
            $patterns[] = $resource . self::SEPARATOR . self::GLOBAL_WILDCARD;
        }

        // 3. Wildcards ancestros (recurso_padre:*, recurso_abuelo:*, etc.)
        $segments = explode(self::RESOURCE_SEPARATOR, $resource);

        // Solo generar patrones ancestros si hay más de 1 segmento
        if (count($segments) > 1) {
            // Recorrer de atrás hacia adelante para construir ancestros
            for ($i = count($segments) - 1; $i > 0; $i--) {
                $ancestorPath = implode(self::RESOURCE_SEPARATOR, array_slice($segments, 0, $i));
                $patterns[] = $ancestorPath . self::SEPARATOR . self::GLOBAL_WILDCARD;
            }
        }

        // 4. Wildcard global
        $patterns[] = self::GLOBAL_WILDCARD;

        return array_unique($patterns);
    }

    /**
     * Obtener la prioridad de un slug para ordenamiento.
     *
     * Menor número = mayor prioridad:
     * 1: Match exacto           (ej: 'cursos/inscripciones:ver')
     * 2: Wildcard mismo recurso (ej: 'cursos/inscripciones:*')
     * 3: Wildcard recurso ancestro (ej: 'cursos:*' para 'cursos/inscripciones:ver')
     * 4: Wildcard global        ('*')
     * 999: Sin coincidencia
     *
     * @param string $requestedSlug Slug solicitado
     * @param string $patternSlug   Patrón del permiso
     * @return int
     */
    public static function getPriority(string $requestedSlug, string $patternSlug): int
    {
        // Match exacto
        if ($requestedSlug === $patternSlug) {
            return 1;
        }

        // Wildcard global
        if (self::isGlobalWildcard($patternSlug)) {
            return 4;
        }

        // Wildcard de recurso
        if (self::isResourceWildcard($patternSlug)) {
            $requestedResource = self::extractResource($requestedSlug);
            $patternResource = self::extractResource($patternSlug);

            // Mismo nivel de recurso
            if ($requestedResource === $patternResource) {
                return 2;
            }

            // Recurso ancestro
            if (self::isDescendantResource($requestedResource, $patternResource)) {
                return 3;
            }
        }

        // No coincide
        return 999;
    }
}
