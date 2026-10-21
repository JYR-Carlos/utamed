<?php

use App\Models\Usuario\Usuario;

test('profile page is displayed', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/settings/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    Usuario::where('email', 'test@example.com')->forceDelete();
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'nombre1' => 'Test',
            'apellido1' => 'User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    $user->refresh();

    expect($user->nombre1)->toBe('TEST');
    expect($user->apellido1)->toBe('USER');
    expect($user->email)->toBe('test@example.com');
    expect($user->fecha_verificacion_email)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/settings/profile', [
            'nombre1' => $user->nombre1,
            'apellido1' => $user->apellido1,
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh()->fecha_verificacion_email)->not->toBeNull();
});

test('validation errors are shown in Spanish', function () {
    $otro = Usuario::factory()->create();
    // Con la clave al día: si no, ForcePasswordChange desvía la petición antes de validar.
    $user = Usuario::factory()->create(['fecha_cambio_passhash' => now()]);

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => '', 'email' => $otro->email])
        ->assertSessionHasErrors([
            'name' => 'Ingresa tu nombre.',
            'email' => 'Ese correo ya está registrado en otra cuenta.',
        ]);

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'Test', 'email' => 'no-es-un-correo'])
        ->assertSessionHasErrors(['email' => 'Ingresa un correo electrónico válido, en minúsculas.']);
});

test('user cannot delete their own account', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response->assertMethodNotAllowed();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh())->not->toBeNull();
});