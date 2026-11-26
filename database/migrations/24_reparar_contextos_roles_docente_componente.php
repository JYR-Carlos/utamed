<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Reconstruir en cada comp.id_contexto las asignaciones válidas de
        //    'Docente Componente' y 'Docente Componente Colegiado' para docentes
        //    que hoy tienen ese rol mal asignado en contexto global o de curso.
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
            SELECT DISTINCT ON (d.id_usuario, rol_target.id_rol, comp.id_contexto)
                d.id_usuario,
                rol_target.id_rol,
                comp.id_contexto,
                COALESCE(ura.asignado_por, ura.creado_por, d.id_usuario),
                COALESCE(ura.creado_por, d.id_usuario),
                COALESCE(ura.fecha_inicio_planificada, NOW()),
                COALESCE(ura.fecha_fin_planificada, NOW() + INTERVAL '100 years'),
                true,
                false
            FROM usuario.usuario_rol_asignacion ura
            JOIN usuario.rol r_ura ON r_ura.id_rol = ura.id_rol
            JOIN usuario.contexto ctx_ura ON ctx_ura.id_contexto = ura.id_contexto
            JOIN usuario.tipo_contexto tc_ura ON tc_ura.id_tipo_contexto = ctx_ura.id_tipo_contexto
            JOIN usuario.docente d ON d.id_usuario = ura.id_usuario
            JOIN curso.docente_componente dc ON dc.id_docente = d.id_docente
            JOIN curso.componente comp ON comp.id_componente = dc.id_componente
            JOIN curso.curso c ON c.id_curso = comp.id_curso
            JOIN usuario.rol rol_target ON rol_target.nombre = CASE
                WHEN dc.es_titular THEN 'Docente Componente'
                ELSE 'Docente Componente Colegiado'
            END
            WHERE ura.esta_activo = true
              AND COALESCE(ura.fue_eliminado, false) = false
              AND r_ura.nombre IN ('Docente Componente', 'Docente Componente Colegiado')
              AND tc_ura.categoria <> 'componente'
              AND comp.id_contexto IS NOT NULL
              AND d.id_docente IS DISTINCT FROM c.id_docente_titular
              AND (
                  ura.id_contexto = c.id_contexto
                  OR tc_ura.categoria = 'global'
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM usuario.usuario_rol_asignacion ya
                  WHERE ya.id_usuario = d.id_usuario
                    AND ya.id_rol = rol_target.id_rol
                    AND ya.id_contexto = comp.id_contexto
                    AND ya.esta_activo = true
                    AND COALESCE(ya.fue_eliminado, false) = false
              )
            ORDER BY d.id_usuario, rol_target.id_rol, comp.id_contexto, ura.fecha_creacion DESC, ura.id_ura DESC
        ");

        // 2. Desactivar todas las asignaciones activas de roles de componente que
        //    quedaron fuera de un contexto 'componente' (ej: curso o global).
        DB::statement("
            UPDATE usuario.usuario_rol_asignacion ura
            SET esta_activo = false,
                fue_eliminado = true,
                fecha_fin_real = COALESCE(ura.fecha_fin_real, NOW())
            FROM usuario.rol r,
                 usuario.contexto ctx,
                 usuario.tipo_contexto tc
            WHERE ura.id_rol = r.id_rol
              AND ura.id_contexto = ctx.id_contexto
              AND ctx.id_tipo_contexto = tc.id_tipo_contexto
              AND r.nombre IN ('Docente Componente', 'Docente Componente Colegiado')
              AND tc.categoria <> 'componente'
              AND ura.esta_activo = true
        ");
    }

    public function down(): void
    {
        // No-op: saneamiento semántico de contextos de roles de componente.
    }
};
