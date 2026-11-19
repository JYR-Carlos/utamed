<?php

/**
 * «Mensajes para» (/docente/mensajeria): los cursos del semestre vigente del
 * docente con sus mensajes sin leer, sin estudiantes ni pendientes.
 */

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

test('mensajes para lista los cursos del semestre vigente con sus mensajes sin leer', function () {
    $row = DB::selectOne("
        SELECT d.id_usuario
        FROM usuario.docente d
        JOIN curso.curso c ON c.id_docente_titular = d.id_docente
        WHERE c.es_plantilla = false AND c.fecha_eliminacion IS NULL AND c.agno_real IS NOT NULL
        ORDER BY d.id_docente
        LIMIT 1
    ") ?? $this->markTestSkipped('No hay docentes titulares con cursos.');

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now()->subDay();
    $usuario->save();

    $this->actingAs($usuario)
        ->get('/docente/mensajeria')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docente/MensajesPara')
            ->has('periodo.sem')
            ->has('periodo.ano')
            ->where('cursos', function ($cursos) {
                expect($cursos)->not->toBeEmpty();
                foreach ($cursos as $c) {
                    expect($c)->toHaveKeys(['id_curso', 'nombre', 'codigo', 'letra_grupo', 'no_leidos'])
                        ->not->toHaveKey('total_estudiantes');
                }

                return true;
            })
        );
});
