<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Usuario\Usuario;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioPermisoEspecial;
use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════════\n";
echo "       VERIFICACIÓN DE PERMISOS: DOCENTE → AYUDANTE\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Get users
$docente = Usuario::find(4);
$ayudante = Usuario::find(5);

if (!$docente || !$ayudante) {
    echo "❌ Error: Usuarios no encontrados\n";
    exit(1);
}

echo "📊 USUARIOS\n";
echo "────────────────────────────────────────────────────────────────────\n";
echo "Docente: ID 4 - " . ($docente->nombre_completo ?? $docente->nombre1) . "\n";
echo "Ayudante: ID 5 - " . ($ayudante->nombre_completo ?? $ayudante->nombre1) . "\n";
echo "\n";

// Check docente roles
echo "👤 ROLES DEL DOCENTE\n";
echo "────────────────────────────────────────────────────────────────────\n";
$docenteRoles = $docente->rolesAsignados()
    ->where('usuario.usuario_rol_asignacion.esta_activo', true)
    ->where('usuario.usuario_rol_asignacion.fue_eliminado', false)
    ->get();

echo "Total roles: {$docenteRoles->count()}\n";
foreach ($docenteRoles as $role) {
    echo "  ✓ {$role->nombre} (ID: {$role->id_rol})\n";
}

// Check docente delegable permissions
echo "\n🎯 PERMISOS DELEGABLES DEL DOCENTE\n";
echo "────────────────────────────────────────────────────────────────────\n";
$delegablePerms = collect();
foreach ($docenteRoles as $role) {
    $perms = $role->permisos()
        ->wherePivot('puede_delegar_permisos', true)
        ->get();
    
    foreach ($perms as $perm) {
        if (str_starts_with($perm->slug, 'cursos') || 
            str_starts_with($perm->slug, 'actividad:') || 
            str_starts_with($perm->slug, 'curso:')) {
            $delegablePerms->push($perm);
        }
    }
}

$delegablePerms = $delegablePerms->unique('id_permiso');
echo "Total permisos delegables: {$delegablePerms->count()}\n";
foreach ($delegablePerms as $perm) {
    echo "  ✓ {$perm->slug} - {$perm->nombre}\n";
}

// Check docente special permissions (using SQL for accuracy)
echo "\n🔐 PERMISOS ESPECIALES DEL DOCENTE\n";
echo "────────────────────────────────────────────────────────────────────\n";
$docenteSpecialPerms = DB::select("
    SELECT 
        upe.id_permiso,
        p.slug,
        p.nombre,
        upe.esta_permitido,
        upe.puede_delegar,
        upe.id_contexto
    FROM usuario.usuario_permiso_especial upe
    LEFT JOIN usuario.permiso p ON upe.id_permiso = p.id_permiso
    WHERE upe.id_usuario = ?
    AND upe.esta_activo = true
    AND upe.fue_borrado = false
    ORDER BY upe.id_permiso
", [4]);

echo "Total permisos especiales: " . count($docenteSpecialPerms) . "\n";
if (count($docenteSpecialPerms) > 0) {
    foreach ($docenteSpecialPerms as $perm) {
        $slug = $perm->slug ?? 'UNKNOWN';
        $permitido = $perm->esta_permitido ? 'Permitido' : 'Denegado';
        $delegable = $perm->puede_delegar ? '✓ Delegable' : '✗ No delegable';
        $contexto = $perm->id_contexto ? "(Contexto: {$perm->id_contexto})" : "(Global)";
        echo "  • {$slug} ({$permitido}) - {$delegable} {$contexto}\n";
    }
} else {
    echo "  (Sin permisos especiales)\n";
}

// Check ayudante current roles
echo "\n👤 ROLES DEL AYUDANTE (ACTUALES)\n";
echo "────────────────────────────────────────────────────────────────────\n";
$ayudanteRoles = $ayudante->rolesAsignados()
    ->where('usuario.usuario_rol_asignacion.esta_activo', true)
    ->where('usuario.usuario_rol_asignacion.fue_eliminado', false)
    ->get();

echo "Total roles: {$ayudanteRoles->count()}\n";
foreach ($ayudanteRoles as $role) {
    echo "  ✓ {$role->nombre} (ID: {$role->id_rol})\n";
}

// Check ayudante special permissions
echo "\n🔐 PERMISOS ESPECIALES DEL AYUDANTE (ACTUALES)\n";
echo "────────────────────────────────────────────────────────────────────\n";
$specialPerms = DB::select("
    SELECT 
        upe.id_permiso,
        p.slug,
        p.nombre,
        upe.esta_permitido,
        upe.id_contexto
    FROM usuario.usuario_permiso_especial upe
    LEFT JOIN usuario.permiso p ON upe.id_permiso = p.id_permiso
    WHERE upe.id_usuario = ?
    AND upe.esta_activo = true
    AND upe.fue_borrado = false
    ORDER BY upe.id_permiso
", [5]);

echo "Total permisos especiales: " . count($specialPerms) . "\n";
foreach ($specialPerms as $perm) {
    $slug = $perm->slug ?? 'UNKNOWN';
    $estado = $perm->esta_permitido ? 'Permitido' : 'Denegado';
    $contexto = $perm->id_contexto ? "(Contexto: {$perm->id_contexto})" : "(Global)";
    echo "  ✓ {$slug} ({$estado}) {$contexto}\n";
}

// Summary
echo "\n📋 RESUMEN\n";
echo "────────────────────────────────────────────────────────────────────\n";
$delegableSpecial = array_filter($docenteSpecialPerms, function ($p) {
    return $p->puede_delegar;
});
$totalDelegable = $delegablePerms->count() + count($delegableSpecial);

if ($totalDelegable > 0) {
    echo "✅ El docente TIENE permisos que puede delegar\n";
    echo "   Puede asignar:\n";
    echo "   • Rol 'ayudante' (ID: 6)\n";
    echo "   • Rol 'estudiante' (ID: 7)\n";
    echo "   • " . $delegablePerms->count() . " permiso(s) de rol\n";
    echo "   • " . count($delegableSpecial) . " permiso(s) especial(es)\n";
} else {
    echo "⚠️  El docente NO tiene permisos delegables\n";
}

echo "\n";
if ($ayudanteRoles->count() > 0) {
    echo "ℹ️  El ayudante actualmente tiene {$ayudanteRoles->count()} rol(es) asignado(s)\n";
} else {
    echo "ℹ️  El ayudante actualmente NO tiene roles asignados\n";
}

echo "\n✅ Verificación completada\n";
echo "═══════════════════════════════════════════════════════════════════\n";
?>
