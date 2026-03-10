<?php

namespace App\Enums;

/**
 * Tipos de contexto válidos para asignación de permisos y roles.
 *
 * AUTOGENERADO desde scripts/permissions_config.php — NO EDITAR MANUALMENTE.
 * Para agregar tipos, editar scripts/permissions_config.php y regenerar con:
 *   php scripts/generate_models.php
 *
 * Los valores de backing string corresponden EXACTAMENTE a la columna
 * `categoria` de la tabla `usuario.tipo_contexto` en la base de datos.
 *
 * Úsala en PermissionContextConstraints para eliminar magic strings y
 * garantizar en tiempo de compilación que sólo se pasan tipos válidos:
 *
 * @example
 *   // Beneficios type-safe, IDE-friendly y con autocompletado:
 *   PermissionContextConstraints::isValidAssignment($perm, ContextType::CARRERA);
 *
 *   // Para convertir desde DB:
 *   $contextType = ContextType::from($context->tipoContexto->categoria);
 */
enum ContextType: string
{
    // Contexto global — aplica a nivel sistema sin restricción de entidad
    case GLOBAL = 'global';

    // Contexto de tipo 'actividad'
    case ACTIVIDAD = 'actividad';

    // Contexto de carrera — restricción a una carrera específica
    case CARRERA = 'carrera';

    // Contexto de curso — restricción a un curso específico
    case CURSO = 'curso';

    // Contexto de departamento — restricción a un departamento específico
    case DEPARTAMENTO = 'departamento';

    // Contexto de facultad — restricción a una facultad específica
    case FACULTAD = 'facultad';
}