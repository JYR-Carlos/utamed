<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════════\n";
echo "       DETALLE: PERMISOS ESPECIALES DEL DOCENTE\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Get details of special permissions
$result = DB::select("
    SELECT 
        upe.id_permiso,
        p.slug,
        p.nombre,
        upe.esta_permitido,
        upe.puede_delegar,
        upe.id_contexto
    FROM usuario.usuario_permiso_especial upe
    LEFT JOIN usuario.permiso p ON upe.id_permiso = p.id_permiso
    WHERE upe.id_usuario = 4
    AND upe.esta_activo = true
    AND upe.fue_borrado = false
    ORDER BY upe.id_permiso
");

echo "Permisos especiales del docente (ID 4):\n";
echo "───────────────────────────────────────────────────────────────────\n";
foreach ($result as $row) {
    $permitido = $row->esta_permitido ? 'Permitido' : 'Denegado';
    $delegable = $row->puede_delegar ? 'SÍ' : 'NO';
    $contexto = $row->id_contexto ? "Contexto: {$row->id_contexto}" : "Global";
    
    echo "  ID {$row->id_permiso}: {$row->slug}\n";
    echo "     Nombre: {$row->nombre}\n";
    echo "     Estado: {$permitido}\n";
    echo "     ¿Puede delegar?: {$delegable}\n";
    echo "     {$contexto}\n\n";
}

echo "═══════════════════════════════════════════════════════════════════\n";
?>
