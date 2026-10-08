<?php

/**
 * Migración 10 (reparar contexto del docente titular).
 *
 * La BD de tests ya tiene la migración aplicada, así que cada caso fabrica
 * dentro de la transacción del test los contextos huérfanos «Curso: <cod>» y
 * las asignaciones del titular, corre up() y revisa el resultado. Todo se
 * revierte al terminar.
 */

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

function migracionRepararContextoTitular(): object
{
    return require base_path('database/migrations/10_reparar_contexto_docente_titular_cursos.php');
}

/**
 * Un curso con titular y sin contextos «Curso: <cod>» previos, con su contexto
 * real, el usuario del titular, el rol «Docente Titular» y un usuario que firma
 * las asignaciones. Además deja al titular sin asignación activa en el
 * contexto real, para que cada caso parta de un estado conocido.
 */
function fixtureCursoTitularSinContextoHuerfano(): ?array
{
    $row = DB::selectOne("
        SELECT c.id_curso, c.cod_curso, c.id_contexto AS id_contexto_real,
               cx.id_tipo_contexto, d.id_usuario,
               (SELECT id_rol FROM usuario.rol WHERE nombre = 'Docente Titular') AS id_rol
        FROM curso.curso c
        JOIN usuario.docente d ON d.id_docente = c.id_docente_titular
        JOIN usuario.contexto cx ON cx.id_contexto = c.id_contexto
        WHERE NOT EXISTS (
            SELECT 1 FROM usuario.contexto o
            WHERE o.contexto_display = 'Curso: ' || c.cod_curso
              AND o.id_contexto <> c.id_contexto
        )
        ORDER BY c.id_curso
        LIMIT 1
    ");
    $firmante = DB::selectOne('SELECT id_usuario FROM usuario.usuario ORDER BY id_usuario LIMIT 1');

    if (!$row || !$row->id_rol || !$firmante) {
        return null;
    }

    $f = (array) $row;
    $f['id_firmante'] = $firmante->id_usuario;

    DB::update("
        UPDATE usuario.usuario_rol_asignacion
        SET esta_activo = false, fue_eliminado = true
        WHERE id_usuario = ? AND id_rol = ? AND id_contexto = ? AND esta_activo
    ", [$f['id_usuario'], $f['id_rol'], $f['id_contexto_real']]);

    return $f;
}

function crearContextoHuerfano(array $f): int
{
    return DB::selectOne('
        INSERT INTO usuario.contexto (contexto_display, id_tipo_contexto)
        VALUES (?, ?)
        RETURNING id_contexto
    ', ['Curso: '.$f['cod_curso'], $f['id_tipo_contexto']])->id_contexto;
}

function crearAsignacionTitular(array $f, int $idContexto, array $extra = []): int
{
    $datos = array_merge([
        'id_usuario' => $f['id_usuario'],
        'id_rol' => $f['id_rol'],
        'id_contexto' => $idContexto,
        'asignado_por' => $f['id_firmante'],
        'creado_por' => $f['id_firmante'],
        'fecha_inicio_planificada' => now()->subMinute(),
        'fecha_fin_planificada' => now()->addYear(),
        'fecha_creacion' => now()->subMinute(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ], $extra);

    $columnas = implode(', ', array_keys($datos));
    $marcas = implode(', ', array_fill(0, count($datos), '?'));

    return DB::selectOne(
        "INSERT INTO usuario.usuario_rol_asignacion ($columnas) VALUES ($marcas) RETURNING id_ura",
        array_values($datos)
    )->id_ura;
}

function leerAsignacionReparada(int $idUra): object
{
    return DB::selectOne('
        SELECT id_ura, id_contexto, esta_activo, fue_eliminado, fecha_inicio_planificada, fecha_fin_planificada
        FROM usuario.usuario_rol_asignacion WHERE id_ura = ?
    ', [$idUra]);
}

function activasTitularEnContextoReal(array $f): array
{
    return DB::select('
        SELECT id_ura FROM usuario.usuario_rol_asignacion
        WHERE id_usuario = ? AND id_rol = ? AND id_contexto = ?
          AND esta_activo AND COALESCE(fue_eliminado, false) = false
    ', [$f['id_usuario'], $f['id_rol'], $f['id_contexto_real']]);
}

test('una asignación del titular en un contexto huérfano pasa al contexto real del curso', function () {
    $f = fixtureCursoTitularSinContextoHuerfano();
    if (!$f) {
        $this->markTestSkipped('No hay un curso con titular sin contextos huérfanos.');
    }

    $huerfano = crearContextoHuerfano($f);
    $idUra = crearAsignacionTitular($f, $huerfano);

    migracionRepararContextoTitular()->up();

    $ura = leerAsignacionReparada($idUra);
    expect($ura->id_contexto)->toBe($f['id_contexto_real'])
        ->and($ura->esta_activo)->toBeTrue()
        ->and($ura->fue_eliminado)->toBeFalse()
        ->and(array_column(activasTitularEnContextoReal($f), 'id_ura'))->toBe([$idUra]);
});

test('dos asignaciones superpuestas en contextos huérfanos no chocan: queda una activa en el real', function () {
    $f = fixtureCursoTitularSinContextoHuerfano();
    if (!$f) {
        $this->markTestSkipped('No hay un curso con titular sin contextos huérfanos.');
    }

    $huerfanoA = crearContextoHuerfano($f);
    $huerfanoB = crearContextoHuerfano($f);

    // La primera insertada es la más reciente por fecha_creacion: así se
    // comprueba que el desempate es por fecha y no por id.
    $reciente = crearAsignacionTitular($f, $huerfanoA, ['fecha_creacion' => now()->subMinute()]);
    $antigua = crearAsignacionTitular($f, $huerfanoB, [
        'fecha_creacion' => now()->subDays(10),
        'fecha_inicio_planificada' => now()->subDays(10),
    ]);

    migracionRepararContextoTitular()->up();

    $elegida = leerAsignacionReparada($reciente);
    $sobrante = leerAsignacionReparada($antigua);

    expect($elegida->id_contexto)->toBe($f['id_contexto_real'])
        ->and($elegida->esta_activo)->toBeTrue()
        ->and($elegida->fue_eliminado)->toBeFalse()
        ->and($sobrante->esta_activo)->toBeFalse()
        ->and($sobrante->fue_eliminado)->toBeTrue()
        ->and(array_column(activasTitularEnContextoReal($f), 'id_ura'))->toBe([$reciente]);
});

test('si ya hay una activa en el contexto real, la huérfana se desactiva y no se duplica', function () {
    $f = fixtureCursoTitularSinContextoHuerfano();
    if (!$f) {
        $this->markTestSkipped('No hay un curso con titular sin contextos huérfanos.');
    }

    $enReal = crearAsignacionTitular($f, $f['id_contexto_real']);
    $huerfano = crearContextoHuerfano($f);
    $idHuerfana = crearAsignacionTitular($f, $huerfano);

    migracionRepararContextoTitular()->up();

    $huerfana = leerAsignacionReparada($idHuerfana);
    expect($huerfana->id_contexto)->toBe($huerfano)
        ->and($huerfana->esta_activo)->toBeFalse()
        ->and($huerfana->fue_eliminado)->toBeTrue()
        ->and(leerAsignacionReparada($enReal)->esta_activo)->toBeTrue()
        ->and(array_column(activasTitularEnContextoReal($f), 'id_ura'))->toBe([$enReal]);
});

test('correr up() dos veces no falla ni cambia nada la segunda vez', function () {
    $f = fixtureCursoTitularSinContextoHuerfano();
    if (!$f) {
        $this->markTestSkipped('No hay un curso con titular sin contextos huérfanos.');
    }

    $huerfanoA = crearContextoHuerfano($f);
    $huerfanoB = crearContextoHuerfano($f);
    $ids = [
        crearAsignacionTitular($f, $huerfanoA, ['fecha_creacion' => now()->subMinute()]),
        crearAsignacionTitular($f, $huerfanoB, ['fecha_creacion' => now()->subDays(10)]),
    ];

    migracionRepararContextoTitular()->up();
    $despuesDeLaPrimera = array_map(fn ($id) => leerAsignacionReparada($id), $ids);

    migracionRepararContextoTitular()->up();
    $despuesDeLaSegunda = array_map(fn ($id) => leerAsignacionReparada($id), $ids);

    expect($despuesDeLaSegunda)->toEqual($despuesDeLaPrimera)
        ->and(array_column(activasTitularEnContextoReal($f), 'id_ura'))->toBe([$ids[0]]);
});
