<?php

use App\Models\Administrativo\Programa;
use App\Models\Administrativo\EstructuraPrograma;

test('programa tiene atributos fillable correctos', function () {
    $fillable = (new Programa())->getFillable();

    expect($fillable)->toContain('id_curso');
    expect($fillable)->toContain('version_programa');
    expect($fillable)->toContain('creado_por');
    expect($fillable)->toContain('es_plantilla');
    expect($fillable)->toContain('es_actual');
});

test('programa usa tabla correcta', function () {
    $programa = new Programa();

    expect($programa->getTable())->toBe('Programa');
});

test('programa tiene primary key correcta', function () {
    $programa = new Programa();

    expect($programa->getKeyName())->toBe('id_programa');
});

test('programa es auto-incrementing', function () {
    $programa = new Programa();

    expect($programa->getIncrementing())->toBeTrue();
});

test('estructura programa usa tabla correcta', function () {
    $estructura = new EstructuraPrograma();

    expect($estructura->getTable())->toBe('Estructura_Programa');
});

test('estructura programa tiene primary key correcta', function () {
    $estructura = new EstructuraPrograma();

    expect($estructura->getKeyName())->toBe('id_seccion');
});
