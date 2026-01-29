// Diagnóstico de Asignación de Roles
// Ejecutar con: php artisan tinker < check_roles.php use App\Models\Usuario\Usuario; use
    App\Models\Usuario\UsuarioRolAsignación; use App\Models\Usuario\Contexto; echo "=== DIAGNÓSTICO DE ROLES ===\n\n" ;
    // Obtener el usuario ID 5 (o el que el usuario esté probando) $usuario=Usuario::find(5); if (!$usuario) {
    echo "Usuario ID 5 no encontrado\n" ; exit; } echo "Usuario: {$usuario->username} (ID: {$usuario->id_usuario})\n" ;
    echo "Nombre: {$usuario->nombre1} {$usuario->apellido1}\n\n" ; // Verificar contextos disponibles
    echo "--- CONTEXTOS DISPONIBLES ---\n" ; $contextos=Contexto::all(); foreach ($contextos as $ctx) {
    echo "ID: {$ctx->id_contexto}, Nombre: {$ctx->nombre}\n" ; } echo "\n" ; // Verificar asignaciones de roles
    echo "--- ASIGNACIONES DE ROLES ---\n" ; $asignaciones=UsuarioRolAsignación::where('id_usuario_recipiente',
    $usuario->id_usuario)->get();

    if ($asignaciones->isEmpty()) {
    echo "⚠️ NO HAY ASIGNACIONES DE ROLES para este usuario\n";
    } else {
    echo "Total de asignaciones: {$asignaciones->count()}\n\n";
    foreach ($asignaciones as $asig) {
    $contexto = Contexto::find($asig->id_contexto);
    $rol = \App\Models\Usuario\Rol::find($asig->id_rol);

    echo "Rol: " . ($rol ? $rol->nombre : "ID {$asig->id_rol}") . "\n";
    echo " Contexto: " . ($contexto ? $contexto->nombre : "ID {$asig->id_contexto}") . "\n";
    echo " Activo: " . ($asig->esta_activo ? 'Sí' : 'No') . "\n";
    echo " Eliminado: " . ($asig->fue_eliminado ? 'Sí' : 'No') . "\n";
    echo " Fecha creación: {$asig->fecha_creacion}\n";
    if ($asig->fecha_fin_real) {
    echo " Fecha fin: {$asig->fecha_fin_real}\n";
    }
    echo "\n";
    }
    }

    // Verificar tabla directamente
    echo "--- VERIFICACIÓN DIRECTA EN BD ---\n";
    $raw = DB::select("SELECT * FROM utamed.\"Usuario_Rol_Asignacion\" WHERE id_usuario_recipiente = ?",
    [$usuario->id_usuario]);
    echo "Registros en BD: " . count($raw) . "\n";
    if (count($raw) > 0) {
    echo "Primer registro:\n";
    print_r($raw[0]);
    }

    echo "\n=== FIN DEL DIAGNÓSTICO ===\n";