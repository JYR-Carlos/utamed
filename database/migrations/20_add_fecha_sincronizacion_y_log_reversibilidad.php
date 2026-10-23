<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trazabilidad temporal y log de auditoría/reversibilidad para sincronizaciones con Intranet.
 *
 * Agrega:
 * 1. fecha_ultima_sincronizacion a curso.curso (timestamp sin zona horaria, nullable)
 * 2. curso.intranet_sincronizacion_log para registrar snapshots atómicos de componentes e inscripciones
 *    creadas/actualizadas durante cada sincronización, permitiendo rollback seguro.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar fecha_ultima_sincronizacion a curso.curso
        DB::statement('
            ALTER TABLE curso.curso 
            ADD COLUMN IF NOT EXISTS fecha_ultima_sincronizacion timestamp without time zone DEFAULT NULL
        ');

        // 2. Crear tabla de logs y snapshots de sincronización
        DB::statement("
            CREATE TABLE IF NOT EXISTS curso.intranet_sincronizacion_log (
                id_log integer NOT NULL GENERATED ALWAYS AS IDENTITY,
                id_curso integer NOT NULL,
                id_usuario integer DEFAULT NULL,
                fecha_sincronizacion timestamp without time zone NOT NULL DEFAULT now(),
                tipo_sincronizacion varchar(50) NOT NULL,
                estado varchar(30) NOT NULL DEFAULT 'APLICADA',
                snapshot_cambios jsonb NOT NULL DEFAULT '{}'::jsonb,
                fecha_reversion timestamp without time zone DEFAULT NULL,
                id_usuario_reversion integer DEFAULT NULL,
                motivo_reversion text DEFAULT NULL,
                CONSTRAINT pk_intranet_sincronizacion_log PRIMARY KEY (id_log),
                CONSTRAINT fk_sincronizacion_log_curso FOREIGN KEY (id_curso) 
                    REFERENCES curso.curso (id_curso) ON DELETE CASCADE,
                CONSTRAINT fk_sincronizacion_log_usuario FOREIGN KEY (id_usuario) 
                    REFERENCES usuario.usuario (id_usuario) ON DELETE SET NULL,
                CONSTRAINT fk_sincronizacion_log_usuario_rev FOREIGN KEY (id_usuario_reversion) 
                    REFERENCES usuario.usuario (id_usuario) ON DELETE SET NULL
            );
        ");

        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_intranet_sincronizacion_log_curso 
            ON curso.intranet_sincronizacion_log (id_curso, fecha_sincronizacion DESC);
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS curso.intranet_sincronizacion_log CASCADE;');
        DB::statement('ALTER TABLE curso.curso DROP COLUMN IF EXISTS fecha_ultima_sincronizacion;');
    }
};
