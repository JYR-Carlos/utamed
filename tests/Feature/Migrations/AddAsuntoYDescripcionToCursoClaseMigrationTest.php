<?php

use App\Models\Curso\Componente;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

function migracionAddAsuntoYDescripcion(): object
{
    return require __DIR__ . '/../../../database/migrations/22_add_asunto_y_descripcion_to_curso_clase.php';
}

test('la migracion 22 agrega asunto y descripcion a curso.clase y es completamente reversible', function () {
    $migration = migracionAddAsuntoYDescripcion();

    // 1. Asegurar que up() agrega las columnas
    $migration->up();

    expect(Schema::hasColumn('curso.clase', 'asunto'))->toBeTrue();
    expect(Schema::hasColumn('curso.clase', 'descripcion'))->toBeTrue();

    // 2. Verificar que se puede insertar un registro con asunto y descripción
    $componente = Componente::first();
    expect($componente)->not->toBeNull();

    $idClase = DB::table('curso.clase')->insertGetId([
        'id_componente' => $componente->id_componente,
        'dia'           => '2026-11-20',
        'hora_inicio'   => '16:00:00',
        'hora_fin'      => '17:30:00',
        'asunto'        => 'Introducción a la unidad clínica',
        'descripcion'   => 'Revisión de casos prácticos y anamnesis.',
    ], 'id_clase');

    $fila = DB::table('curso.clase')->where('id_clase', $idClase)->first();
    expect($fila)->not->toBeNull();
    expect($fila->asunto)->toBe('Introducción a la unidad clínica');
    expect($fila->descripcion)->toBe('Revisión de casos prácticos y anamnesis.');

    // 3. Ejecutar down() y verificar que las columnas se eliminan
    $migration->down();

    expect(Schema::hasColumn('curso.clase', 'asunto'))->toBeFalse();
    expect(Schema::hasColumn('curso.clase', 'descripcion'))->toBeFalse();

    // 4. Restaurar up() para dejar la BD en estado consistente
    $migration->up();

    expect(Schema::hasColumn('curso.clase', 'asunto'))->toBeTrue();
    expect(Schema::hasColumn('curso.clase', 'descripcion'))->toBeTrue();
});
