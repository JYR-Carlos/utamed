<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE curso.bibliografia DROP CONSTRAINT IF EXISTS uq_bibliografia');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE curso.bibliografia ADD CONSTRAINT uq_bibliografia UNIQUE (uuid_archivo)');
    }
};
