<?php

use App\Support\Rut;

/**
 * El formato único con el que se guarda el RUT.
 *
 * Los casos son las escrituras que llegaban a la columna y la duplicaban: con
 * puntos (sembrados y planillas), sin guion (pegado desde otro sistema) y con la
 * K en minúscula.
 */

it('lleva cualquier escritura al formato canónico', function (string $entrada, string $esperado) {
    expect(Rut::normalizar($entrada))->toBe($esperado);
})->with([
    'con puntos y guion'   => ['23.671.848-4', '23671848-4'],
    'sólo con guion'       => ['23671848-4', '23671848-4'],
    'sin separadores'      => ['236718484', '23671848-4'],
    'con espacios'         => ['  23.671.848 - 4 ', '23671848-4'],
    'K en minúscula'       => ['17.894.861-k', '17894861-K'],
    'cuerpo de 7 dígitos'  => ['9.123.456-3', '9123456-3'],
]);

it('deja intacto lo que no tiene forma de RUT, para que lo rechace la validación', function () {
    expect(Rut::normalizar('sin-rut'))->toBe('sin-rut')
        ->and(Rut::normalizar('123'))->toBe('123')
        ->and(Rut::normalizar('123456789012'))->toBe('123456789012');
});

it('trata el vacío como ausencia de dato', function () {
    expect(Rut::normalizar(null))->toBeNull()
        ->and(Rut::normalizar(''))->toBeNull()
        ->and(Rut::normalizar('   '))->toBeNull();
});

it('arma el RUT cuando la intranet manda cuerpo y dígito por separado', function () {
    expect(Rut::desdePartes(23671848, '4'))->toBe('23671848-4')
        ->and(Rut::desdePartes('9123456', 'k'))->toBe('9123456-K')
        ->and(Rut::desdePartes('23671848-4'))->toBe('23671848-4');
});

it('respeta el corte del origen cuando el dígito verificador viene aparte', function () {
    // El largo no alcanza para deducir dónde termina el cuerpo, pero el origen ya
    // lo dijo: se le cree en vez de reinterpretarlo.
    expect(Rut::desdePartes('123456', '7'))->toBe('123456-7');
});

it('reconoce si un valor es un RUT y si ya está en el formato guardado', function () {
    expect(Rut::esValido('23.671.848-4'))->toBeTrue()
        ->and(Rut::esValido('sin-rut'))->toBeFalse()
        ->and(Rut::esCanonico('23671848-4'))->toBeTrue()
        ->and(Rut::esCanonico('23.671.848-4'))->toBeFalse();
});

it('normalizar es idempotente: aplicarlo dos veces no cambia el resultado', function () {
    $unaVez = Rut::normalizar('23.671.848-4');

    expect(Rut::normalizar($unaVez))->toBe($unaVez);
});
