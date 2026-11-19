<?php

/**
 * Mi perfil del docente: datos de la Intranet y la fecha del último cambio de
 * contraseña. Desde la revisión «Mi UTAmed» ya no lleva el semestre, el
 * usuario ni los cursos asignados.
 */

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

test('el perfil muestra los datos de la Intranet sin semestre, usuario ni cursos', function () {
    $row = DB::selectOne('SELECT d.id_usuario FROM usuario.docente d ORDER BY d.id_docente LIMIT 1');
    if (!$row) {
        $this->markTestSkipped('No hay docentes.');
    }

    $usuario = Usuario::findOrFail($row->id_usuario);
    $usuario->fecha_cambio_passhash = now()->subDay();
    $usuario->save();

    $this->actingAs($usuario)
        ->get('/docente/perfil')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('docente/Perfil')
            ->where('perfil.rut', $usuario->rut)
            ->where('perfil.email', $usuario->email)
            ->has('perfil.cargo')
            ->has('perfil.grado')
            ->has('perfil.titulo')
            ->where('perfil.ultimo_cambio_clave', fn ($fecha) => $fecha !== null)
            ->missing('perfil.username')
            ->missing('periodo')
            ->missing('cursos')
        );
});
