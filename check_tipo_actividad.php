<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check actual column type via pg_catalog
$col = Illuminate\Support\Facades\DB::select(
    "SELECT pg_catalog.format_type(a.atttypid, a.atttypmod) as col_type FROM pg_catalog.pg_attribute a JOIN pg_catalog.pg_class c ON c.oid = a.attrelid JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'agenda' AND c.relname = 'actividad' AND a.attname = 'tipo_actividad' AND a.attnum > 0"
);
echo "pg_catalog column type:\n";
print_r($col);

$sp = Illuminate\Support\Facades\DB::select('SHOW search_path');
echo "search_path: ";
print_r($sp);

$tables = Illuminate\Support\Facades\DB::select("SELECT table_schema, table_name FROM information_schema.tables WHERE table_name = 'actividad'");
echo "Tables named actividad:\n";
print_r($tables);

$cols = Illuminate\Support\Facades\DB::select(
    "SELECT table_schema, column_name, data_type, udt_name FROM information_schema.columns WHERE table_name = 'actividad' AND column_name = 'tipo_actividad'"
);
echo "Columns:\n";
print_r($cols);

// Check enum values defined in DB
$enumVals = Illuminate\Support\Facades\DB::select(
    "SELECT e.enumlabel FROM pg_type t JOIN pg_enum e ON t.oid = e.enumtypid WHERE t.typname = 'en_tipo_actividad' ORDER BY e.enumsortorder"
);
echo "DB enum values for en_tipo_actividad:\n";
print_r($enumVals);

// Check actual data
$count = Illuminate\Support\Facades\DB::select('SELECT COUNT(*) as cnt FROM actividad');
echo "Row count:\n";
print_r($count);

$vals = Illuminate\Support\Facades\DB::select('SELECT DISTINCT tipo_actividad::text FROM actividad LIMIT 10');
echo "Distinct tipo_actividad values:\n";
print_r($vals);
