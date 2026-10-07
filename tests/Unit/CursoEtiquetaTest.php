<?php

/*
 * Curso::etiqueta() es el rótulo único de un curso en breadcrumbs y menús:
 * «{asignatura} Grupo {letra}». Dos cursos hermanos del mismo docente se
 * llaman igual salvo por la letra, así que la letra no puede faltar.
 */

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Curso\Curso;

function cursoConAsignatura(?string $asignatura, ?string $nombre, ?string $letra): Curso
{
    $curso = new Curso();
    $curso->nombre = $nombre;
    $curso->letra_grupo = $letra;

    $plan = new AsignacionPlan();
    $plan->setRelation('asignatura', $asignatura === null ? null : tap(new Asignatura(), fn ($a) => $a->nombre = $asignatura));
    $curso->setRelation('asignacionPlan', $plan);

    return $curso;
}

test('usa el nombre de la asignatura y la letra del grupo', function () {
    $curso = cursoConAsignatura('Taller de Multimedia II', 'Taller de Multimedia II', 'A');

    expect($curso->etiqueta())->toBe('Taller de Multimedia II Grupo A');
});

test('prefiere la asignatura al nombre del curso', function () {
    $curso = cursoConAsignatura('Taller Profesional IV', 'DM095 sección especial', 'B');

    expect($curso->etiqueta())->toBe('Taller Profesional IV Grupo B');
});

test('sin asignatura cae al nombre del curso', function () {
    $curso = cursoConAsignatura(null, 'Curso sin plan', 'C');

    expect($curso->etiqueta())->toBe('Curso sin plan Grupo C');
});

test('sin letra de grupo no agrega «Grupo»', function () {
    $curso = cursoConAsignatura('Taller de Multimedia II', 'Taller de Multimedia II', null);

    expect($curso->etiqueta())->toBe('Taller de Multimedia II');
});
