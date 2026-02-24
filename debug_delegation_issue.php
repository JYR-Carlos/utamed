<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Usuario\Usuario;
use App\Models\Curso\Curso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "═══════════════════════════════════════════════════════════════════\n";
echo "       DEBUG: ¿Por qué no se pueden asignar permisos especiales?\n";
echo "═══════════════════════════════════════════════════════════════════\n\n";

// Configuración de usuarios y curso
$docenteId = 4; // Usuario docente 
$ayudanteId = 5; // Usuario ayudante
$cursoId = 1;   // ID del curso donde se intenta asignar

$docente = Usuario::find($docenteId);
$ayudante = Usuario::find($ayudanteId);
$curso = Curso::find($cursoId);

if (!$docente || !$ayudante || !$curso) {
    echo "❌ Error: No se encontraron usuarios o curso\n";
    echo "   Docente ID {$docenteId}: " . ($docente ? 'OK' : 'NO EXISTE') . "\n";
    echo "   Ayudante ID {$ayudanteId}: " . ($ayudante ? 'OK' : 'NO EXISTE') . "\n";
    echo "   Curso ID {$cursoId}: " . ($curso ? 'OK' : 'NO EXISTE') . "\n";
    exit(1);
}

echo "✅ CONTEXTO CONFIGURADO\n";
echo "────────────────────────────────────────────────────────────────────\n";
echo "Docente: {$docente->nombre1} (ID: {$docente->id_usuario})\n";
echo "Ayudante: {$ayudante->nombre1} (ID: {$ayudante->id_usuario})\n";
echo "Curso: {$curso->cod_curso} - {$curso->nombre} (ID: {$curso->id_curso})\n";
echo "Contexto del curso: {$curso->id_contexto}\n\n";

// ======================================================================
// PASO 1: Verificar el método getDelegablePermissions del docente
// ======================================================================

echo "🔍 PASO 1: ANÁLISIS DE DELEGACIÓN - DOCENTE\n";
echo "────────────────────────────────────────────────────────────────────\n";

// Simular el método getDelegablePermissions del DocenteCursoController
function getDelegablePermissionsDebug(Usuario $user, ?int $idContexto = null) {
    echo "  📝 Analizando permisos delegables para usuario {$user->id_usuario}...\n";
    
    // 1. Permisos de roles
    $roleAssignments = $user->rolesAsignados()
        ->where('usuario.usuario_rol_asignacion.esta_activo', true)
        ->where('usuario.usuario_rol_asignacion.fue_eliminado', false)
        ->get();
    
    echo "  📊 Roles activos: {$roleAssignments->count()}\n";
    
    $rolePerms = collect();
    foreach ($roleAssignments as $role) {
        $perms = $role->permisos()
            ->wherePivot('puede_delegar_permisos', true)
            ->get();
            
        echo "    - Rol '{$role->nombre}': {$perms->count()} permisos delegables\n";
        
        foreach ($perms as $perm) {
            if (str_starts_with($perm->slug, 'cursos') || 
                str_starts_with($perm->slug, 'actividad:') || 
                str_starts_with($perm->slug, 'curso:')) {
                $rolePerms->push($perm);
                echo "      ✓ {$perm->slug} (de rol)\n";
            }
        }
    }
    $rolePerms = $rolePerms->unique('id_permiso');
    
    // 2. Permisos especiales
    $specialQuery = \App\Models\Usuario\UsuarioPermisoEspecial::where('id_usuario', $user->id_usuario)
        ->where('esta_activo', true)
        ->where('fue_borrado', false)
        ->where(function ($query) {
            $query->where('esta_permitido', true)
                ->orWhereNull('esta_permitido');
        })
        ->where('puede_delegar', true);

    if ($idContexto) {
        $specialQuery->where('id_contexto', $idContexto);
    }

    $assignments = $specialQuery->get();
    $specialPerms = collect();
    
    echo "  📊 Permisos especiales delegables: {$assignments->count()}\n";
    
    foreach ($assignments as $assignment) {
        $perm = $assignment->permiso;
        if ($perm && (str_starts_with($perm->slug, 'actividad:') || 
                      str_starts_with($perm->slug, 'curso:') || 
                      str_starts_with($perm->slug, 'cursos'))) {
            $specialPerms->push($perm);
            $ctx = $assignment->id_contexto ? "(Ctx: {$assignment->id_contexto})" : "(Global)";
            echo "      ✓ {$perm->slug} {$ctx}\n";
        }
    }
    
    return $rolePerms->concat($specialPerms)->unique('id_permiso');
}

// Obtener permisos delegables del docente
$delegables = getDelegablePermissionsDebug($docente, $curso->id_contexto);
echo "  📋 TOTAL PERMISOS DELEGABLES: {$delegables->count()}\n\n";

