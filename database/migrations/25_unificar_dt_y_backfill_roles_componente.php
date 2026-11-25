<?php

use App\Services\Authorization\RolesPermissionsConfigSyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sincronizar tipos de contexto, permisos, roles base y flag `puede_delegar_permiso`
        //    directamente desde scripts/permissions_config.php y scripts/roles_config.php
        //    (sin hardcodear slugs/matrices en la migración y validando contextos).
        $syncService = app(RolesPermissionsConfigSyncService::class);
        $syncService->syncAll();

        // 2. Migrar legado 'Docente Titular Restringido' -> 'Docente Titular' y eliminar el rol legado
        $syncService->mergeAndDeleteLegacyRole('Docente Titular Restringido', 'Docente Titular');

        $dcId = (int) DB::table('usuario.rol')
            ->where('nombre', 'Docente Componente')
            ->whereNull('fecha_eliminacion')
            ->value('id_rol');

        $colId = (int) DB::table('usuario.rol')
            ->where('nombre', 'Docente Componente Colegiado')
            ->whereNull('fecha_eliminacion')
            ->value('id_rol');

        // 3. Backfill ortogonal de roles de componente desde curso.docente_componente
        //    para TODOS los docentes participantes (incluido el Docente Titular del curso).

        // 3a. Si un docente es titular de componente y tenía Colegiado activo allí, desactivarlo antes de promover
        DB::statement("
            UPDATE usuario.usuario_rol_asignacion ura
            SET esta_activo = false,
                fue_eliminado = true,
                fecha_fin_real = COALESCE(ura.fecha_fin_real, NOW())
            FROM usuario.docente d,
                 curso.docente_componente dc,
                 curso.componente comp
            WHERE ura.id_usuario = d.id_usuario
              AND dc.id_docente = d.id_docente
              AND comp.id_componente = dc.id_componente
              AND ura.id_contexto = comp.id_contexto
              AND ura.id_rol = ?
              AND dc.es_titular = true
              AND ura.esta_activo = true
        ", [$colId]);

        // 3b. Backfill de 'Docente Componente' para todos los dc.es_titular = true
        DB::statement("
            INSERT INTO usuario.usuario_rol_asignacion (
                id_usuario,
                id_rol,
                id_contexto,
                asignado_por,
                creado_por,
                fecha_inicio_planificada,
                fecha_fin_planificada,
                esta_activo,
                fue_eliminado
            )
            SELECT DISTINCT ON (d.id_usuario, comp.id_contexto)
                d.id_usuario,
                ?,
                comp.id_contexto,
                COALESCE(dt.id_usuario, d.id_usuario),
                COALESCE(dt.id_usuario, d.id_usuario),
                NOW(),
                NOW() + INTERVAL '100 years',
                true,
                false
            FROM curso.docente_componente dc
            JOIN usuario.docente d ON d.id_docente = dc.id_docente
            JOIN curso.componente comp ON comp.id_componente = dc.id_componente
            JOIN curso.curso c ON c.id_curso = comp.id_curso
            LEFT JOIN usuario.docente dt ON dt.id_docente = c.id_docente_titular
            WHERE dc.es_titular = true
              AND comp.id_contexto IS NOT NULL
              AND c.fecha_eliminacion IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM usuario.usuario_rol_asignacion ya
                  WHERE ya.id_usuario = d.id_usuario
                    AND ya.id_rol = ?
                    AND ya.id_contexto = comp.id_contexto
                    AND ya.esta_activo = true
                    AND COALESCE(ya.fue_eliminado, false) = false
              )
            ORDER BY d.id_usuario, comp.id_contexto, dc.id_docente_componente DESC
        ", [$dcId, $dcId]);

        // 3c. Backfill de 'Docente Componente Colegiado' para dc.es_titular = false (si no tiene ya Docente Componente)
        DB::statement("
            INSERT INTO usuario.usuario_rol_asignacion (
                id_usuario,
                id_rol,
                id_contexto,
                asignado_por,
                creado_por,
                fecha_inicio_planificada,
                fecha_fin_planificada,
                esta_activo,
                fue_eliminado
            )
            SELECT DISTINCT ON (d.id_usuario, comp.id_contexto)
                d.id_usuario,
                ?,
                comp.id_contexto,
                COALESCE(dt.id_usuario, d.id_usuario),
                COALESCE(dt.id_usuario, d.id_usuario),
                NOW(),
                NOW() + INTERVAL '100 years',
                true,
                false
            FROM curso.docente_componente dc
            JOIN usuario.docente d ON d.id_docente = dc.id_docente
            JOIN curso.componente comp ON comp.id_componente = dc.id_componente
            JOIN curso.curso c ON c.id_curso = comp.id_curso
            LEFT JOIN usuario.docente dt ON dt.id_docente = c.id_docente_titular
            WHERE dc.es_titular = false
              AND comp.id_contexto IS NOT NULL
              AND c.fecha_eliminacion IS NULL
              AND NOT EXISTS (
                  SELECT 1 FROM usuario.usuario_rol_asignacion ya
                  WHERE ya.id_usuario = d.id_usuario
                    AND ya.id_rol IN (?, ?)
                    AND ya.id_contexto = comp.id_contexto
                    AND ya.esta_activo = true
                    AND COALESCE(ya.fue_eliminado, false) = false
              )
            ORDER BY d.id_usuario, comp.id_contexto, dc.id_docente_componente DESC
        ", [$colId, $dcId, $colId]);

        // 3d. Limpieza defensiva: ningún rol de componente activo fuera de categoría 'componente'
        DB::statement("
            UPDATE usuario.usuario_rol_asignacion ura
            SET esta_activo = false,
                fue_eliminado = true,
                fecha_fin_real = COALESCE(ura.fecha_fin_real, NOW())
            FROM usuario.contexto ctx,
                 usuario.tipo_contexto tc
            WHERE ura.id_contexto = ctx.id_contexto
              AND ctx.id_tipo_contexto = tc.id_tipo_contexto
              AND ura.id_rol IN (?, ?)
              AND tc.categoria <> 'componente'
              AND ura.esta_activo = true
        ", [$dcId, $colId]);
    }

    public function down(): void
    {
        // No-op: unificación semántica de roles, delegabilidad y backfill de contextos.
    }
};
