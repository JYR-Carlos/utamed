<?php
/**
 * Debug script para diagnosticar problemas de carga de permisos en PermissionsModal
 * Ejecución: php artisan tinker < scripts/debug_permissions_modal.php
 */

require 'bootstrap/app.php';
require 'vendor/autoload.php';

use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\Contexto;

// Parámetros editables
$idCurso = 1; // ← CAMBIA ESTO AL ID DEL CURSO
$idUsuarioDocente = 1; // ← CAMBIA ESTO AL ID DEL DOCENTE (admin o teacher)
$idUsuarioAyudante = null; // ← CAMBIA ESTO AL ID DEL AYUDANTE A VER (si es null, muestra el primero)

echo "═══════════════════════════════════════════════════════════\n";
echo "DEBUG: Carga de Permisos en PermissionsModal\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Verificar que el curso existe
$curso = Curso::find($idCurso);
if (!$curso) {
    echo "❌ CURSO NO ENCONTRADO: ID $idCurso\n";
    exit(1);
}
echo "✅ Curso encontrado: {$curso->cod_curso} - {$curso->nombre}\n";
echo "   ID Contexto: {$curso->id_contexto}\n\n";

// 2. Verificar que el contexto existe
if (!$curso->id_contexto) {
    echo "❌ ERROR: El curso no tiene un contexto asignado\n";
    exit(1);
}
$contexto = Contexto::find($curso->id_contexto);
if (!$contexto) {
    echo "❌ ERROR: El contexto ID {$curso->id_contexto} no existe en BD\n";
    exit(1);
}
echo "✅ Contexto existe: {$contexto->contexto_display}\n\n";

// 3. Obtener miembros del equipo en este contexto
echo "📋 MIEMBROS DEL EQUIPO EN ESTE CONTEXTO:\n";
$members = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->with(['rol', 'usuario'])
    ->get();

if ($members->isEmpty()) {
    echo "❌ No hay miembros activos en este contexto\n\n";
    exit(1);
}

foreach ($members as $i => $member) {
    echo "  {$i}) ID Usuario: {$member->id_usuario}, Rol: {$member->rol->nombre}\n";
}
echo "\n";

// 4. Si no se especificó ayudante, usar el primero
if (!$idUsuarioAyudante) {
    $idUsuarioAyudante = $members->first()->id_usuario;
    echo "ℹ️  Usando primer miembro: ID {$idUsuarioAyudante}\n\n";
}

// 5. Verificar que el usuario ayudante es miembro
$auxiliarAsignment = UsuarioRolAsignacion::where('id_contexto', $curso->id_contexto)
    ->where('id_usuario', $idUsuarioAyudante)
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->first();

if (!$auxiliarAsignment) {
    echo "❌ ERROR: Usuario $idUsuarioAyudante NO es miembro del equipo\n";
    exit(1);
}
echo "✅ Usuario $idUsuarioAyudante es miembro (Rol: {$auxiliarAsignment->rol->nombre})\n\n";

// 6. Simular la llamada a getMemberPermissions
echo "═══════════════════════════════════════════════════════════\n";
echo "SIMULATING: CourseTeamController::getMemberPermissions()\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$usuario = Usuario::find($idUsuarioAyudante);
$roles = $usuario->rolesAsignados()
    ->where('id_contexto', $curso->id_contexto)
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->pluck('id_rol');

echo "Roles asignados al usuario:\n";
echo json_encode($roles->toArray(), JSON_PRETTY_PRINT) . "\n\n";

$special = $usuario->permisosEspeciales()
    ->where('id_contexto', $curso->id_contexto)
    ->where('esta_activo', true)
    ->where('fue_borrado', false)
    ->get(['id_permiso', 'esta_permitido', 'puede_delegar']);

echo "Permisos especiales:\n";
echo json_encode($special->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 7. Verificar available_roles
$availableRoles = \App\Models\Usuario\Rol::whereIn('nombre', ['Ayudante', 'Estudiante'])
    ->orderBy('nombre')
    ->get();

echo "Roles disponibles para asignar:\n";
echo json_encode($availableRoles->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "═══════════════════════════════════════════════════════════\n";
echo "✅ Si ves datos arriba, el backend está devolviendo correctamente\n";
echo "❌ Si ves datos vacíos, revisa las tablas de la BD\n";
echo "═══════════════════════════════════════════════════════════\n";
