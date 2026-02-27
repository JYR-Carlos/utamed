<?php

namespace App\Support;

/**
 * Restricciones de tipo de contexto válido por permiso.
 *
 * Generada automáticamente por scripts/generate_models.php — NO EDITAR MANUALMENTE.
 * Fuente de verdad: scripts/permissions_config.php → config/permission-context-metadata.php.
 *
 * Consulta el mapa plano slug→contextos generado en config/permission-context-metadata.php,
 * que incluye la resolución completa de _actions (contexto propio), _parent_actions
 * (contexto padre via _valid_parent_context) y wildcards (root:*).
 *
 * //FIX: Permisos con acciones concretas en contexto GLOBAL
 * 'cursos:ver' no se puede asignar a un contexto global.
 * actualmente se asigna a todos los contextos, que de crear mas contextos, no
 * se actualiza.
 */
final class PermissionContextConstraints
{
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
        return in_array(strtoupper($contextType), self::validContextTypesFor($slug), true);
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
}