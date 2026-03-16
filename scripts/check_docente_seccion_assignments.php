<?php

require_once __DIR__ . '/../bootstrap/app.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Docente;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

echo "\n===================================================================================\n";
echo "🔍 ASIGNACIONES DE DOCENTES A SECCIONES Y PERMISOS EN CONTEXTO\n";
echo "===================================================================================\n\n";

$docentes = Docente::with('secciones.curso')->get();

foreach ($docentes as $docente) {
    $usuario_nombre = $docente->usuario->name ?? 'SIN NOMBRE';
    
    echo "📖 DOCENTE ID: {$docente->id_docente} ({$usuario_nombre})\n";
    
    if ($docente->secciones->count() > 0) {
        echo "   ✅ Asignado a " . $docente->secciones->count() . " secciones:\n";
        foreach ($docente->secciones as $seccion) {
            echo "      - Curso: {$seccion->curso->nombre} (ID: {$seccion->id_curso})\n";
        }
    } else {
        echo "   ❌ NO asignado a ninguna sección\n";
    }
    
    // Verificar roles en contextos
    $roles_contextos = DB::table('usuario.usuario_rol_asignacion as ura')
        ->join('usuario.rol', 'ura.id_rol', '=', 'usuario.rol.id_rol')
        ->leftJoin('usuario.contexto', 'ura.id_contexto', '=', 'usuario.contexto.id_contexto')
        ->where('ura.id_usuario', $docente->id_usuario)
        ->where('ura.esta_activo', true)
        ->select(
            'usuario.rol.nombre AS rol_nombre',
            'usuario.contexto.tipo AS contexto_tipo',
            'usuario.contexto.nombre AS contexto_nombre',
            'usuario.contexto.id_contexto'
        )
        ->get();
    
    if ($roles_contextos->count() > 0) {
        echo "   Permisos en contextos:\n";
        foreach ($roles_contextos as $ra) {
            $contexto_info = $ra->contexto_tipo ? "{$ra->contexto_tipo}: {$ra->contexto_nombre}" : "GLOBAL";
            echo "      - Rol: {$ra->rol_nombre} | Contexto: {$contexto_info}\n";
        }
    } else {
        echo "   ⚠️  NO tiene roles asignados en contextos\n";
    }
    
    echo "\n";
}

echo "===================================================================================\n";
printf("Total docentes: %d\n", $docentes->count());
echo "===================================================================================\n\n";
