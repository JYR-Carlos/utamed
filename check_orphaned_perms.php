<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════════\n";
echo "       DIAGNÓSTICO: PERMISOS ESPECIALES HUÉRFANOS\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Check if IDs 60-64 exist
echo "📋 Verificando si los permisos con IDs 60-64 existen en tabla permiso:\n";
for ($i = 60; $i <= 64; $i++) {
    $exists = DB::table('usuario.permiso')->where('id_permiso', $i)->exists();
    $status = $exists ? '✓ Existe' : '✗ No existe (HUÉRFANO)';
    echo "  ID {$i}: {$status}\n";
}

// Show orphaned references
echo "\n📊 Permisos especiales con referencias huérfanas:\n";
$orphaned = DB::select("
    SELECT upe.id_usuario, upe.id_permiso, upe.puede_delegar, upe.esta_permitido
    FROM usuario.usuario_permiso_especial upe
    LEFT JOIN usuario.permiso p ON upe.id_permiso = p.id_permiso
    WHERE p.id_permiso IS NULL
    ORDER BY upe.id_usuario, upe.id_permiso
");

if (count($orphaned) > 0) {
    foreach ($orphaned as $row) {
        echo "  Usuario {$row->id_usuario}: Permiso ID {$row->id_permiso} (Puede delegar: {$row->puede_delegar}, Permitido: {$row->esta_permitido})\n";
    }
} else {
    echo "  Ninguno\n";
}

echo "\n✅ Diagnóstico completado\n";
echo "═══════════════════════════════════════════════════════════════════\n";
?>
