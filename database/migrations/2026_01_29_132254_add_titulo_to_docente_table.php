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
        Schema::table('utamed.Docente', function (Blueprint $table) {
            $table->string('titulo', 255)->nullable()->after('grado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utamed.Docente', function (Blueprint $table) {
            $table->dropColumn('titulo');
        });
    }
};
