<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS utamed');

        // 1. Contexto (para multi-tenancy)
        Schema::create('utamed.Contexto', function (Blueprint $table) {
            $table->id('id_contexto');
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();
        });

        // 2. Facultad
        Schema::create('utamed.Facultad', function (Blueprint $table) {
            $table->id('id_facultad');
            $table->string('nombre', 255);
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 3. Departamento
        Schema::create('utamed.Departamento', function (Blueprint $table) {
            $table->id('id_departamento');
            $table->unsignedBigInteger('id_facultad');
            $table->string('nombre', 255);
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_facultad')
                ->references('id_facultad')
                ->on('utamed.Facultad')
                ->onDelete('cascade');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 4. Carrera
        Schema::create('utamed.Carrera', function (Blueprint $table) {
            $table->id('id_carrera');
            $table->string('nombre', 255);
            $table->string('jornada', 50)->nullable();
            $table->string('sede', 100)->nullable();
            $table->string('modalidad', 50)->nullable();
            $table->unsignedBigInteger('id_departamento');
            $table->unsignedBigInteger('id_facultad');
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_departamento')
                ->references('id_departamento')
                ->on('utamed.Departamento')
                ->onDelete('cascade');

            $table->foreign('id_facultad')
                ->references('id_facultad')
                ->on('utamed.Facultad')
                ->onDelete('cascade');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 5. Plan
        Schema::create('utamed.Plan', function (Blueprint $table) {
            $table->id('id_plan');
            $table->unsignedBigInteger('id_carrera');
            $table->integer('agno');
            $table->integer('version');
            $table->integer('creditos_sct_totales')->nullable();
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_carrera')
                ->references('id_carrera')
                ->on('utamed.Carrera')
                ->onDelete('cascade');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 6. Asignatura
        Schema::create('utamed.Asignatura', function (Blueprint $table) {
            $table->id('id_asignatura');
            $table->string('cod_asignatura', 50)->unique();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->integer('creditos_sct')->nullable();
            $table->integer('horas_catedra')->nullable();
            $table->integer('horas_taller')->nullable();
            $table->integer('horas_laboratorio')->nullable();
            $table->integer('horas_dirigidas')->nullable();
            $table->integer('horas_autonomas')->nullable();
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 7. Asignacion_Plan (relación entre Plan y Asignatura)
        Schema::create('utamed.Asignacion_Plan', function (Blueprint $table) {
            $table->id('id_asignacion');
            $table->unsignedBigInteger('id_asignatura');
            $table->unsignedBigInteger('id_plan');
            $table->integer('agno_planificado');
            $table->integer('semestre_planificado');
            $table->string('tipo_ramo', 50)->nullable();
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_asignatura')
                ->references('id_asignatura')
                ->on('utamed.Asignatura')
                ->onDelete('cascade');

            $table->foreign('id_plan')
                ->references('id_plan')
                ->on('utamed.Plan')
                ->onDelete('cascade');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 8. Usuario
        Schema::create('utamed.Usuario', function (Blueprint $table) {
            $table->id('id_usuario');
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->timestamp('fecha_creacion')->useCurrent();
        });

        // 9. Estudiante
        Schema::create('utamed.Estudiante', function (Blueprint $table) {
            $table->id('id_estudiante');
            $table->string('rut', 20)->unique();
            $table->string('nombre_completo', 255);
            $table->integer('agno_ingreso')->nullable();
            $table->unsignedBigInteger('id_carrera')->nullable();
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_carrera')
                ->references('id_carrera')
                ->on('utamed.Carrera')
                ->onDelete('set null');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 10. Docente
        Schema::create('utamed.Docente', function (Blueprint $table) {
            $table->id('id_docente');
            $table->string('rut', 20)->unique();
            $table->string('nombre_completo', 255);
            $table->string('grado', 100)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });

        // 11. Curso
        Schema::create('utamed.Curso', function (Blueprint $table) {
            $table->id('id_curso');
            $table->unsignedBigInteger('id_asignatura');
            $table->unsignedBigInteger('id_plan');
            $table->string('cod_curso', 50)->unique();
            $table->string('nombre', 255)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->integer('numero_semestre')->nullable();
            $table->unsignedBigInteger('id_docente')->nullable();
            $table->unsignedBigInteger('id_contexto')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->useCurrent();
            $table->timestamp('fecha_eliminacion')->nullable();

            $table->foreign('id_asignatura')
                ->references('id_asignatura')
                ->on('utamed.Asignatura')
                ->onDelete('cascade');

            $table->foreign('id_plan')
                ->references('id_plan')
                ->on('utamed.Plan')
                ->onDelete('cascade');

            $table->foreign('id_docente')
                ->references('id_docente')
                ->on('utamed.Docente')
                ->onDelete('set null');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order (respecting foreign key constraints)
        Schema::dropIfExists('utamed.Curso');
        Schema::dropIfExists('utamed.Docente');
        Schema::dropIfExists('utamed.Estudiante');
        Schema::dropIfExists('utamed.Usuario');
        Schema::dropIfExists('utamed.Asignacion_Plan');
        Schema::dropIfExists('utamed.Asignatura');
        Schema::dropIfExists('utamed.Plan');
        Schema::dropIfExists('utamed.Carrera');
        Schema::dropIfExists('utamed.Departamento');
        Schema::dropIfExists('utamed.Facultad');
        Schema::dropIfExists('utamed.Contexto');

        // Drop schema
        DB::statement('DROP SCHEMA IF EXISTS utamed CASCADE');
    }
};
