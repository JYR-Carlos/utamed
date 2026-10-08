<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Desactivar asignaciones viejas que colisionarían con una ya existente en el contexto real del curso
        DB::statement("
            UPDATE usuario.usuario_rol_asignacion ura
            SET esta_activo = false, fue_eliminado = true
            FROM curso.curso c
            JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
            WHERE ura.id_usuario = d.id_usuario
              AND ura.id_rol = (SELECT id_rol FROM usuario.rol WHERE nombre = 'Docente Titular')
              AND ura.id_contexto != c.id_contexto
              AND ura.id_contexto IN (
                  SELECT ctx.id_contexto 
                  FROM usuario.contexto ctx 
                  WHERE ctx.contexto_display = 'Curso: ' || c.cod_curso
              )
              AND EXISTS (
                  SELECT 1 
                  FROM usuario.usuario_rol_asignacion ura_activa
                  WHERE ura_activa.id_usuario = ura.id_usuario
                    AND ura_activa.id_rol = ura.id_rol
                    AND ura_activa.id_contexto = c.id_contexto
                    AND ura_activa.esta_activo = true 
                    AND COALESCE(ura_activa.fue_eliminado, false) = false
              )
        ");

        // 2. Reasignar al contexto real del curso las asignaciones que aún no tienen
        //    una activa allí. Si un mismo titular tiene varias asignaciones en
        //    contextos huérfanos del mismo curso, moverlas todas en un solo UPDATE
        //    las haría chocar entre sí en uq_no_solapar_roles: se elige una
        //    (la activa más reciente) y las demás activas se desactivan antes.
        $candidatos = "
            WITH candidatos AS (
                SELECT ura.id_ura,
                       c.id_contexto AS id_contexto_real,
                       ROW_NUMBER() OVER (
                           PARTITION BY ura.id_usuario, ura.id_rol, c.id_contexto
                           ORDER BY ura.esta_activo DESC, ura.fecha_creacion DESC, ura.id_ura DESC
                       ) AS orden
                FROM usuario.usuario_rol_asignacion ura
                JOIN usuario.docente d ON d.id_usuario = ura.id_usuario
                JOIN curso.curso c ON c.id_docente_titular = d.id_docente
                WHERE ura.id_rol = (SELECT id_rol FROM usuario.rol WHERE nombre = 'Docente Titular')
                  AND ura.id_contexto != c.id_contexto
                  AND ura.id_contexto IN (
                      SELECT ctx.id_contexto
                      FROM usuario.contexto ctx
                      WHERE ctx.contexto_display = 'Curso: ' || c.cod_curso
                  )
                  AND NOT EXISTS (
                      SELECT 1
                      FROM usuario.usuario_rol_asignacion ura_activa
                      WHERE ura_activa.id_usuario = ura.id_usuario
                        AND ura_activa.id_rol = ura.id_rol
                        AND ura_activa.id_contexto = c.id_contexto
                        AND ura_activa.esta_activo = true
                        AND COALESCE(ura_activa.fue_eliminado, false) = false
                  )
            )
        ";

        // 2a. Desactivar las asignaciones activas sobrantes
        DB::statement($candidatos . "
            UPDATE usuario.usuario_rol_asignacion ura
            SET esta_activo = false, fue_eliminado = true
            FROM candidatos
            WHERE ura.id_ura = candidatos.id_ura
              AND candidatos.orden > 1
              AND ura.esta_activo = true
        ");

        // 2b. Mover la elegida al contexto real del curso
        DB::statement($candidatos . "
            UPDATE usuario.usuario_rol_asignacion ura
            SET id_contexto = candidatos.id_contexto_real
            FROM candidatos
            WHERE ura.id_ura = candidatos.id_ura
              AND candidatos.orden = 1
        ");
    }

    public function down(): void
    {
        // No-op: corrección de datos semántica
    }
};
