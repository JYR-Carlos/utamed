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

    expect($user->nombre1)->toBe('Test');
    expect($user->apellido1)->toBe('User');
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

test('user can delete their account', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/profile')
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});