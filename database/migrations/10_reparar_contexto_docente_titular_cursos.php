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

        // 2. Reasignar contexto a cursos que aún no tienen una asignación activa en el contexto real
        DB::statement("
            UPDATE usuario.usuario_rol_asignacion ura
            SET id_contexto = c.id_contexto
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
              AND NOT EXISTS (
                  SELECT 1 
                  FROM usuario.usuario_rol_asignacion ura_activa
                  WHERE ura_activa.id_usuario = ura.id_usuario
                    AND ura_activa.id_rol = ura.id_rol
                    AND ura_activa.id_contexto = c.id_contexto
                    AND ura_activa.esta_activo = true 
                    AND COALESCE(ura_activa.fue_eliminado, false) = false
              )
        ");
    }

    public function down(): void
    {
        // No-op: corrección de datos semántica
    }
};
