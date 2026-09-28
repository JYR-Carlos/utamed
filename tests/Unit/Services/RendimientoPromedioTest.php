<?php

/*
 * T30: promedio ponderado del alumno en un componente, normalizado sobre las
 * actividades ya evaluadas.
 */

use App\Services\Student\RendimientoCursoEstudiante;

beforeEach(function () {
    $this->servicio = new RendimientoCursoEstudiante();
    // id_actividad => ponderación (suman 100)
    $this->ponderaciones = collect([15 => 30.0, 18 => 40.0, 20 => 30.0]);
});

test('sin notas no hay promedio', function () {
    expect($this->servicio->promedioPonderado(collect(), $this->ponderaciones))->toBeNull();
});

test('con una sola nota evaluada el promedio es esa nota', function () {
    expect($this->servicio->promedioPonderado(collect([15 => 5.5]), $this->ponderaciones))->toBe(5.5);
});

test('pondera y normaliza sobre lo ya evaluado', function () {
    // (6.0*30 + 4.0*40) / 70 = 4.857… → 4.9
    expect($this->servicio->promedioPonderado(collect([15 => 6.0, 18 => 4.0]), $this->ponderaciones))->toBe(4.9);
});

test('con todas las actividades evaluadas usa las ponderaciones completas', function () {
    // 7*0.3 + 5*0.4 + 4*0.3 = 5.3
    expect($this->servicio->promedioPonderado(collect([15 => 7.0, 18 => 5.0, 20 => 4.0]), $this->ponderaciones))->toBe(5.3);
});

test('si ninguna actividad tiene ponderación usa promedio simple', function () {
    expect($this->servicio->promedioPonderado(collect([1 => 6.0, 2 => 5.0]), collect([1 => 0.0, 2 => 0.0])))->toBe(5.5);
});
