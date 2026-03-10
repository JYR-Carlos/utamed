<?php

namespace App\Services\Authorization;

use App\Enums\ContextType;
use App\Support\Permissions;

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
 * Todos los métodos reciben el enum Permissions como slug y el enum ContextType
 * como tipo de contexto, forzando type-safety y evitando magic strings.
 *
 * @see config\permission-context-metadata.php
 * @see scripts\permissions_config.php
 * @see \App\Support\Permissions
 * @see \App\Enums\ContextType
 */
final class PermissionContextConstraints
{
    /**
     * Retorna los tipos de contexto válidos para un permiso dado.
     * Lee config/permission-context-metadata.php generado automáticamente.
     *
     * Ejemplos:
     *   Permissions::CURSOS_VER                      → ['global', 'curso', 'carrera', 'departamento', 'facultad']
     * 
     *   Permissions::CURSOS_CREAR                    → ['global', 'carrera']  (_parent_action)
     * 
     *   Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR → ['global', 'curso', ...]
     * 
     *   Permissions::USUARIOS_VER                    → ['global'] (global context)
     * 
     *   Permissions::GLOBAL_WILDCARD                 → ['global'] (global context)
     *
     * @param  Permissions $permission
     * @return ContextType[]
     */
    public static function validContextTypesFor(Permissions $permission): array
    {
        /** @var array<string, string[]> $metadata */
        $metadata = config('permission-context-metadata', []);

        $raw = $metadata[$permission->value] ?? ['global'];

        return array_values(array_filter(
            array_map(
                fn(string $v) => ContextType::tryFrom($v),
                $raw
            )
        ));
    }

    /**
     * Verifica si una pareja permiso↔tipo_de_contexto es válida para asignación.
     *
     * @param Permissions $permission
     * @param ContextType $contextType
     * @return bool
     */
    public static function isValidAssignment(Permissions $permission, ContextType $contextType): bool
    {
        return in_array($contextType, self::validContextTypesFor($permission), true);
    }

    /**
     * Verifica si AL MENOS UNO de los tipos de contexto es válido para asignación.
     *
     * Útil para modelos con múltiples tipos de contexto (ej: InscripcionCurso
     * tiene tipos ['curso', 'carrera']). Basta con que uno sea válido.
     *
     * @param Permissions   $permission
     * @param ContextType[] $contextTypes
     * @return bool
     */
    public static function isAnyTypeValid(Permissions $permission, array $contextTypes): bool
    {
        foreach ($contextTypes as $type) {
            if (self::isValidAssignment($permission, $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retorna un mensaje de error legible cuando la pareja permiso↔contexto es inválida.
     *
     * @param Permissions $permission
     * @param ContextType $contextType
     * @return string
     */
    public static function invalidAssignmentMessage(Permissions $permission, ContextType $contextType): string
    {
        $valid = implode(
            ', ',
            array_map(
                fn(ContextType $t) => $t->value,
                self::validContextTypesFor($permission)
            )
        );
        return "El permiso '{$permission->value}' no puede asignarse a un contexto de tipo '{$contextType->value}'. "
            . "Tipos de contexto válidos: {$valid}.";
    }

    /**
     * Valida múltiples contextos a la vez (útil para validaciones en batch).
     *
     * @param Permissions   $permission    Permiso a validar
     * @param ContextType[] $contextTypes  Tipos de contexto a validar
     * @return ContextType[]               Array de tipos inválidos (vacío = todos válidos)
     *
     * @example
     *   $invalid = PermissionContextConstraints::getInvalidTypes(
     *       Permissions::CURSOS_VER,
     *       [ContextType::CARRERA, ContextType::CURSO]
     *   );
     *   if (!empty($invalid)) {
     *       // handle invalid types
     *   }
     */
    public static function getInvalidTypes(Permissions $permission, array $contextTypes): array
    {
        $valid = self::validContextTypesFor($permission);

        return array_values(array_filter(
            $contextTypes,
            fn(ContextType $type) => !in_array($type, $valid, true)
        ));
    }

    /**
     * Verifica si TODOS los contextos son válidos para un permiso.
     *
     * @param Permissions   $permission
     * @param ContextType[] $contextTypes
     * @return bool
     */
    public static function areAllTypesValid(Permissions $permission, array $contextTypes): bool
    {
        return empty(self::getInvalidTypes($permission, $contextTypes));
    }

    /**
     * Retorna información de diagnóstico para debugging.
     *
     * Útil para logging y reportes de errores más detallados.
     *
     * @param Permissions $permission
     * @param ContextType $contextType
     * @return array{
     *   slug: string,
     *   contextType: string,
     *   valid: bool,
     *   validTypes: string[],
     *   message: string
     * }
     */
    public static function diagnoseAssignment(Permissions $permission, ContextType $contextType): array
    {
        $valid = self::isValidAssignment($permission, $contextType);
        $validTypes = self::validContextTypesFor($permission);

        return [
            'slug' => $permission->value,
            'contextType' => $contextType->value,
            'valid' => $valid,
            'validTypes' => array_map(
                fn(ContextType $t) => $t->value,
                $validTypes
            ),
            'message' => $valid
                ? "✓ Asignación válida"
                : self::invalidAssignmentMessage($permission, $contextType),
        ];
    }
}