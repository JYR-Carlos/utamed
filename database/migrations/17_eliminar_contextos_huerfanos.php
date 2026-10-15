<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Elimina los contextos huérfanos que dejó la aplicación al crear cursos y componentes.
 *
 * CursoService (store/copiar) e IntranetService creaban un contexto propio antes de
 * insertar, y los triggers tr_curso_pre_insert / tr_componente_pre_insert lo
 * reemplazaban por el contexto real (con tipo y padre). El de la aplicación quedaba
 * sin tipo, sin padre y sin dueño.
 *
 * Criterio (no se usa el nombre, que se repite y se puede editar):
 * - sin tipo y sin padre: todo contexto legítimo tiene tipo, incluido el global;
 * - nada lo referencia: ninguna de las tablas con FK a usuario.contexto ni un contexto hijo.
 *
 * Corre después de la 10, que mueve al contexto real los roles de titular que habían
 * quedado en estos huérfanos. Los huérfanos que aún tienen asignaciones (por ejemplo,
 * eliminadas, que se conservan como historial) no se borran.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            DELETE FROM usuario.contexto x
            WHERE x.id_tipo_contexto IS NULL
              AND x.id_contexto_padre IS NULL
              AND NOT EXISTS (SELECT 1 FROM usuario.contexto h WHERE h.id_contexto_padre = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM usuario.usuario_rol_asignacion u WHERE u.id_contexto = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM usuario.usuario_permiso_especial p WHERE p.id_contexto = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM curso.curso c WHERE c.id_contexto = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM curso.componente k WHERE k.id_contexto = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM agenda.actividad a WHERE a.id_contexto = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM administrativo.facultad f WHERE f.id_contexto = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM administrativo.departamento d WHERE d.id_contexto = x.id_contexto)
              AND NOT EXISTS (SELECT 1 FROM administrativo.carrera r WHERE r.id_contexto = x.id_contexto)
        ");
    }

    public function down(): void
    {
        // No-op: los contextos borrados no tenían uso; no hay nada que restaurar.
    }
};
