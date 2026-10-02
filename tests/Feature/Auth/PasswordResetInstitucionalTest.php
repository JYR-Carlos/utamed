<?php

/*
 * T17: el enlace de restablecimiento de contraseña sale siempre hacia el
 * correo institucional del usuario (`usuario.email`), nunca a otra casilla.
 */

use App\Models\Usuario\Usuario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;

uses(DatabaseTransactions::class);

test('el enlace de restablecimiento va al correo institucional', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.prueba.t17@alumnos.uta.cl']);

    $this->post('/forgot-password', ['email' => 'alumno.prueba.t17@alumnos.uta.cl'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        fn ($notification, array $channels, $notifiable) => $channels === ['mail']
            && $notifiable->routeNotificationFor('mail', $notification) === 'alumno.prueba.t17@alumnos.uta.cl',
    );
});

test('un correo que no es el institucional de ninguna cuenta no recibe enlace', function () {
    Notification::fake();

    Usuario::factory()->create(['email' => 'alumno.prueba.t17b@alumnos.uta.cl']);

    $this->post('/forgot-password', ['email' => 'personal.t17b@gmail.com']);

    Notification::assertNothingSent();
});

test('el enlace permite fijar una nueva contraseña', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.prueba.t17c@alumnos.uta.cl']);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'nueva-clave-2026',
            'password_confirmation' => 'nueva-clave-2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    $this->post('/login', ['email' => $user->rut, 'password' => 'nueva-clave-2026']);
    $this->assertAuthenticatedAs($user);
});
