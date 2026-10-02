<?php

/*
 * T12: los datos institucionales del estudiante son de sólo lectura, también
 * en la API. La pantalla genérica de perfil lo manda a su ficha y el PATCH
 * directo se rechaza sin tocar la fila.
 */

use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function estudianteConClaveVigente(): Usuario
{
    $user = Usuario::factory()->create(['email' => 'solo.lectura.t12@alumnos.uta.cl']);
    $user->forceFill(['fecha_cambio_passhash' => now()])->save();
    Estudiante::factory()->create(['id_usuario' => $user->id_usuario]);

    return $user->fresh();
}

test('la pantalla genérica de perfil lleva al estudiante a su ficha', function () {
    $user = estudianteConClaveVigente();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertRedirect(route('estudiante.perfil'));
});

test('el estudiante no puede cambiar su correo institucional por la API', function () {
    $user = estudianteConClaveVigente();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Otro Nombre',
            'email' => 'personal.t12@gmail.com',
        ])
        ->assertForbidden();

    expect($user->fresh()->email)->toBe('solo.lectura.t12@alumnos.uta.cl');
});
