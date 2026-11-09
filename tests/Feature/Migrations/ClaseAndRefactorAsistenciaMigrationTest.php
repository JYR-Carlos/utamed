<?php

use App\Models\Curso\InscripcionComponente;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(DatabaseTransactions::class);

function migracionClaseYRefactorAsistencia(): object
{
    return require __DIR__ . '/../../../database/migrations/21_create_clase_and_refactor_asistencia.php';
}

test('la migracion 21 crea clase, backfillea asistencias preexistentes y es completamente reversible', function () {
    $migration = migracionClaseYRefactorAsistencia();

    // 1. Obtener dos inscripciones de un mismo componente para simular una sesión de asistencia previa
    $inscripciones = InscripcionComponente::whereHas('componente')
        ->limit(2)
        ->get();

    expect($inscripciones->count())->toBeGreaterThanOrEqual(1);

    $idComponente = $inscripciones[0]->id_componente;
    $idInscripcion1 = $inscripciones[0]->id_inscripcion_componente;
    $idInscripcion2 = $inscripciones->count() > 1 ? $inscripciones[1]->id_inscripcion_componente : $idInscripcion1;

    $dia = '2026-11-05';
    $horaInicio = '08:00:00';
    $horaFin = '09:30:00';

    // Si la migración ya está aplicada en la BD, ejecutamos primero down() para probar el ciclo completo up() y down()
    if (Schema::hasColumn('curso.asistencia', 'id_clase')) {
        $migration->down();
    }

    // Asegurar que las columnas legacy dia/hora existan antes de insertar (por si la tabla ya está en formato previo)
    if (!Schema::hasColumn('curso.asistencia', 'dia')) {
        DB::statement("ALTER TABLE curso.asistencia ADD COLUMN dia date NOT NULL DEFAULT CURRENT_DATE;");
        DB::statement("ALTER TABLE curso.asistencia ADD COLUMN hora_inicio time NOT NULL DEFAULT '08:00:00';");
        DB::statement("ALTER TABLE curso.asistencia ADD COLUMN hora_fin time NOT NULL DEFAULT '09:30:00';");
    }

    // Insertar registros previos simulando el modelo antiguo
    $idAsistencia1 = DB::table('curso.asistencia')->insertGetId([
        'dia' => $dia,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin,
        'esta_presente' => true,
        'id_inscripcion_componente' => $idInscripcion1,
    ], 'id_asistencia');

    // 2. Ejecutar up()
    $migration->up();

    // Verificar que la tabla curso.clase existe
    expect(Schema::hasTable('curso.clase'))->toBeTrue();

    // Verificar que se creó la clase correspondiente al componente y horario
    $clase = DB::table('curso.clase')
        ->where('id_componente', $idComponente)
        ->where('dia', $dia)
        ->where('hora_inicio', $horaInicio)
        ->where('hora_fin', $horaFin)
        ->first();

    expect($clase)->not->toBeNull();
    expect($clase->id_componente)->toBe($idComponente);

    // Verificar que la fila de asistencia ahora tiene id_clase apuntando a la clase creada
    $asistenciaActualizada = DB::table('curso.asistencia')
        ->where('id_asistencia', $idAsistencia1)
        ->first();

    expect($asistenciaActualizada)->not->toBeNull();
    expect($asistenciaActualizada->id_clase)->toBe($clase->id_clase);

    // Verificar que dia, hora_inicio, hora_fin ya NO existen en curso.asistencia
    expect(Schema::hasColumn('curso.asistencia', 'dia'))->toBeFalse();
    expect(Schema::hasColumn('curso.asistencia', 'hora_inicio'))->toBeFalse();
    expect(Schema::hasColumn('curso.asistencia', 'hora_fin'))->toBeFalse();

    // 3. Ejecutar down() y verificar reversibilidad
    $migration->down();

    // Verificar que curso.clase ya no existe
    expect(Schema::hasTable('curso.clase'))->toBeFalse();

    // Verificar que las columnas dia, hora_inicio, hora_fin fueron restauradas en curso.asistencia
    expect(Schema::hasColumn('curso.asistencia', 'dia'))->toBeTrue();
    expect(Schema::hasColumn('curso.asistencia', 'hora_inicio'))->toBeTrue();
    expect(Schema::hasColumn('curso.asistencia', 'hora_fin'))->toBeTrue();
    expect(Schema::hasColumn('curso.asistencia', 'id_clase'))->toBeFalse();

    // Verificar que los datos históricos se restauraron fielmente
    $asistenciaRevertida = DB::table('curso.asistencia')
        ->where('id_asistencia', $idAsistencia1)
        ->first();

    expect($asistenciaRevertida)->not->toBeNull();
    expect($asistenciaRevertida->dia)->toBe($dia);
    expect(substr((string) $asistenciaRevertida->hora_inicio, 0, 5))->toBe(substr($horaInicio, 0, 5));
    expect(substr((string) $asistenciaRevertida->hora_fin, 0, 5))->toBe(substr($horaFin, 0, 5));
});
