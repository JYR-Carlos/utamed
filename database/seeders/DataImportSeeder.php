<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DataImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = base_path('database-model/init_scripts/03-inserts.sql');

        if (!File::exists($path)) {
            $this->command->error("SQL file not found at: $path");
            return;
        }

        $this->command->info("Reading SQL file from: $path");
        $sql = File::get($path);

        // Remove psql meta-commands like \c
        $sql = preg_replace('/^\\\\c.*\n/m', '', $sql);

        // Execute raw SQL
        try {
            DB::unprepared($sql);
            $this->command->info('Data imported successfully from 03-inserts.sql');
        } catch (\Exception $e) {
            $this->command->error('Error importing data: ' . $e->getMessage());
            throw $e;
        }
    }
}
