<?php

namespace App\Support;

/**
 * Grafo declarativo de dependencias de permisos delegables de curso.
 *
 * Estructura Lean: [ slug_dependiente => [ slugs_prerrequisito_inmediato ] ]
 *
 * Reglas de coherencia:
 * - Al otorgar: Se deben otorgar automáticamente todos los prerrequisitos (upstream).
 * - Al revocar: Se deben revocar automáticamente todos los dependientes que no pueden subsistir sin él (downstream).
 */
final class CoursePermissionGraph
{
    private const DEPENDENCIES = [
        // ── Actividades ───────────────────────────────────────────────────────
        Permissions::ACTIVIDADES_CREAR->value                => [Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_CREAR_PLANTILLA->value      => [Permissions::ACTIVIDADES_CREAR->value, Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_EDITAR->value               => [Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_ELIMINAR->value             => [Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_EVALUAR->value              => [Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_DAR_FEEDBACK->value         => [Permissions::ACTIVIDADES_EVALUAR->value, Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_DESCARGAR_ENTREGAS->value   => [Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_SUBIR_ENTREGAS->value       => [Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_ENVIAR_RECORDATORIOS->value => [Permissions::ACTIVIDADES_VER->value],

        // ── Grupos de Actividad ───────────────────────────────────────────────
        Permissions::ACTIVIDADES_GRUPOS_VER->value           => [Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_GRUPOS_CREAR->value         => [Permissions::ACTIVIDADES_GRUPOS_VER->value, Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_GRUPOS_EDITAR->value        => [Permissions::ACTIVIDADES_GRUPOS_VER->value, Permissions::ACTIVIDADES_VER->value],
        Permissions::ACTIVIDADES_GRUPOS_ELIMINAR->value      => [Permissions::ACTIVIDADES_GRUPOS_VER->value, Permissions::ACTIVIDADES_VER->value],

        // ── Unidades ──────────────────────────────────────────────────────────
        Permissions::CURSOS_UNIDADES_CREAR->value            => [Permissions::CURSOS_UNIDADES_VER->value],
        Permissions::CURSOS_UNIDADES_CREAR_PLANTILLA->value  => [Permissions::CURSOS_UNIDADES_CREAR->value, Permissions::CURSOS_UNIDADES_VER->value],
        Permissions::CURSOS_UNIDADES_EDITAR->value           => [Permissions::CURSOS_UNIDADES_VER->value],
        Permissions::CURSOS_UNIDADES_ELIMINAR->value         => [Permissions::CURSOS_UNIDADES_VER->value],

        // ── Programas (Syllabus) ──────────────────────────────────────────────
        Permissions::CURSOS_PROGRAMAS_AGREGAR->value         => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_ELIMINAR->value        => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_1->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_2->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_3->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_4->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_5->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_6->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_7->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_8->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],
        Permissions::CURSOS_PROGRAMAS_MODIFICAR_MODULO_9->value => [Permissions::CURSOS_PROGRAMAS_VER_TODOS->value],

        // ── Componentes ───────────────────────────────────────────────────────
        Permissions::COMPONENTES_EDITAR->value               => [Permissions::COMPONENTES_VER->value],

        // ── Curso ─────────────────────────────────────────────────────────────
        Permissions::CURSOS_EDITAR->value                    => [Permissions::CURSOS_VER->value],
        Permissions::CURSOS_ELIMINAR->value                  => [Permissions::CURSOS_EDITAR->value, Permissions::CURSOS_VER->value],
    ];

    /**
     * Resuelve recursivamente todos los prerrequisitos que deben otorgarse junto a $slugs.
     * Retorna un array único que incluye los slugs originales y todos sus ancestros necesarios.
     *
     * @param string|array<string> $slugs
     * @return array<string>
     */
    public static function resolveRequiredToGrant(string|array $slugs): array
    {
        $input = is_array($slugs) ? $slugs : [$slugs];
        $resolved = [];
        $queue = $input;

        while (!empty($queue)) {
            $current = array_shift($queue);
            if (!in_array($current, $resolved, true)) {
                $resolved[] = $current;
                foreach (self::DEPENDENCIES[$current] ?? [] as $dep) {
                    if (!in_array($dep, $resolved, true)) {
                        $queue[] = $dep;
                    }
                }
            }
        }

        return array_values($resolved);
    }

    /**
     * Resuelve recursivamente todos los dependientes que deben revocarse en cascada si se apagan $slugs.
     * Retorna un array único que incluye los slugs originales y todos los que dependen de ellos.
     *
     * @param string|array<string> $slugs
     * @return array<string>
     */
    public static function resolveCascadeToRevoke(string|array $slugs): array
    {
        $input = is_array($slugs) ? $slugs : [$slugs];
        $toRevoke = $input;

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (self::DEPENDENCIES as $candidate => $prerequisites) {
                if (in_array($candidate, $toRevoke, true)) {
                    continue;
                }
                foreach ($prerequisites as $prereq) {
                    if (in_array($prereq, $toRevoke, true)) {
                        $toRevoke[] = $candidate;
                        $changed = true;
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($toRevoke));
    }

    /**
     * Retorna el mapa completo de dependencias para el frontend.
     *
     * @return array<string, array<string>>
     */
    public static function dependencyMap(): array
    {
        return self::DEPENDENCIES;
    }
}
