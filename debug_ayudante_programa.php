<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Find tomas
$tomas = \App\Models\Usuario\Usuario::where('username', 'tomas')->first();
if (!$tomas) {
    echo "Usuario tomas no encontrado\n";
    exit(1);
}

echo "=== USUARIO ===\n";
echo "ID: {$tomas->id_usuario}, Username: {$tomas->username}\n";

// Find all courses for tomas as ayudante
$rolAyudante = \App\Models\Usuario\Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();

echo "\n=== CURSOS ASIGNADOS COMO AYUDANTE ===\n";

$cursosAsignados = \App\Models\Usuario\UsuarioRolAsignacion::where('id_usuario', $tomas->id_usuario)
    ->where('id_rol', $rolAyudante->id_rol)
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->get(['id_contexto']);

$contextIds = $cursosAsignados->pluck('id_contexto');
echo "Contextos asignados: " . json_encode($contextIds->toArray()) . "\n";

$cursos = \App\Models\Curso\Curso::whereIn('id_contexto', $contextIds)
    ->get(['id_curso', 'nombre', 'id_contexto', 'cod_curso']);

echo "Cursos encontrados: " . $cursos->count() . "\n";

if ($cursos->isEmpty()) {
    echo "\nNo hay cursos asignados. Revisa:\n";
    echo "1. ¿Los contextos existen en la tabla curso?\n";
    echo "2. ¿El rol está correctamente configurado?\n";
    exit(0);
}

// Para cada curso
foreach ($cursos as $idx => $curso) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "CURSO #" . ($idx + 1) . ": {$curso->nombre}\n";
    echo "ID: {$curso->id_curso}, Contexto: {$curso->id_contexto}\n";
    echo str_repeat("=", 60) . "\n";

    // Check if programa exists
    $programa = \App\Models\Administrativo\Programa::where('id_curso', $curso->id_curso)->first();
    echo "\n1. ¿Existe programa? " . ($programa ? "SÍ (ID: {$programa->id_programa}, estado: {$programa->estado})" : "NO") . "\n";

    // Check permissions
    echo "\n2. PERMISOS EN CONTEXTO {$curso->id_contexto}:\n";
    $permisos = $tomas->getAllPermissions($curso->id_contexto);
    
    // Filter para programa
    $programaPermisos = array_filter($permisos, function($p) {
        return stripos($p['slug'], 'programa') !== false;
    });

    foreach ($programaPermisos as $p) {
        echo "   - {$p['slug']}: " . ($p['esta_permitido'] ? '✓ SÍ' : '✗ NO') . "\n";
    }

    if (empty($programaPermisos)) {
        echo "   (No hay permisos de programa asignados)\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "ANÁLISIS DE PERMISOS POR ROL\n";
echo str_repeat("=", 60) . "\n";

// Check what permissions are assigned to the Ayudante role
$rolAyudante = \App\Models\Usuario\Rol::whereRaw('LOWER(nombre) = ?', ['ayudante'])->first();
echo "\nRol Ayudante ID: {$rolAyudante->id_rol}\n";

$permisosDelRol = \App\Models\Usuario\AsignacionRolPermiso::where('id_rol', $rolAyudante->id_rol)->get();
echo "Permissions assigned to Ayudante role: " . $permisosDelRol->count() . "\n";

if ($permisosDelRol->isNotEmpty()) {
    echo json_encode($permisosDelRol->toArray(), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "⚠️  EL ROL AYUDANTE NO TIENE PERMISOS ASIGNADOS EN LA TABLA asignacion_rol_permiso!\n";
    echo "   Esto explica por qué tomas no tiene permisos en el contexto.\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "FIN DEL ANÁLISIS\n";
