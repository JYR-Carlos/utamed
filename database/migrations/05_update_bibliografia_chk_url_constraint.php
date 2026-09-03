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
        DB::statement('ALTER TABLE curso.bibliografia DROP CONSTRAINT IF EXISTS chk_url_notrq_when_no_es_bibuta');
        DB::statement('ALTER TABLE curso.bibliografia ADD CONSTRAINT chk_url_notrq_when_no_es_bibuta CHECK (es_bibliografia_uta = TRUE OR url IS NOT NULL OR uuid_archivo IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE curso.bibliografia DROP CONSTRAINT IF EXISTS chk_url_notrq_when_no_es_bibuta');
        DB::statement('ALTER TABLE curso.bibliografia ADD CONSTRAINT chk_url_notrq_when_no_es_bibuta CHECK (es_bibliografia_uta = TRUE OR url IS NOT NULL)');
    }
};
