<?php

use App\Models\Usuario\Usuario;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = Usuario::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->rut,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('users with rut ending in lowercase k can authenticate', function () {
    $user = Usuario::factory()->create([
        'rut' => '99887766-K',
        'passhash' => \Illuminate\Support\Facades\Hash::make('password'),
        'esta_activo' => true,
    ]);

    $response = $this->post('/login', [
        'email' => '99887766-k',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $user = Usuario::factory()->create();

    $this->post('/login', [
        'email' => $user->rut,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = Usuario::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});