<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE usuario.usuario_rol_asignacion ura
            SET id_contexto = c.id_contexto
            FROM curso.curso c
            JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
            WHERE ura.id_usuario = d.id_usuario
              AND ura.id_rol = (SELECT id_rol FROM usuario.rol WHERE nombre = 'Docente Titular')
              AND ura.id_contexto IN (
                  SELECT ctx.id_contexto 
                  FROM usuario.contexto ctx 
                  WHERE ctx.contexto_display = 'Curso: ' || c.cod_curso
              )
        ");
    }

    public function down(): void
    {
        // No-op: corrección de datos semántica
    }
};
