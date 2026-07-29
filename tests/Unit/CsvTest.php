<?php

use App\Support\Csv;

test('los valores que empiezan por un carácter de fórmula se neutralizan', function (string $entrada) {
    expect(Csv::celda($entrada))->toBe("'" . $entrada);
})->with([
    '=cmd|\'/c calc\'!A1',
    '+1+1',
    '-2+3',
    '@SUM(A1:A9)',
    "\tvalor",
    "\rvalor",
]);

test('un valor normal no se altera', function () {
    expect(Csv::celda('Juan Pérez'))->toBe('Juan Pérez');
    expect(Csv::celda('CURSO-101'))->toBe('CURSO-101');
    expect(Csv::celda('a=b'))->toBe('a=b');
});

test('null y cadena vacía se emiten como celda vacía', function () {
    expect(Csv::celda(null))->toBe('');
    expect(Csv::celda(''))->toBe('');
});

test('los escalares no string se convierten sin prefijo', function () {
    expect(Csv::celda(42))->toBe('42');
    expect(Csv::celda(3.5))->toBe('3.5');
    expect(Csv::celda(true))->toBe('1');
    expect(Csv::celda(false))->toBe('0');
});

test('un número negativo se neutraliza: para la hoja de cálculo empieza por fórmula', function () {
    // -1 llega como string desde la BD en la mayoría de columnas; el prefijo es
    // el precio de no poder distinguirlo de `-2+3`.
    expect(Csv::celda('-1'))->toBe("'-1");
});

test('las fechas se formatean en vez de serializarse', function () {
    $fecha = new DateTimeImmutable('2026-03-15 08:30:00');

    expect(Csv::celda($fecha))->toBe('2026-03-15 08:30:00');
});

test('fila() sanea todas las celdas conservando las claves', function () {
    $fila = Csv::fila([
        'id' => 7,
        'nombre' => '=HYPERLINK("http://x")',
        'curso' => 'Anatomía I',
    ]);

    expect($fila)->toBe([
        'id' => '7',
        'nombre' => '\'=HYPERLINK("http://x")',
        'curso' => 'Anatomía I',
    ]);
});
