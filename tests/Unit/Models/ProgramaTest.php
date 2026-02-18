<?php

use App\Models\Administrativo\Programa;

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

    expect($programa->getTable())->toBe('programa');
});

test('programa tiene primary key correcta', function () {
    $programa = new Programa();

    expect($programa->getKeyName())->toBe(['id_programa', 'id_curso', 'es_plantilla', 'es_actual']);
});

test('programa no es auto-incrementing', function () {
    $programa = new Programa();

    expect($programa->getIncrementing())->toBeFalse();
});
