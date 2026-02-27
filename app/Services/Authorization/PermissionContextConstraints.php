<?php

namespace App\Services\Authorization;

/**
 * Restricciones de tipo de contexto válido por permiso.
 *
 * Centraliza la validación de permission-context compatibility para:
 * - Prevenir asignaciones malformadas en tiempo de ejecución
 * - Proporcionar mensajes de error descriptivos
 *
 * Fuente de verdad: config/permission-context-metadata.php
 * (Generada automáticamente por scripts/permissions_config.php)
 *
 * //FIX: Permisos con acciones concretas en contexto GLOBAL
 * 'cursos:ver' no se puede asignar a un contexto global.
 * actualmente se asigna a todos los contextos, que de crear mas contextos, no
 * se actualiza.
 * 
 * @see config\permission-context-metadata.php
 * 
 * @see scripts\permissions_config.php
 */
final class PermissionContextConstraints
{
    /**
     * Mapa de normalización: categorías de DB → tipos de config.
     *
     * La tabla tipo_contexto usa 'system' como categoría para el contexto global,
     * pero config/permission-context-metadata.php usa 'GLOBAL'. Este mapa resuelve
     * esa discrepancia para que la validación funcione con valores directos de la BD.
     */
    private const CONTEXT_TYPE_ALIASES = [
        'SYSTEM' => 'GLOBAL',
    ];

    /**
     * //FIX: ÚNICAMENTE PRESENTE PORQUE HAY DIFERENCIA ENTRE BD Y CÓDIGO 
     * 
     * Normaliza un tipo de contexto proveniente de la BD al formato que usa el config.
     *
     * Convierte a mayúsculas y aplica aliases conocidos (e.g. 'system' → 'GLOBAL').
     *
     * @param string $contextType Valor crudo (puede venir de tipo_contexto.categoria)
     * @return string Tipo normalizado compatible con config/permission-context-metadata
     */
    public static function normalizeContextType(string $contextType): string
    {
        $upper = strtoupper($contextType);
        return self::CONTEXT_TYPE_ALIASES[$upper] ?? $upper;
    }

    /**
     * Retorna los tipos de contexto válidos para un slug de permiso dado.
     * Lee config/permission-context-metadata.php generado automáticamente.
     *
     * Ejemplos:
     *   'cursos:ver'                      → ['GLOBAL', 'CURSO']
     *   'cursos:crear'                    → ['GLOBAL', 'CARRERA']  (_parent_action)
     *   'carreras/planes:crear'           → ['GLOBAL', 'CARRERA']
     *   'cursos/actividades/grupos:crear' → ['GLOBAL', 'CURSO']
     *   'usuarios:ver'                    → ['GLOBAL'] (system context)
     *   '*'                               → ['GLOBAL'] (system context)
     *
     * @param  string   $slug
     * @return string[]
     */
    public static function validContextTypesFor(string $slug): array
    {
        /** @var array<string, string[]> $metadata */
        $metadata = config('permission-context-metadata', []);
        return $metadata[$slug] ?? ['GLOBAL'];
    }

    /**
     * Verifica si una pareja permiso↔tipo_de_contexto es válida para asignación.
     */
    public static function isValidAssignment(string $slug, string $contextType): bool
    {
        return in_array(self::normalizeContextType($contextType), self::validContextTypesFor($slug), true);
    }

    /**
     * Retorna un mensaje de error legible cuando la pareja permiso↔contexto es inválida.
     */
    public static function invalidAssignmentMessage(string $slug, string $contextType): string
    {
        $valid = implode(', ', self::validContextTypesFor($slug));
        return "El permiso '{$slug}' no puede asignarse a un contexto de tipo '{$contextType}'. "
            . "Tipos de contexto válidos: {$valid}.";
    }

    /**
     * Valida múltiples contextos a la vez (útil para validaciones en batch).
     *
     * @param string $slug              Slug del permiso
     * @param string[] $contextTypes    Tipos de contexto a validar
     * @return string[]                 Array de tipos inválidos (vacío = todos válidos)
     * 
     * @example
     *   $invalid = PermissionContextConstraints::getInvalidTypes('cursos:ver', ['CARRERA', 'INVALID_TYPE']);
     *   if (!empty($invalid)) {
     *       throw new InvalidArgumentException("Invalid context types: " . implode(', ', $invalid));
     *   }
     */
    public static function getInvalidTypes(string $slug, array $contextTypes): array
    {
        $validTypes = self::validContextTypesFor($slug);
        return array_filter(
            $contextTypes,
            fn($type) => !in_array(self::normalizeContextType($type), $validTypes, true)
        );
    }

    /**
     * Verifica si TODOS los contextos son válidos para un permiso.
     *
     * @param string $slug
     * @param string[] $contextTypes
     * @return bool
     */
    public static function areAllTypesValid(string $slug, array $contextTypes): bool
    {
        return empty(self::getInvalidTypes($slug, $contextTypes));
    }

    /**
     * Retorna información de diagnóstico para debugging.
     *
     * Útil para logging y reportes de errores más detallados.
     *
     * @param string $slug
     * @param string $contextType
     * @return array{
     *   slug: string,
     *   contextType: string,
     *   valid: bool,
     *   validTypes: string[],
     *   message: string
     * }
     */
    public static function diagnoseAssignment(string $slug, string $contextType): array
    {
        $valid = self::isValidAssignment($slug, $contextType);
        $validTypes = self::validContextTypesFor($slug);

        return [
            'slug' => $slug,
            'contextType' => $contextType,
            'valid' => $valid,
            'validTypes' => $validTypes,
            'message' => $valid
                ? "✓ Asignación válida"
                : self::invalidAssignmentMessage($slug, $contextType),
        ];
    }
}