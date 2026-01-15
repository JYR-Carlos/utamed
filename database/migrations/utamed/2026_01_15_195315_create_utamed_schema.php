<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS utamed');

        // 1. Facultad
        Schema::create('utamed.facultad', function (Blueprint $table) {
            $table->increments('id_facultad');
            $table->text('nombre')->nullable(false);
        });

        // 2. Departamento
        Schema::create('utamed.departamento', function (Blueprint $table) {
            $table->smallInteger('id_departamento')->generatedAs();
            $table->integer('id_facultad');
            $table->text('nombre')->nullable();
            
            $table->primary(['id_departamento', 'id_facultad']);
            $table->foreign('id_facultad')
                ->references('id_facultad')
                ->on('utamed.facultad')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 3. Carrera
        Schema::create('utamed.carrera', function (Blueprint $table) {
            $table->integer('id_carrera')->primary()->generatedAs();
            $table->text('nombre')->nullable();
            $table->integer('id_facultad')->nullable();
            $table->smallInteger('id_departamento')->nullable();
            
            $table->foreign(['id_departamento', 'id_facultad'])
                ->references(['id_departamento', 'id_facultad'])
                ->on('utamed.departamento')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        // 4. Asignatura
        Schema::create('utamed.asignatura', function (Blueprint $table) {
            $table->integer('id_asignatura')->primary()->generatedAs();
            $table->text('cod_asignatura')->nullable(false)->unique('unique_cod_asignatura');
            $table->text('nombre')->nullable();
            $table->smallInteger('id_carrera')->nullable();
            
            $table->foreign('id_carrera')
                ->references('id_carrera')
                ->on('utamed.carrera')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 5. Programa
        Schema::create('utamed.programa', function (Blueprint $table) {
            $table->smallInteger('id_asignatura');
            $table->smallInteger('version');
            $table->date('fecha_creacion')->nullable();
            $table->integer('creditos_sct')->nullable();
            
            $table->primary(['id_asignatura', 'version']);
            $table->foreign('id_asignatura')
                ->references('id_asignatura')
                ->on('utamed.asignatura')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 6. Estudiante
        Schema::create('utamed.estudiante', function (Blueprint $table) {
            $table->integer('id_estudiante')->primary()->generatedAs();
            $table->text('rut')->nullable(false);
            $table->text('nombre_completo')->nullable();
            $table->integer('agno_ingreso')->nullable();
            $table->integer('id_carrera')->nullable();
            
            $table->foreign('id_carrera')
                ->references('id_carrera')
                ->on('utamed.carrera')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 7. Docente
        Schema::create('utamed.docente', function (Blueprint $table) {
            $table->integer('id_docente')->primary()->generatedAs();
            $table->text('rut')->nullable(false);
            $table->text('nombre_completo')->nullable();
            $table->text('grado')->nullable();
            $table->text('cargo')->nullable();
        });

        // 8. Usuario
        Schema::create('utamed.usuario', function (Blueprint $table) {
            $table->integer('id_usuario')->primary()->generatedAs();
            $table->string('username', 10)->nullable(false);
            $table->text('password')->nullable(false);
            $table->date('fecha_creacion')->nullable();
        });

        // Add foreign keys for Usuario (1:1 relationships)
        Schema::table('utamed.estudiante', function (Blueprint $table) {
            $table->foreign('id_estudiante')
                ->references('id_usuario')
                ->on('utamed.usuario')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        Schema::table('utamed.docente', function (Blueprint $table) {
            $table->foreign('id_docente')
                ->references('id_usuario')
                ->on('utamed.usuario')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 9. Curso
        Schema::create('utamed.curso', function (Blueprint $table) {
            $table->integer('id_curso')->generatedAs();
            $table->integer('id_asignatura');
            $table->text('cod_curso')->nullable(false);
            $table->text('nombre')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->smallInteger('numero_semestre')->nullable();
            $table->integer('id_docente')->nullable();
            
            $table->primary(['id_curso', 'id_asignatura']);
            $table->foreign('id_asignatura')
                ->references('id_asignatura')
                ->on('utamed.asignatura')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->foreign('id_docente')
                ->references('id_docente')
                ->on('utamed.docente')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 10. Inscribe
        Schema::create('utamed.inscribe', function (Blueprint $table) {
            $table->integer('id_asignatura');
            $table->integer('id_estudiante');
            $table->integer('id_curso');
            $table->smallInteger('num_intento')->default(1);
            $table->date('fecha_inscripcion')->nullable();
            $table->char('estado_inscripcion', 1)->default('i');
            $table->integer('promedio_parcial')->nullable();
            
            $table->primary(['id_asignatura', 'id_estudiante', 'id_curso', 'num_intento']);
            $table->foreign('id_estudiante')
                ->references('id_estudiante')
                ->on('utamed.estudiante')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->foreign(['id_curso', 'id_asignatura'])
                ->references(['id_curso', 'id_asignatura'])
                ->on('utamed.curso')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 11. Asistencia
        Schema::create('utamed.asistencia', function (Blueprint $table) {
            $table->integer('id_curso');
            $table->integer('id_estudiante');
            $table->integer('num_intento');
            $table->integer('num_clase');
            $table->date('fecha')->nullable();
            $table->boolean('presente')->default(false);
            
            $table->primary(['id_curso', 'id_estudiante', 'num_intento', 'num_clase']);
            $table->foreign(['id_curso', 'id_estudiante', 'num_intento'])
                ->references(['id_curso', 'id_estudiante', 'num_intento'])
                ->on('utamed.inscribe')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 12. Unidad
        Schema::create('utamed.unidad', function (Blueprint $table) {
            $table->integer('id_curso');
            $table->smallInteger('num_unidad');
            
            $table->primary(['id_curso', 'num_unidad']);
            $table->foreign('id_curso')
                ->references('id_curso')
                ->on('utamed.curso')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 13. Tipo_Actividad
        Schema::create('utamed.tipo_actividad', function (Blueprint $table) {
            $table->smallInteger('id_tipo')->primary()->generatedAs();
            $table->text('tipo_entrega')->nullable(false);
            $table->boolean('es_grupal')->default(false);
        });

        // 14. Actividad
        Schema::create('utamed.actividad', function (Blueprint $table) {
            $table->integer('id_actividad')->primary()->generatedAs();
            $table->integer('id_curso')->nullable();
            $table->smallInteger('num_unidad')->nullable();
            $table->text('nombre')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->boolean('visible')->default(true);
            $table->integer('tipo_actividad')->nullable(false);
            
            $table->foreign(['id_curso', 'num_unidad'])
                ->references(['id_curso', 'num_unidad'])
                ->on('utamed.unidad')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->foreign('tipo_actividad')
                ->references('id_tipo')
                ->on('utamed.tipo_actividad')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 15. Estado_Actividad
        Schema::create('utamed.estado_actividad', function (Blueprint $table) {
            $table->smallInteger('id_estado')->primary()->generatedAs();
            $table->text('titulo')->nullable(false);
            $table->text('descripcion')->nullable();
        });

        // 16. Actividad_Asignada
        Schema::create('utamed.actividad_asignada', function (Blueprint $table) {
            $table->integer('id_actividad');
            $table->integer('grupo');
            $table->smallInteger('nota')->nullable();
            $table->smallInteger('estado_actual')->nullable();
            
            $table->primary(['id_actividad', 'grupo']);
            $table->foreign('id_actividad')
                ->references('id_actividad')
                ->on('utamed.actividad')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->foreign('estado_actual')
                ->references('id_estado')
                ->on('utamed.estado_actividad')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 17. Asignado_Actividad
        Schema::create('utamed.asignado_actividad', function (Blueprint $table) {
            $table->integer('id_estudiante')->primary();
            $table->integer('grupo_Actividad_Asignada')->nullable();
            $table->integer('id_actividad_Actividad_Asignada')->nullable();
            
            $table->foreign(['id_actividad_Actividad_Asignada', 'grupo_Actividad_Asignada'])
                ->references(['id_actividad', 'grupo'])
                ->on('utamed.actividad_asignada')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->foreign('id_estudiante')
                ->references('id_estudiante')
                ->on('utamed.estudiante')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 18. Rubrica_Analitica
        Schema::create('utamed.rubrica_analitica', function (Blueprint $table) {
            $table->integer('id_rubrica')->primary()->generatedAs();
            $table->integer('id_actividad')->nullable();
            $table->text('descripcion')->nullable();
            
            $table->foreign('id_actividad')
                ->references('id_actividad')
                ->on('utamed.actividad')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 19. Criterio_Rubrica
        Schema::create('utamed.criterio_rubrica', function (Blueprint $table) {
            $table->integer('id_rubrica');
            $table->smallInteger('id_criterio')->generatedAs();
            $table->text('definicion')->nullable();
            $table->smallInteger('ponderacion')->nullable();
            
            $table->primary(['id_rubrica', 'id_criterio']);
            $table->foreign('id_rubrica')
                ->references('id_rubrica')
                ->on('utamed.rubrica_analitica')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 20. Nivel_Rubrica
        Schema::create('utamed.nivel_rubrica', function (Blueprint $table) {
            $table->integer('id_rubrica');
            $table->smallInteger('id_nivel')->generatedAs();
            $table->text('definicion')->nullable(false);
            $table->smallInteger('nivel_correlativo')->nullable();
            
            $table->primary(['id_rubrica', 'id_nivel']);
            $table->foreign('id_rubrica')
                ->references('id_rubrica')
                ->on('utamed.rubrica_analitica')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 21. Cruce_Nivel_Criterio
        Schema::create('utamed.cruce_nivel_criterio', function (Blueprint $table) {
            $table->integer('id_rubrica');
            $table->smallInteger('id_nivel');
            $table->smallInteger('id_criterio');
            $table->text('descripcion')->nullable();
            
            $table->primary(['id_rubrica', 'id_nivel', 'id_criterio']);
            $table->foreign(['id_rubrica', 'id_criterio'])
                ->references(['id_rubrica', 'id_criterio'])
                ->on('utamed.criterio_rubrica')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->foreign(['id_rubrica', 'id_nivel'])
                ->references(['id_rubrica', 'id_nivel'])
                ->on('utamed.nivel_rubrica')
                ->onDelete('no action')
                ->onUpdate('no action');
        });

        // 22. Registro_Agenda
        Schema::create('utamed.registro_agenda', function (Blueprint $table) {
            $table->smallInteger('id_actividad');
            $table->smallInteger('id_grupo');
            $table->smallInteger('id_registro');
            $table->smallInteger('autor');
            
            $table->primary(['id_actividad', 'id_grupo', 'id_registro', 'autor']);
            $table->foreign(['id_actividad', 'id_grupo'])
                ->references(['id_actividad', 'grupo'])
                ->on('utamed.actividad_asignada')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->foreign('autor')
                ->references('id_usuario')
                ->on('utamed.usuario')
                ->onDelete('no action')
                ->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order (respecting foreign key constraints)
        Schema::dropIfExists('utamed.registro_agenda');
        Schema::dropIfExists('utamed.cruce_nivel_criterio');
        Schema::dropIfExists('utamed.nivel_rubrica');
        Schema::dropIfExists('utamed.criterio_rubrica');
        Schema::dropIfExists('utamed.rubrica_analitica');
        Schema::dropIfExists('utamed.asignado_actividad');
        Schema::dropIfExists('utamed.actividad_asignada');
        Schema::dropIfExists('utamed.estado_actividad');
        Schema::dropIfExists('utamed.actividad');
        Schema::dropIfExists('utamed.tipo_actividad');
        Schema::dropIfExists('utamed.unidad');
        Schema::dropIfExists('utamed.asistencia');
        Schema::dropIfExists('utamed.inscribe');
        Schema::dropIfExists('utamed.curso');
        Schema::dropIfExists('utamed.usuario');
        Schema::dropIfExists('utamed.docente');
        Schema::dropIfExists('utamed.estudiante');
        Schema::dropIfExists('utamed.programa');
        Schema::dropIfExists('utamed.asignatura');
        Schema::dropIfExists('utamed.carrera');
        Schema::dropIfExists('utamed.departamento');
        Schema::dropIfExists('utamed.facultad');
        
        // Drop schema
        DB::statement('DROP SCHEMA IF EXISTS utamed CASCADE');
    }
};
