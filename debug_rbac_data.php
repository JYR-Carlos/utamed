<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "--- PERMISOS ---\n";
    $permisos = DB::table(DB::raw('"utamed.Usuario"."Permiso"'))->get();
    foreach ($permisos as $p) {
        echo "ID: {$p->id_permiso} | Slug: {$p->slug} | Nombre: {$p->nombre} | Modulo: " . ($p->modulo ?? 'NULL') . "\n";
    }

    echo "\n--- TIPOS DE CONTEXTO ---\n";
    $tipos = DB::table(DB::raw('"utamed.Usuario"."Tipo_Contexto"'))->get();
    foreach ($tipos as $t) {
        echo "ID: {$t->id_tipo_contexto} | Nombre: {$t->nombre} | Contexto: {$t->id_contexto}\n";
    }

    echo "\n--- CONTEXTOS ---\n";
    $contextos = DB::table(DB::raw('"utamed.Usuario"."Contexto"'))->get();
    foreach ($contextos as $c) {
        echo "ID: {$c->id_contexto} | Display: {$c->contexto_display}\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
