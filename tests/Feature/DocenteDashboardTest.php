<?php

use App\Models\Curso\Curso;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;
use Inertia\Testing\AssertableInertia as Assert;

test('docente dashboard excluye cursos plantilla del periodo vigente y del conteo de cursos', function () {
    $docente = Docente::whereHas('usuario')->with('usuario')->find(11);
    if (!$docente) {
        $this->markTestSkipped('Docente 11 no encontrado.');
    }

    $usuario = $docente->usuario;
    $usuario->fecha_cambio_passhash = now();
    $usuario->save();

    $response = $this->actingAs($usuario)->get('/docente/dashboard');

    $response->assertStatus(200);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('docente/Dashboard')
        ->has('periodo')
        ->where('periodo.ano', 2026)
        ->where('periodo.sem', 2)
        ->where('periodo.fecha_inicio', fn ($val) => $val !== null && !str_starts_with((string) $val, '2026-01-01'))
        ->has('cursosTitular')
        ->where('cursosTitular', fn ($cursos) => collect($cursos)->every(fn ($c) => !str_starts_with($c['nombre'], 'Plantilla - ')))
        ->has('componentes')
        ->where('componentes', fn ($componentes) => collect($componentes)->every(fn ($c) => !str_starts_with($c['nombre'], 'Plantilla - ')))
        ->where('stats.total_cursos', fn ($total) => $total > 0)
    );
});
