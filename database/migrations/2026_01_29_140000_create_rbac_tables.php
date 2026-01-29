<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Permiso
        Schema::create('utamed.Permiso', function (Blueprint $table) {
            $table->smallIncrements('id_permiso');
            $table->string('slug', 50)->unique();
            $table->text('nombre');
            $table->text('descripcion')->nullable();
        });

        // 2. Rol
        Schema::create('utamed.Rol', function (Blueprint $table) {
            $table->smallIncrements('id_rol');
            $table->string('nombre', 50);
            $table->unsignedBigInteger('id_usuario_autor');

            $table->foreign('id_usuario_autor')
                ->references('id_usuario')
                ->on('utamed.Usuario')
                ->onDelete('cascade');
        });

        // 3. Asignación_Rol_Permiso
        Schema::create('utamed.Asignación_Rol_Permiso', function (Blueprint $table) {
            $table->unsignedSmallInteger('id_rol');
            $table->unsignedSmallInteger('id_permiso');
            $table->boolean('puede_delegar_permisos')->default(false);

            $table->primary(['id_rol', 'id_permiso']);

            $table->foreign('id_rol')
                ->references('id_rol')
                ->on('utamed.Rol')
                ->onDelete('cascade');

            $table->foreign('id_permiso')
                ->references('id_permiso')
                ->on('utamed.Permiso')
                ->onDelete('cascade');
        });

        // 4. Usuario_Rol_Asignación
        Schema::create('utamed.Usuario_Rol_Asignación', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario_recipiente');
            $table->unsignedBigInteger('id_contexto');
            $table->unsignedSmallInteger('id_rol');
            $table->unsignedBigInteger('id_usuario_asignador');

            $table->integer('asignado_por')->nullable(); // Legacy field from dictionary
            $table->timestamp('fecha_inicio_planificada')->nullable();
            $table->timestamp('fecha_fin_planificada')->nullable();
            $table->timestamp('fecha_fin_real')->nullable();
            $table->boolean('fue_eliminado')->default(false);
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->nullable();
            $table->boolean('esta_activo')->default(true);

            $table->primary(['id_usuario_recipiente', 'id_contexto', 'id_rol', 'id_usuario_asignador'], 'usuario_rol_asignacion_pk');

            $table->foreign('id_usuario_recipiente')
                ->references('id_usuario')
                ->on('utamed.Usuario')
                ->onDelete('cascade');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('cascade');

            $table->foreign('id_rol')
                ->references('id_rol')
                ->on('utamed.Rol')
                ->onDelete('cascade');

            $table->foreign('id_usuario_asignador')
                ->references('id_usuario')
                ->on('utamed.Usuario')
                ->onDelete('cascade');
        });

        // 5. Usuario_Permiso_Especial
        Schema::create('utamed.Usuario_Permiso_Especial', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario_recipiente');
            $table->unsignedSmallInteger('id_permiso');
            $table->unsignedBigInteger('id_contexto');
            $table->unsignedBigInteger('id_usuario_asignador');

            $table->timestamp('fecha_inicio_planificada')->useCurrent();
            $table->timestamp('fecha_fin_planificada')->nullable();
            $table->boolean('esta_permitido')->default(true); // Can be used to explicitly REVOKE a permission
            $table->boolean('puede_delegar')->default(false);
            $table->timestamp('fecha_fin_real')->nullable();
            $table->boolean('fue_borrado')->default(false);
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamp('fecha_modificacion')->nullable();
            $table->boolean('esta_activo')->default(true);

            $table->primary(['id_usuario_recipiente', 'id_permiso', 'id_contexto', 'id_usuario_asignador'], 'usuario_permiso_especial_pk');

            $table->foreign('id_usuario_recipiente')
                ->references('id_usuario')
                ->on('utamed.Usuario')
                ->onDelete('cascade');

            $table->foreign('id_permiso')
                ->references('id_permiso')
                ->on('utamed.Permiso')
                ->onDelete('cascade');

            $table->foreign('id_contexto')
                ->references('id_contexto')
                ->on('utamed.Contexto')
                ->onDelete('cascade');

            $table->foreign('id_usuario_asignador')
                ->references('id_usuario')
                ->on('utamed.Usuario')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utamed.Usuario_Permiso_Especial');
        Schema::dropIfExists('utamed.Usuario_Rol_Asignación');
        Schema::dropIfExists('utamed.Asignación_Rol_Permiso');
        Schema::dropIfExists('utamed.Rol');
        Schema::dropIfExists('utamed.Permiso');
    }
};
