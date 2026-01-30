<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$modules = \App\Models\Usuario\Permiso::distinct()->pluck('modulo');
foreach ($modules as $m) {
    echo "Module: [" . $m . "]\n";
}
