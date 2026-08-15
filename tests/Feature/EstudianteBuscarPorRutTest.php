<?php

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use App\Services\EstudianteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Búsqueda del alumno por RUT cuando la sincronización con la intranet decide si
 * crearlo o reutilizarlo.
 *
 * Es el punto donde nacen los usuarios duplicados: la intranet manda el RUT como
 * número **sin dígito verificador** (`ALUM_RUT` = 23671848) y la columna guarda
 * "23671848-4". Si la búsqueda no los reconoce como la misma persona, se inserta
 * de nuevo.
 */
uses(DatabaseTransactions::class);

function estudianteConRut(string $rut): Estudiante
{
    $carrera = Carrera::first();

    if (! $carrera) {
        test()->markTestSkipped('La base de pruebas no tiene ninguna carrera.');
    }

    $sufijo = random_int(100000, 999999);

    $usuario = Usuario::create([
        'username'    => "tstrut{$sufijo}",
        'passhash'    => 'x',
        'email'       => "tstrut{$sufijo}@example.test",
        'rut'         => $rut,
        'nombre1'     => 'Alumno',
        'apellido1'   => 'De Prueba',
        'esta_activo' => true,
    ]);

    return Estudiante::create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
    ]);
}

it('reconoce al alumno cuando el RUT llega sin dígito verificador', function () {
    $estudiante = estudianteConRut('23671848-4');

    $encontrado = app(EstudianteService::class)->buscarPorRut(23671848);

    expect($encontrado?->id_estudiante)->toBe($estudiante->id_estudiante);
});

it('reconoce al alumno cuando el RUT llega escrito completo', function () {
    $estudiante = estudianteConRut('18167586-1');
    $servicio = app(EstudianteService::class);

    expect($servicio->buscarPorRut('18167586-1')?->id_estudiante)->toBe($estudiante->id_estudiante)
        ->and($servicio->buscarPorRut('18.167.586-1')?->id_estudiante)->toBe($estudiante->id_estudiante);
});

it('no devuelve a otra persona cuando el RUT no coincide', function () {
    estudianteConRut('23671848-4');

    // Mismo largo, cuerpo distinto: no es la misma persona.
    expect(app(EstudianteService::class)->buscarPorRut(23671849))->toBeNull();
});

it('escribe el RUT en un solo formato aunque venga con puntos', function () {
    $estudiante = estudianteConRut('11.222.333-4');

    expect($estudiante->usuario->rut)->toBe('11222333-4');
});
