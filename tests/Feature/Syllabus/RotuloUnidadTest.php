<?php

/**
 * El nombre guardado de una unidad suele traer su rótulo («Unidad 1 : Fundamentos»).
 * Al leer el programa —pantalla y PDF— se escribe una sola vez; el dato no cambia.
 */

use App\Traits\ParsesSyllabus;

function leerUnidad(string $titulo): array
{
    $lector = new class
    {
        use ParsesSyllabus;

        public function leer(array $data): array
        {
            $porNumeral = fn (array $secciones) => collect($secciones)->keyBy('numeral_romano');

            return [
                'pantalla' => $porNumeral($this->parseSecciones($data)),
                'pdf' => $porNumeral($this->parseSeccionesEstructuradas($data)),
            ];
        }
    };

    $secciones = $lector->leer([
        'metadata' => ['tipo_syllabus' => 'COMPLETO'],
        'secciones' => [
            'VI' => ['contenido' => ['unidades' => [['numero' => 1, 'titulo' => $titulo, 'contenidos_items' => [], 'resultados_aprendizaje' => []]]]],
            'VIII' => ['contenido' => ['bibliografias' => [['titulo' => 'Libro', 'autor' => 'Autor', 'anio' => 2020, 'id_unidad' => 1]]]],
        ],
    ]);
    $texto = fn (string $numeral) => strtok($secciones['pantalla'][$numeral]['contenidos'][0]['texto_contenido'], "\n");

    return [
        $texto('VI'),
        $texto('VIII'),
        $secciones['pdf']['VI']['unidades'][0]['titulo'],
        $secciones['pdf']['VIII']['grupos'][0]['encabezado'],
    ];
}

test('el rótulo de la unidad se escribe una sola vez', function (string $titulo) {
    expect(leerUnidad($titulo))->toBe([
        'Unidad 1: Fundamentos',
        'UNIDAD 1: FUNDAMENTOS',
        'Fundamentos',
        'Unidad 1: Fundamentos',
    ]);
})->with(['Unidad 1 : Fundamentos', 'UNIDAD 1: Fundamentos', 'Fundamentos']);
