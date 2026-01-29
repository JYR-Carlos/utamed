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
        // Add missing columns to utamed.Usuario
        Schema::table('utamed.Usuario', function (Blueprint $table) {
            $table->string('rut', 20)->nullable()->after('id_usuario');
            $table->string('nombre1', 100)->nullable()->after('rut');
            $table->string('nombre2', 100)->nullable()->after('nombre1');
            $table->string('apellido1', 100)->nullable()->after('nombre2');
            $table->string('apellido2', 100)->nullable()->after('apellido1');
            $table->string('email', 255)->nullable()->after('apellido2');
            $table->boolean('esta_activo')->default(true)->after('email');
            $table->timestamp('fecha_modificacion')->nullable()->after('fecha_creacion');

            // Rename password to passhash to match model
            $table->renameColumn('password', 'passhash');
        });

        // Add id_usuario to utamed.Estudiante
        Schema::table('utamed.Estudiante', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario')->nullable()->after('id_estudiante');
            $table->foreign('id_usuario')->references('id_usuario')->on('utamed.Usuario')->onDelete('cascade');
        });

        // Add id_usuario to utamed.Docente
        Schema::table('utamed.Docente', function (Blueprint $table) {
            $table->unsignedBigInteger('id_usuario')->nullable()->after('id_docente');
            $table->foreign('id_usuario')->references('id_usuario')->on('utamed.Usuario')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utamed.Docente', function (Blueprint $table) {
            $table->dropForeign(['id_usuario']);
            $table->dropColumn('id_usuario');
        });

        Schema::table('utamed.Estudiante', function (Blueprint $table) {
            $table->dropForeign(['id_usuario']);
            $table->dropColumn('id_usuario');
        });

        Schema::table('utamed.Usuario', function (Blueprint $table) {
            $table->renameColumn('passhash', 'password');
            $table->dropColumn(['rut', 'nombre1', 'nombre2', 'apellido1', 'apellido2', 'email', 'esta_activo', 'fecha_modificacion']);
        });
    }
};
