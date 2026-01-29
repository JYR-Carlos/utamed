// Simple role check WITH ACCENT
$userId = 5;

echo "Checking roles for user ID: $userId (Table: Usuario_Rol_Asignación)\n\n";

try {
$records = DB::select('SELECT * FROM utamed."Usuario_Rol_Asignación" WHERE id_usuario_recipiente = ?', [$userId]);

echo "Total records found: " . count($records) . "\n\n";

foreach ($records as $record) {
echo "Rol ID: {$record->id_rol}, Contexto: {$record->id_contexto}, Activo: " . ($record->esta_activo ? 'YES' : 'NO') .
"\n";
}
} catch (\Exception $e) {
echo "ERROR: " . $e->getMessage() . "\n";
}