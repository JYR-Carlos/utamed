<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDocentePermissions extends Command
{
    protected $signature = 'verify:docente-perms';
    protected $description = 'Verifica el estado actual de permisos del rol Docente en la BD';

    public function handle()
    {
        $this->line('');
        $this->line(str_repeat('=', 80));
        $this->line('🔍 VERIFICACIÓN DE PERMISOS DEL ROL DOCENTE');
        $this->line(str_repeat('=', 80));
        $this->line('');

        // 1. Resumen
        $this->line('1️⃣  RESUMEN DE PERMISOS ASIGNADOS');
        $this->line(str_repeat('-', 80));

        $result = DB::table('usuario.rol as r')
            ->leftJoin('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
            ->leftJoin('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
            ->where('r.nombre', 'Docente')
            ->selectRaw('r.nombre as rol, COUNT(p.id_permiso) as cantidad_permisos_asignados')
            ->groupBy('r.nombre')
            ->first();

        if (!$result) {
            $this->error('❌ ERROR: No se encontró el rol "Docente" en la BD');
            return 1;
        }

        $permisosAsignados = $result->cantidad_permisos_asignados;
        $this->line("Rol: Docente");
        $this->line("Total de permisos asignados: $permisosAsignados");
        $this->line("Total ESPERADOS según roles_config.php: 42");
        
        if ($permisosAsignados == 42) {
            $this->info("✅ Cantidad CORRECTA");
        } else {
            $this->warn("❌ Cantidad INCORRECTA (faltan " . (42 - $permisosAsignados) . " permisos)");
        }

        // 2. Listar todos los permisos
        $this->line('');
        $this->line('2️⃣  LISTADO COMPLETO DE PERMISOS');
        $this->line(str_repeat('-', 80));

        $permisos = DB::table('usuario.rol as r')
            ->join('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
            ->join('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
            ->where('r.nombre', 'Docente')
            ->orderBy('p.slug')
            ->select('p.id_permiso', 'p.slug', 'p.descripcion')
            ->get();

        foreach ($permisos as $i => $perm) {
            $num = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $this->line(sprintf("%s. %-50s | %s", $num, $perm->slug, $perm->descripcion ?? ''));
        }

        // 3. Permisos de Programas
        $this->line('');
        $this->line('3️⃣  PERMISOS DE PROGRAMAS');
        $this->line(str_repeat('-', 80));

        $permisosPrograma = DB::table('usuario.rol as r')
            ->join('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
            ->join('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
            ->where('r.nombre', 'Docente')
            ->where('p.slug', 'like', '%programa%')
            ->orderBy('p.slug')
            ->select('p.slug', 'p.descripcion')
            ->get();

        if (count($permisosPrograma) == 0) {
            $this->warn("❌ NO TIENE NINGÚN PERMISO DE PROGRAMAS");
        } else {
            foreach ($permisosPrograma as $perm) {
                $this->line("✅ {$perm->slug}");
                $this->line("   → {$perm->descripcion}");
            }
        }

        // 4. Verificación específica
        $this->line('');
        $this->line('4️⃣  VERIFICACIÓN: ¿Puede ver programas?');
        $this->line(str_repeat('-', 80));

        $tienePermisoVer = DB::table('usuario.rol as r')
            ->join('usuario.asignacion_rol_permiso as arp', 'r.id_rol', '=', 'arp.id_rol')
            ->join('usuario.permiso as p', 'arp.id_permiso', '=', 'p.id_permiso')
            ->where('r.nombre', 'Docente')
            ->where('p.slug', 'cursos/programas:ver')
            ->exists();

        if ($tienePermisoVer) {
            $this->info("✅ SÍ - El docente PUEDE ver programas");
            $this->line("   Permiso: cursos/programas:ver");
        } else {
            $this->warn("❌ NO - El docente NO PUEDE ver programas");
            $this->line("   Permitido el acceso al programa en BD: NO");
        }

        // 5. Comparativa
        $this->line('');
        $this->line('5️⃣  COMPARATIVA: ESPERADO vs ACTUAL');
        $this->line(str_repeat('-', 80));

        $expectedPerms = [
            'cursos:ver', 'cursos:editar', 'cursos:eliminar',
            'cursos/inscripciones:ver', 'cursos/inscripciones:inscribir_alumnos',
            'cursos/inscripciones:eliminar_inscripciones',
            'cursos/secciones:ver', 'cursos/secciones:crear', 'cursos/secciones:crear_plantilla',
            'cursos/secciones:editar', 'cursos/secciones:eliminar',
            'cursos/unidades:ver', 'cursos/unidades:crear', 'cursos/unidades:crear_plantilla',
            'cursos/unidades:editar', 'cursos/unidades:eliminar',
            'cursos/actividades:ver', 'cursos/actividades:crear', 'cursos/actividades:crear_plantilla',
            'cursos/actividades:editar', 'cursos/actividades:eliminar', 'cursos/actividades:evaluar',
            'cursos/actividades:dar_feedback', 'cursos/actividades:descargar_entregas',
            'cursos/actividades:enviar_recordatorios', 'cursos/actividades:subir_entregas',
            'cursos/actividades/grupos:ver', 'cursos/actividades/grupos:crear',
            'cursos/actividades/grupos:editar', 'cursos/actividades/grupos:eliminar',
            'cursos/programas:ver', 'cursos/programas:agregar', 'cursos/programas:eliminar',
            'cursos/programas/modificar:modulo_1', 'cursos/programas/modificar:modulo_2',
            'cursos/programas/modificar:modulo_3', 'cursos/programas/modificar:modulo_4',
            'cursos/programas/modificar:modulo_5', 'cursos/programas/modificar:modulo_6',
            'cursos/programas/modificar:modulo_7', 'cursos/programas/modificar:modulo_8',
            'cursos/programas/modificar:modulo_9',
        ];

        $actualPerms = $permisos->pluck('slug')->toArray();
        $faltantes = array_diff($expectedPerms, $actualPerms);
        $sobrantes = array_diff($actualPerms, $expectedPerms);

        $this->line("PERMISOS ESPERADOS (roles_config.php): " . count($expectedPerms));
        $this->line("PERMISOS ACTUALES (BD): " . count($actualPerms));
        $this->line('');

        if (count($faltantes) > 0) {
            $this->warn("❌ PERMISOS QUE FALTAN (" . count($faltantes) . "):");
            foreach ($faltantes as $perm) {
                $this->line("   - $perm");
            }
        } else {
            $this->info("✅ Todos los permisos esperados están en la BD");
        }

        if (count($sobrantes) > 0) {
            $this->line('');
            $this->warn("⚠️  PERMISOS ADICIONALES (" . count($sobrantes) . "):");
            foreach ($sobrantes as $perm) {
                $this->line("   - $perm");
            }
        } else {
            $this->info("✅ No hay permisos adicionales");
        }

        // Conclusión
        $this->line('');
        $this->line(str_repeat('=', 80));
        $this->line('📋 CONCLUSIÓN');
        $this->line(str_repeat('=', 80));

        if (count($faltantes) == 0 && $tienePermisoVer) {
            $this->info("✅ Estado: BD CORRECTA");
            $this->line('');
            $this->line("El docente PUEDE acceder a los programas porque:");
            $this->line("  1. Tiene el permiso 'cursos/programas:ver' en la BD ✅");
            $this->line("  2. Tiene todos los 42 permisos configurados ✅");
            $this->line('');
            $this->info("RESPUESTA A TU PREGUNTA:");
            $this->info("    → SÍ, actualmente el docente PUEDE acceder a programas");
        } else {
            $this->warn("❌ Estado: BD INCOMPLETA");
            $this->line('');
            $this->line("El docente NO PUEDE acceder a los programas porque:");
            
            if (!$tienePermisoVer) {
                $this->line("  • Falta 'cursos/programas:ver' ❌");
            }
            
            if (count($faltantes) > 0) {
                $this->line("  • Faltan " . count($faltantes) . " permisos en total ❌");
            }
            
            $this->line('');
            $this->warn("SOLUCIÓN:");
            $this->line("    1. Ejecutar: php scripts/generate_permissions_sql.php");
            $this->line("    2. Ejecutar el SQL generado en BD");
            $this->line("    3. Los 42 permisos se actualizarán:");
        }

        $this->line('');
        $this->line(str_repeat('=', 80));
        $this->line('');

        return 0;
    }
}
