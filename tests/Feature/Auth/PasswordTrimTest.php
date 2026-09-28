<?php

/*
 * T54: las contraseñas se recortan (App\Http\Middleware\TrimStrings) tanto al
 * guardarlas como al compararlas, para que un espacio pegado por accidente al
 * inicio o al final no bloquee el acceso.
 */

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

test('el login acepta la contraseña con espacios pegados al inicio', function () {
    $user = Usuario::factory()->create();

    $this->post('/login', [
        'email' => $user->rut,
        'password' => '   password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('el login acepta la contraseña con espacios al inicio y al final', function () {
    $user = Usuario::factory()->create();

    $this->post('/login', [
        'email' => $user->rut,
        'password' => "\t password  ",
    ]);

    $this->assertAuthenticatedAs($user);
});

test('los espacios no convierten una contraseña incorrecta en correcta', function () {
    $user = Usuario::factory()->create();

    $this->post('/login', [
        'email' => $user->rut,
        'password' => '   otra-clave',
    ]);

    $this->assertGuest();
});

test('el cambio de contraseña guarda la clave recortada', function () {
    $user = Usuario::factory()->create();

    $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => '  password',
            'password' => '  nueva-clave-2026',
            'password_confirmation' => '  nueva-clave-2026',
        ])
        ->assertSessionHasNoErrors();

    $passhash = $user->refresh()->passhash;

    expect(Hash::check('nueva-clave-2026', $passhash))->toBeTrue()
        ->and(Hash::check('  nueva-clave-2026', $passhash))->toBeFalse();
});

test('tras cambiarla con espacios se puede entrar con o sin ellos', function () {
    $user = Usuario::factory()->create();

    $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => ' nueva-clave-2026',
            'password_confirmation' => ' nueva-clave-2026',
        ])
        ->assertSessionHasNoErrors();

    $this->post('/logout');
    $this->assertGuest();

    $this->post('/login', [
        'email' => $user->rut,
        'password' => 'nueva-clave-2026',
    ]);
    $this->assertAuthenticatedAs($user);

    $this->post('/logout');

    $this->post('/login', [
        'email' => $user->rut,
        'password' => '   nueva-clave-2026',
    ]);
    $this->assertAuthenticatedAs($user);
});
