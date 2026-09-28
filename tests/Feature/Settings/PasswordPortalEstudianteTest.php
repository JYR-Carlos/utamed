<?php

/*
 * T16: quien sólo es estudiante cambia su contraseña dentro del portal del
 * estudiante; el resto sigue viendo la página genérica de ajustes.
 */

use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;

uses(DatabaseTransactions::class);

/** Usuario con la clave vigente, para que no lo intercepte el cambio obligatorio. */
function usuarioConClaveVigente(): Usuario
{
    $user = Usuario::factory()->create();
    $user->forceFill(['fecha_cambio_passhash' => now()])->save();

    return $user;
}

test('el estudiante ve el cambio de contraseña dentro de su portal', function () {
    $user = usuarioConClaveVigente();
    Estudiante::factory()->create(['id_usuario' => $user->id_usuario]);

    $this->actingAs($user)
        ->get('/settings/password')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('student/CambiarPassword'));
});

test('un docente sigue viendo la página de ajustes', function () {
    $user = usuarioConClaveVigente();
    Docente::factory()->create(['id_usuario' => $user->id_usuario]);

    $this->actingAs($user)
        ->get('/settings/password')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('settings/Password'));
});

test('el estudiante cambia su clave sin que se le cierre la sesión', function () {
    $user = usuarioConClaveVigente();
    Estudiante::factory()->create(['id_usuario' => $user->id_usuario]);

    $this->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'nueva-clave-2026',
            'password_confirmation' => 'nueva-clave-2026',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    $this->assertAuthenticatedAs($user);
});
