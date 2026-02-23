<?php
/**
 * Script para debugmar permisos de un usuario
 * Ejecutar: php scripts/debug_user_permissions.php
 */

// Bootstrap Laravel
require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\Usuario\Usuario;
use App\Models\Usuario\Docente;
use Illuminate\Support\Facades\DB;

$userId = 4;

echo "=".str_repeat("=", 80)."\n";
echo "DEBUG: Permisos y Roles del Usuario ID: {$userId}\n";
echo "=".str_repeat("=", 80)."\n\n";

// 1. Obtener usuario
$user = Usuario::find($userId);
if (!$user) {
    echo "❌ Usuario no encontrado\n";
    exit(1);
}

echo "📋 INFORMACIÓN DEL USUARIO\n";
echo "-".str_repeat("-", 80)."\n";
echo "  ID: {$user->id_usuario}\n";
echo "  Nombre: {$user->nombre}\n";
echo "  Email: {$user->email}\n";
echo "  Es Admin: " . ($user->is_admin ? "✅ SÍ" : "❌ NO") . "\n";

// 2. Verificar si es docente
$docente = $user->docente;
echo "\n👨‍🏫 INFORMACIÓN DE DOCENTE\n";
echo "-".str_repeat("-", 80)."\n";
if ($docente) {
    echo "  ✅ Es DOCENTE\n";
    echo "  ID Docente: {$docente->id_docente}\n";
    
    // Obtener secciones asignadas
    $secciones = $docente->secciones()
        ->with('curso')
        ->get();
    
    echo "  Secciones Asignadas: {$secciones->count()}\n";
    
    if ($secciones->count() > 0) {
        echo "\n  📚 Secciones Detalle:\n";
        foreach ($secciones as $seccion) {
            echo "     - Sección ID: {$seccion->id_seccion}\n";
            echo "       Curso ID: {$seccion->id_curso}\n";
            if ($seccion->curso) {
                echo "       Curso Nombre: {$seccion->curso->nombre}\n";
            }
        }
    } else {
        echo "  ⚠️  El docente NO tiene secciones asignadas\n";
    }
} else {
    echo "  ❌ NO es DOCENTE\n";
}

// 3. Roles activos
echo "\n🎭 ROLES ACTIVOS\n";
echo "-".str_repeat("-", 80)."\n";
$rolesActivos = $user->rolesAsignados()
    ->where('esta_activo', true)
    ->where('fue_eliminado', false)
    ->get();

if ($rolesActivos->count() > 0) {
    echo "  Cantidad de roles activos: {$rolesActivos->count()}\n";
    foreach ($rolesActivos as $rol) {
        echo "  ✅ {$rol->nombre}\n";
    }
} else {
    echo "  ❌ El usuario NO tiene roles activos asignados\n";
}

// 4. Permisos especiales
echo "\n🔐 PERMISOS ESPECIALES\n";
echo "-".str_repeat("-", 80)."\n";

$permisos = DB::table('vw_permisos_usuario')
    ->where('id_usuario', $userId)
    ->get();

if ($permisos->count() > 0) {
    echo "  Total de permisos: {$permisos->count()}\n";
    
    // Agrupar por contexto
    $porContexto = $permisos->groupBy('id_contexto');
    
    foreach ($porContexto as $contextoId => $permisosContexto) {
        echo "\n  📍 Contexto ID: {$contextoId}\n";
        
        $permitidos = $permisosContexto->where('esta_permitido', 1)->count();
        $denegados = $permisosContexto->where('esta_permitido', 0)->count();
        
        echo "     Permitidos: {$permitidos} | Denegados: {$denegados}\n";
        
        // Mostrar permisos relevantes para programa
        $programaPermisos = $permisosContexto->filter(fn($p) => str_contains($p->slug ?? '', 'programa'));
        
        if ($programaPermisos->count() > 0) {
            echo "     📋 Permisos de PROGRAMA:\n";
            foreach ($programaPermisos as $permiso) {
                $estado = $permiso->esta_permitido ? "✅" : "❌";
                echo "        {$estado} {$permiso->slug}\n";
            }
        }
        
        // Mostrar permisos wildcard
        $wildcardPermisos = $permisosContexto->filter(fn($p) => ($p->slug ?? '') === '*');
        if ($wildcardPermisos->count() > 0) {
            foreach ($wildcardPermisos as $permiso) {
                $estado = $permiso->esta_permitido ? "✅" : "❌";
                echo "     {$estado} Permiso WILDCARD (*) - Todos los permisos\n";
            }
        }
    }
} else {
    echo "  ⚠️  El usuario NO tiene permisos especiales asignados\n";
}

// 5. Verificar acceso a Curso 1
echo "\n\n🎯 VERIFICACIÓN DE ACCESO A CURSO 1\n";
echo "-".str_repeat("-", 80)."\n";

$curso = DB::table('curso')
    ->where('id_curso', 1)
    ->where('fecha_eliminacion', null)
    ->first();

if ($curso) {
    echo "  Curso encontrado: {$curso->nombre} (ID: {$curso->id_curso})\n";
    echo "  Contexto del curso: {$curso->id_contexto}\n";
    
    // Verificar si el docente tiene secciones en este curso
    if ($docente) {
        $seccionesEnCurso = $docente->secciones()
            ->where('id_curso', $curso->id_curso)
            ->count();
        
        echo "  Secciones del docente en este curso: {$seccionesEnCurso}\n";
        
        if ($seccionesEnCurso > 0) {
            echo "  ✅ El docente ESTÁ asignado a este curso\n";
        } else {
            echo "  ❌ El docente NO ESTÁ asignado a este curso\n";
        }
    }
    
    // Verificar permisos específicos del curso
    $permisosCurso = DB::table('vw_permisos_usuario')
        ->where('id_usuario', $userId)
        ->where('id_contexto', $curso->id_contexto)
        ->where('slug', 'like', '%programa%')
        ->get();
    
    if ($permisosCurso->count() > 0) {
        echo "\n  Permisos de PROGRAMA en este contexto:\n";
        foreach ($permisosCurso as $p) {
            $estado = $p->esta_permitido ? "✅" : "❌";
            echo "     {$estado} {$p->slug}\n";
        }
    }
} else {
    echo "  ❌ Curso no encontrado\n";
}

// 6. Resumen de requisitos
echo "\n\n✅ RESUMEN DE REQUISITOS PARA CREAR PROGRAMA\n";
echo "-".str_repeat("-", 80)."\n";

$requisitos = [
    "Es Administrador" => $user->is_admin,
    "Es Docente" => $docente !== null,
    "Tiene secciones asignadas" => $docente && $docente->secciones()->count() > 0,
    "Tiene permiso 'curso/programa:crear'" => $permisos
        ->filter(fn($p) => str_contains($p->slug ?? '', 'programa') && str_contains($p->slug ?? '', 'crear'))
        ->count() > 0,
];

foreach ($requisitos as $requisito => $cumplido) {
    $estado = $cumplido ? "✅" : "❌";
    echo "  {$estado} {$requisito}\n";
}

echo "\n" . "=".str_repeat("=", 80) . "\n";
echo "Fin del Debug\n";
echo "=".str_repeat("=", 80) . "\n";
