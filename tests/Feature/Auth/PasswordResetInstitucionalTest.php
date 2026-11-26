<?php

/*
 * T17: el enlace de restablecimiento de contraseña sale siempre hacia el
 * correo institucional del usuario (`usuario.email`), nunca a otra casilla.
 */

use App\Models\Usuario\Usuario;
use App\Notifications\RestablecerContrasena;
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
        RestablecerContrasena::class,
        fn ($notification, array $channels, $notifiable) => $channels === ['mail']
            && $notifiable->routeNotificationFor('mail', $notification) === 'alumno.prueba.t17@alumnos.uta.cl',
    );
});

test('el correo sale en español, con el nombre, el enlace y la vigencia', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.prueba.t17d@alumnos.uta.cl', 'nombre1' => 'MARÍA']);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, RestablecerContrasena::class, function ($notification) use ($user) {
        $correo = $notification->toMail($user);
        $enlace = "/reset-password/{$notification->token}?email=alumno.prueba.t17d%40alumnos.uta.cl";
        $minutos = config('auth.passwords.users.expire');

        expect($correo->subject)->toBe('Elige tu contraseña nueva · UTAMED')
            ->and((string) $correo->render())
            ->toContain('Hola, María:', $enlace, "{$minutos} minutos", 'cite@gestion.uta.cl')
            ->and(view($correo->view[1], $correo->viewData)->render())
            ->toContain($enlace, "{$minutos} minutos");

        return true;
    });
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

    Notification::assertSentTo($user, RestablecerContrasena::class, function ($notification) use ($user) {
        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Nueva-clave-2026',
            'password_confirmation' => 'Nueva-clave-2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });

    $this->post('/login', ['email' => $user->rut, 'password' => 'Nueva-clave-2026']);
    $this->assertAuthenticatedAs($user);
});
