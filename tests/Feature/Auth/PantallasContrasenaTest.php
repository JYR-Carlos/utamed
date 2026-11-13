<?php

/*
 * RED-IMP-05b: lo que las pantallas de contraseña necesitan del servidor.
 *
 * - Recuperar: si el enlace no sale, la pantalla sabe por qué (correo sin
 *   cuenta o pedido muy seguido, con los segundos que faltan).
 * - Cambio obligatorio: al guardar, el usuario vuelve a la misma pantalla con
 *   el aviso de que la contraseña se cambió, y la navegación queda libre.
 */

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseTransactions::class);

test('la pantalla de recuperar recibe la vigencia y la pausa del enlace', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ForgotPassword')
            ->where('vigenciaMinutos', (int) config('auth.passwords.users.expire'))
            ->where('esperaReenvio', (int) config('auth.passwords.users.throttle'))
            ->where('aviso', null));
});

test('un correo sin cuenta vuelve con el aviso no_registrado', function () {
    Notification::fake();

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'nadie.red05b@alumnos.uta.cl'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasErrors('email')
        ->assertSessionHas('recuperarAviso', ['codigo' => 'no_registrado']);

    Notification::assertNothingSent();
});

test('pedir un segundo enlace enseguida vuelve con la pausa y los segundos que faltan', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.red05b@alumnos.uta.cl']);

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasErrors('email');

    $aviso = session('recuperarAviso');
    expect($aviso['codigo'])->toBe('pausa')
        ->and($aviso['segundos'])->toBeGreaterThanOrEqual(1)
        ->toBeLessThanOrEqual((int) config('auth.passwords.users.throttle'));

    // Y la pantalla lo recibe al volver.
    $this->get('/forgot-password')
        ->assertInertia(fn (Assert $page) => $page->where('aviso.codigo', 'pausa'));
});

test('el cambio obligatorio vuelve a la pantalla con el aviso de éxito', function () {
    $user = Usuario::factory()->create(['fecha_cambio_passhash' => null]);
    expect($user->debeCambiarPassword())->toBeTrue();

    $this->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'Nueva-clave-2026',
            'password_confirmation' => 'Nueva-clave-2026',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-password.edit'));

    expect(Hash::check('Nueva-clave-2026', $user->refresh()->passhash))->toBeTrue()
        ->and($user->debeCambiarPassword())->toBeFalse();

    $this->get(route('user-password.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/CambioPasswordObligatorio')
            ->where('cambiada', true)
            ->where('motivo', Usuario::CAMBIO_PASSWORD_PRIMER_INGRESO));

    // El aviso se ve una sola vez; después la ruta es la de siempre.
    $despues = $this->get(route('user-password.edit'))->assertOk();
    expect($despues->viewData('page')['component'])->not->toBe('auth/CambioPasswordObligatorio');
});

test('mientras el cambio sigue pendiente la pantalla no trae el aviso', function () {
    $user = Usuario::factory()->create(['fecha_cambio_passhash' => null]);

    $this->actingAs($user)
        ->get(route('user-password.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/CambioPasswordObligatorio')
            ->where('cambiada', false));
});
