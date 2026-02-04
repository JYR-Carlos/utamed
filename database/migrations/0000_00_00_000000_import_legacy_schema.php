<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $path = base_path('database-model/init_scripts/01-sql_def.sql');

        if (!File::exists($path)) {
            throw new \Exception("SQL definition file not found at: $path");
        }

        $sql = File::get($path);

        // Remove psql meta-commands like \c, \connect, etc.
        $sql = preg_replace('/^\\\\.*\n/m', '', $sql);

        // Remove comments (optional but creates less noise)
        // $sql = preg_replace('/--.*\n/m', '', $sql);

        try {
            DB::unprepared($sql);
        } catch (\Exception $e) {
            // Log error but try to continue if it's just a warning? 
            // No, critical schema failure should stop.
            throw new \Exception("Error importing SQL: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schemas = [
            'utamed.Usuario',
            'utamed.Administrativo',
            'utamed.Curso',
            'utamed.Agenda'
        ];

        foreach ($schemas as $s) {
            DB::statement("DROP SCHEMA IF EXISTS \"$s\" CASCADE");
        }
    }
};
