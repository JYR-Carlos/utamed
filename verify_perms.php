#!/usr/bin/env php
<?php

/**
 * Script para verificar permisos del rol Docente - Versión CLI mejorada
 */

use Illuminate\Support\Facades\DB;

try {
    require __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->handle($input = new \Symfony\Component\Console\Input\ArgvInput, 
        $output = new \Symfony\Component\Console\Output\ConsoleOutput);

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "🔍 VERIFICACIÓN DE PERMISOS DEL ROL DOCENTE\n";
    echo str_repeat("=", 80) . "\n\n";

    // ============================================================================
    // 1. Contar total de permisos asignados al rol Docente
    // ============================================================================
    echo "1️⃣  RESUMEN DE PERMISOS ASIGNADOS\n";
    echo str_repeat("-", 80) . "\n";

    $result = DB::connection('pgsql')
        ->table('usuario.rol as r')
        ->leftJoin('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
        ->leftJoin('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
        ->where('r.nombre', 'Docente')
        ->select(DB::raw('r.nombre as rol, COUNT(p.id_permiso) as cantidad_permisos_asignados'))
        ->groupBy('r.nombre')
        ->first();

    if (!$result) {
        echo "❌ ERROR: No se encontró el rol 'Docente' en la BD\n";
        exit(1);
    }

    $permisosAsignados = $result->cantidad_permisos_asignados;
    echo "Rol: Docente\n";
    echo "Total de permisos asignados: $permisosAsignados\n";
    echo "Total ESPERADOS según roles_config.php: 42\n";

    if ($permisosAsignados == 42) {
        echo "✅ Cantidad CORRECTA\n";
    } else {
        echo "❌ Cantidad INCORRECTA (faltan " . (42 - $permisosAsignados) . " permisos)\n";
    }

    // ============================================================================
    // 2. Listar TODOS los permisos del rol Docente
    // ============================================================================
    echo "\n2️⃣  LISTADO COMPLETO DE PERMISOS\n";
    echo str_repeat("-", 80) . "\n";

    $permisos = DB::connection('pgsql')
        ->table('usuario.rol as r')
        ->join('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
        ->join('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
        ->where('r.nombre', 'Docente')
        ->orderBy('p.slug')
        ->select('p.id_permiso', 'p.slug', 'p.descripcion')
        ->get();

    foreach ($permisos as $i => $perm) {
        echo sprintf("%2d. %-50s | %s\n", $i + 1, $perm->slug, $perm->descripcion);
    }

    // ============================================================================
    // 3. Verificar específicamente los permisos de PROGRAMAS
    // ============================================================================
    echo "\n3️⃣  PERMISOS DE PROGRAMAS\n";
    echo str_repeat("-", 80) . "\n";

    $permisosPrograma = DB::connection('pgsql')
        ->table('usuario.rol as r')
        ->join('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
        ->join('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
        ->where('r.nombre', 'Docente')
        ->where('p.slug', 'like', '%programa%')
        ->orderBy('p.slug')
        ->select('p.slug', 'p.descripcion')
        ->get();

    if (count($permisosPrograma) == 0) {
        echo "❌ NO TIENE NINGÚN PERMISO DE PROGRAMAS\n";
    } else {
        foreach ($permisosPrograma as $perm) {
            echo "✅ {$perm->slug}\n";
            echo "   → {$perm->descripcion}\n";
        }
    }

    // ============================================================================
    // 4. Verificar si tiene "cursos/programas:ver" específicamente
    // ============================================================================
    echo "\n4️⃣  VERIFICACIÓN: ¿Puede ver programas?\n";
    echo str_repeat("-", 80) . "\n";

    $tienePermisoVer = DB::connection('pgsql')
        ->table('usuario.rol as r')
        ->join('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
        ->join('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
        ->where('r.nombre', 'Docente')
        ->where('p.slug', 'cursos/programas:ver')
        ->exists();

    if ($tienePermisoVer) {
        echo "✅ SÍ - El docente PUEDE ver programas\n";
        echo "   Permiso: cursos/programas:ver\n";
    } else {
        echo "❌ NO - El docente NO PUEDE ver programas\n";
        echo "   Permitido el acceso al programa en BD: NO\n";
    }

    // ============================================================================
    // 5. Comparar: Qué permisos DEBERÍA tener vs tiene
    // ============================================================================
    echo "\n5️⃣  COMPARATIVA: ESPERADO vs ACTUAL\n";
    echo str_repeat("-", 80) . "\n";

    $expectedPerms = [
        'cursos:ver',
        'cursos:editar',
        'cursos:eliminar',
        'cursos/inscripciones:ver',
        'cursos/inscripciones:inscribir_alumnos',
        'cursos/inscripciones:eliminar_inscripciones',
        'cursos/secciones:ver',
        'cursos/secciones:crear',
        'cursos/secciones:crear_plantilla',
        'cursos/secciones:editar',
        'cursos/secciones:eliminar',
        'cursos/unidades:ver',
        'cursos/unidades:crear',
        'cursos/unidades:crear_plantilla',
        'cursos/unidades:editar',
        'cursos/unidades:eliminar',
        'cursos/actividades:ver',
        'cursos/actividades:crear',
        'cursos/actividades:crear_plantilla',
        'cursos/actividades:editar',
        'cursos/actividades:eliminar',
        'cursos/actividades:evaluar',
        'cursos/actividades:dar_feedback',
        'cursos/actividades:descargar_entregas',
        'cursos/actividades:enviar_recordatorios',
        'cursos/actividades:subir_entregas',
        'cursos/actividades/grupos:ver',
        'cursos/actividades/grupos:crear',
        'cursos/actividades/grupos:editar',
        'cursos/actividades/grupos:eliminar',
        'cursos/programas:ver',
        'cursos/programas:agregar',
        'cursos/programas:eliminar',
        'cursos/programas/modificar:modulo_1',
        'cursos/programas/modificar:modulo_2',
        'cursos/programas/modificar:modulo_3',
        'cursos/programas/modificar:modulo_4',
        'cursos/programas/modificar:modulo_5',
        'cursos/programas/modificar:modulo_6',
        'cursos/programas/modificar:modulo_7',
        'cursos/programas/modificar:modulo_8',
        'cursos/programas/modificar:modulo_9',
    ];

    $actualPerms = $permisos->pluck('slug')->toArray();
    $faltantes = array_diff($expectedPerms, $actualPerms);
    $sobrantes = array_diff($actualPerms, $expectedPerms);

    echo "PERMISOS ESPERADOS (roles_config.php): " . count($expectedPerms) . "\n";
    echo "PERMISOS ACTUALES (BD): " . count($actualPerms) . "\n\n";

    if (count($faltantes) > 0) {
        echo "❌ PERMISOS QUE FALTAN (" . count($faltantes) . "):\n";
        foreach ($faltantes as $perm) {
            echo "   - $perm\n";
        }
    } else {
        echo "✅ Todos los permisos esperados están en la BD\n";
    }

    if (count($sobrantes) > 0) {
        echo "\n⚠️  PERMISOS ADICIONALES (" . count($sobrantes) . "):\n";
        foreach ($sobrantes as $perm) {
            echo "   - $perm\n";
        }
    } else {
        echo "✅ No hay permisos adicionales\n";
    }

    // ============================================================================
    // CONCLUSIÓN
    // ============================================================================
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "📋 CONCLUSIÓN\n";
    echo str_repeat("=", 80) . "\n";

    if (count($faltantes) == 0 && $tienePermisoVer) {
        echo "✅ Estado: BD CORRECTA\n\n";
        echo "El docente PUEDE acceder a los programas porque:\n";
        echo "  1. Tiene el permiso 'cursos/programas:ver' en la BD ✅\n";
        echo "  2. Tiene todos los 42 permisos configurados ✅\n";
        echo "\nRESPUESTA A TU PREGUNTA:\n";
        echo "    → SÍ, actualmente el docente PUEDE acceder a programas\n\n";
    } else {
        echo "❌ Estado: BD INCOMPLETA\n\n";
        echo "El docente NO PUEDE acceder a los programas porque:\n";
        
        if (!$tienePermisoVer) {
            echo "  • Falta 'cursos/programas:ver' ❌\n";
        }
        
        if (count($faltantes) > 0) {
            echo "  • Faltan " . count($faltantes) . " permisos en total ❌\n";
        }
        
        echo "\nSOLUCIÓN:\n";
        echo "    1. Ejecutar: php scripts/generate_permissions_sql.php\n";
        echo "    2. Ejecutar el SQL generado en: database-model/init_scripts/03-inserts/11-roles-config.sql\n";
        echo "    3. La BD se actualizará con los 42 permisos del docente\n\n";
    }

    echo str_repeat("=", 80) . "\n\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
