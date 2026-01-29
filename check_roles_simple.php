// Simple role check
$userId = 5; // Change this to the user ID you're testing

echo "Checking roles for user ID: $userId\n\n";

// Check raw table
$records = DB::select('SELECT * FROM utamed."Usuario_Rol_Asignacion" WHERE id_usuario_recipiente = ?', [$userId]);

echo "Total records found: " . count($records) . "\n\n";

if (count($records) > 0) {
foreach ($records as $record) {
echo "Rol ID: {$record->id_rol}\n";
echo "Contexto ID: {$record->id_contexto}\n";
echo "Activo: " . ($record->esta_activo ? 'true' : 'false') . "\n";
echo "Eliminado: " . ($record->fue_eliminado ? 'true' : 'false') . "\n";
echo "Fecha creación: {$record->fecha_creacion}\n";
echo "---\n";
}
} else {
echo "⚠️ NO SE ENCONTRARON REGISTROS\n";
echo "\nPosibles causas:\n";
echo "1. Los roles no se están guardando\n";
echo "2. El ID de usuario es incorrecto\n";
echo "3. Los registros fueron eliminados\n";
}