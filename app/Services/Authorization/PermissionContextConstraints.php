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
     * Carga metadata de contextos válidos para cada permiso desde config.
     *
     * @return array<string, string[]> Asociativo: permiso_slug => [context_types]
     * @throws \RuntimeException Si el archivo de configuración no existe
     */
    private static function loadPermissionMetadata(): array
    {
        $configPath = config_path('permission-context-metadata.php');

        if (!file_exists($configPath)) {
            throw new \RuntimeException(
                "Permission metadata file not found: {$configPath}\n" .
                "Run: php scripts/generate_permissions_sql.php"
            );
        }

        return require $configPath;
    }

    /**
     * Obtiene el tipo de contexto (categoria) dado su ID.
     *
     * @param int $contextId ID del contexto
     * @return ContextType Categoria del tipo de contexto (ej: 'global', 'carrera', etc.)
     * @throws \InvalidArgumentException Si el contexto no existe
     */
    public static function getContextTypeById(int $contextId): ContextType
    {
        $contextType = \Illuminate\Support\Facades\DB::table('usuario.contexto')
            ->join('usuario.tipo_contexto', 'usuario.contexto.id_tipo_contexto', '=', 'usuario.tipo_contexto.id_tipo_contexto')
            ->where('usuario.contexto.id_contexto', $contextId)
            ->value('usuario.tipo_contexto.categoria');

        if ($contextType === null) {
            throw new \InvalidArgumentException(
                "No se encontró el contexto con ID {$contextId}"
            );
        }

        return ContextType::from($contextType);
    }

    /**
     * Retorna los tipos de contexto válidos para un permiso dado.
     * 
     * Lee config/permission-context-metadata.php generado automáticamente.
     *
     * @example Permissions::CURSOS_VER                      → ['global', 'curso', 'carrera', 'departamento', 'facultad']
     * @example Permissions::CURSOS_CREAR                    → ['global', 'carrera']  (_parent_action)
     * @example Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR → ['global', 'curso', ...]
     * @example Permissions::USUARIOS_VER                    → ['global'] (global context)
     * @example Permissions::GLOBAL_WILDCARD                 → ['global'] (global context)
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
                ContextType::tryFrom(...),
                $raw
            )
        ));
    }

    /**
     * Verifica si una pareja (permiso <-> tipo_de_contexto) es válida su asignación.
     *
     * @param Permissions $permission
     * @param ContextType $contextType
     * @return bool
     */
    public static function isValidAssignment(Permissions $permission, ContextType $contextType): bool
    {
        return \in_array($contextType, self::validContextTypesFor($permission), true);
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
            fn(ContextType $type) => !\in_array($type, $valid, true)
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

    /**
     * Calcula la intersección de tipos de contexto válidos para un conjunto de permisos.
     *
     * Un contexto es válido para el conjunto si es válido para TODOS los permisos.
     *
     * @param Permissions[] $permissions Array de permisos del enum
     *
     * @return ContextType[] Contextos válidos como enums
     * @throws \InvalidArgumentException Si el array está vacío
     *
     * @example
     *   $compatible = PermissionContextConstraints::getCompatibleContexts([
     *       Permissions::CURSOS_CREAR, // ['global', 'carrera', 'departamento', 'facultad']
     *       Permissions::CURSOS_VER, // ['global', 'curso', 'carrera', 'departamento', 'facultad']
     *   ]);
     *   // Devuelve: [ContextType::GLOBAL, ContextType::CARRERA, ContextType::DEPARTAMENTO, ContextType::FACULTAD]
     */
    public static function getCompatibleContexts(array $permissions): array
    {
        if (empty($permissions)) {
            throw new \InvalidArgumentException('El array de permisos está vacío');
        }

        // Obtener contextos válidos (como strings) para cada permiso
        $metadata = self::loadPermissionMetadata();
        $allContexts = array_map(
            fn(Permissions $perm) => $metadata[$perm->value] ?? ['global'],
            $permissions
        );

        // Calcular intersección: retener solo contextos válidos en TODOS los permisos
        $compatible = $allContexts[0];
        foreach (\array_slice($allContexts, 1) as $contexts) {
            $compatible = array_intersect($compatible, $contexts);
        }

        // Convertir strings a ContextType enums y retornar
        try {
            // return array_values(array_map(ContextType::from(...), $compatible));
            return collect($compatible)
                ->map(ContextType::from(...))
                ->values()
                ->all();

        } catch (\ValueError $e) {
            \Log::error(
                "Error al convertir contextos compatibles a enums: " . $e->getMessage(),
                ['compatible_contexts' => $compatible]
            );
            throw new \Exception("Error al obtener contextos compatibles: " . $e->getMessage());
        }
    }

    /**
     * Verifica si un rol puede ser asignado a un tipo de contexto específico.
     *
     * @param Permissions[] $rolePermissions Array de permisos del enum
     * @param ContextType $targetContext Tipo de contexto destino
     *
     * @return bool true si todos los permisos del rol son válidos en ese contexto
     * @throws \InvalidArgumentException Si el array está vacío
     */
    public static function isCompatibleWithContext(
        array $rolePermissions,
        ContextType $targetContext
    ): bool {
        $compatibleContexts = self::getCompatibleContexts($rolePermissions);
        return \in_array($targetContext, $compatibleContexts, true);
    }

    /**
     * Obtiene una descripción legible de dónde puede asignarse un rol.
     *
     * @param Permissions[] $rolePermissions Array de permisos del enum
     * @return string Descripción formateada
     *
     * @throws \InvalidArgumentException Si el array está vacío
     */
    public static function getCompatibilityDescription(array $rolePermissions): string
    {
        $contexts = self::getCompatibleContexts($rolePermissions);
        if (empty($contexts)) {
            return '❌ Este rol no puede asignarse a ningún contexto (permisos incompatibles)';
        }

        $formatted = implode(', ', array_map(
            fn(ContextType $ctx) => "'{$ctx->value}'",
            $contexts
        ));

        return "✓ Asignación válida en: {$formatted}";
    }
}