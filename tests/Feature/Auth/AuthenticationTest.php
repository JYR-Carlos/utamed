<?php

use App\Models\Usuario\Usuario;
use App\Support\DataGenerators\ChileanNameGenerator;

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
    Usuario::where('rut', '99887766-K')->forceDelete();
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

test('un RUT sin cuenta y una contraseña errónea dan el mismo error', function () {
    $user = Usuario::factory()->create();
    do {
        $rutSinCuenta = ChileanNameGenerator::generarRUT();
    } while (Usuario::where('rut', $rutSinCuenta)->exists());

    $this->post('/login', ['email' => $rutSinCuenta, 'password' => 'password'])
        ->assertSessionHas('login_error.code', 'INVALID_CREDENTIALS')
        ->assertSessionHasErrors(['email' => 'Credenciales incorrectas.']);

    $this->post('/login', ['email' => $user->rut, 'password' => 'wrong-password'])
        ->assertSessionHas('login_error.code', 'INVALID_CREDENTIALS')
        ->assertSessionHasErrors(['email' => 'Credenciales incorrectas.']);

    $this->assertGuest();
});

test('la cuenta desactivada sólo se informa con la contraseña correcta', function () {
    $user = Usuario::factory()->inactive()->create();

    $this->post('/login', ['email' => $user->rut, 'password' => 'wrong-password'])
        ->assertSessionHas('login_error.code', 'INVALID_CREDENTIALS');

    $this->post('/login', ['email' => $user->rut, 'password' => 'password'])
        ->assertSessionHas('login_error.code', 'USER_INACTIVE');

    $this->assertGuest();
});

test('el exceso de intentos vuelve al login con la pausa en segundos', function () {
    $user = Usuario::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->rut, 'password' => 'wrong-password']);
    }

    $response = $this->post('/login', ['email' => $user->rut, 'password' => 'password']);

    $response->assertRedirect();
    $response->assertSessionHas('login_error.code', 'RATE_LIMIT_EXCEEDED');
    expect(session('login_error.retry_after'))->toBeGreaterThan(0);
    $this->assertGuest();
});

test('users can logout', function () {
    $user = Usuario::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/login');
});