if ($delegables->isEmpty()) {
    echo "❌ PROBLEMA IDENTIFICADO: El docente NO tiene permisos delegables\n";
    echo "   Esto significa que el método getDelegablePermissions devuelve vacío\n\n";
}

// ======================================================================
// PASO 2: Verificar qué pasa en getMemberPermissions
// ======================================================================

echo "🔍 PASO 2: ANÁLISIS DE CARGA - getMemberPermissions\n";
echo "────────────────────────────────────────────────────────────────────\n";

// Verificar si el ayudante es miembro del equipo
$isMember = \App\Models\Usuario\UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
    ->where('id_usuario', $ayudante->id_usuario)
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->exists();

if (!$isMember) {
    echo "❌ PROBLEMA: El ayudante NO es miembro del equipo en el contexto {$curso->id_contexto}\n";
} else {
    echo "✅ El ayudante SÍ es miembro del equipo\n";
}

// Simular la lógica de available_permissions del método
$availablePermissions = $delegables->filter(function ($p) {
    return str_starts_with($p->slug, 'cursos') || 
           str_starts_with($p->slug, 'actividad:') || 
           str_starts_with($p->slug, 'curso:');
});

echo "  📊 Permisos disponibles tras filtrado: {$availablePermissions->count()}\n";

$grouped = $availablePermissions->groupBy(fn() => 'Docencia');
echo "  📊 Permisos agrupados por módulo 'Docencia': {$grouped->count()} grupos\n";

foreach ($grouped as $module => $perms) {
    echo "    - {$module}: {$perms->count()} permisos\n";
}

// ======================================================================
// PASO 3: Verificar restricciones de contexto
// ======================================================================

echo "\n🔍 PASO 3: ANÁLISIS DE CONTEXTO ESPECÍFICO\n";
echo "────────────────────────────────────────────────────────────────────\n";

// Ver si hay permisos especiales del docente EN ESTE CONTEXTO específico
$especialesEnContexto = \App\Models\Usuario\UsuarioPermisoEspecial::where('id_usuario', $docente->id_usuario)
    ->where('id_contexto', $curso->id_contexto)
    ->where('esta_activo', true)
    ->where('fue_borrado', false)
    ->where('puede_delegar', true)
    ->with('permiso')
    ->get();

echo "Permisos especiales del docente en contexto {$curso->id_contexto}: {$especialesEnContexto->count()}\n";
foreach ($especialesEnContexto as $esp) {
    $permitido = $esp->esta_permitido ? 'Permitido' : 'Denegado';
    echo "  ✓ {$esp->permiso->slug} ({$permitido})\n";
}

// Ver permisos especiales del docente GLOBALES (sin contexto específico)
$especialesGlobales = \App\Models\Usuario\UsuarioPermisoEspecial::where('id_usuario', $docente->id_usuario)
    ->whereNull('id_contexto')
    ->where('esta_activo', true)
    ->where('fue_borrado', false)
    ->where('puede_delegar', true)
    ->with('permiso')
    ->get();

echo "Permisos especiales del docente GLOBALES: {$especialesGlobales->count()}\n";
foreach ($especialesGlobales as $esp) {
    $permitido = $esp->esta_permitido ? 'Permitido' : 'Denegado';
    echo "  ✓ {$esp->permiso->slug} ({$permitido})\n";
}

// ======================================================================
// CONCLUSIÓN
// ======================================================================

echo "\n📊 DIAGNÓSTICO FINAL\n";
echo "════════════════════════════════════════════════════════════════════\n";

if ($delegables->isEmpty()) {
    echo "❌ CAUSA DEL PROBLEMA: getDelegablePermissions() devuelve vacío\n";
    echo "\n🔧 POSIBLES SOLUCIONES:\n";
    echo "   1. Los permisos especiales del docente están en contexto 1, no {$curso->id_contexto}\n";
    echo "   2. El método no está considerando permisos de otros contextos\n";
    echo "   3. Los slugs de los permisos no coinciden con los filtros\n";
} else {
    echo "✅ El docente SÍ tiene permisos delegables ({$delegables->count()})\n";
    if ($availablePermissions->isEmpty()) {
        echo "❌ Pero se filtran todos en available_permissions\n";
        echo "   Revisar los filtros por slug en getMemberPermissions\n";
    } else {
        echo "✅ Y pasan el filtro de available_permissions ({$availablePermissions->count()})\n";
        echo "   El problema debe estar en el frontend o en la respuesta JSON\n";
    }
}

echo "\n✅ Debug completado\n";
echo "════════════════════════════════════════════════════════════════════\n";
?>