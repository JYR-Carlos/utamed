<?php

/*
 * T17: el enlace de restablecimiento de contraseña sale siempre hacia el
 * correo institucional del usuario (`usuario.email`), nunca a otra casilla.
 * Se pide con el RUT, como en el login: la persona no escribe ningún correo.
 */

use App\Models\Usuario\Usuario;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;

uses(DatabaseTransactions::class);

test('con el RUT, el enlace va al correo institucional de la cuenta', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.prueba.t17@alumnos.uta.cl']);

    $this->post('/forgot-password', ['rut' => $user->rut])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('recuperarEnviado', 'al•••@alumnos.uta.cl');

    Notification::assertSentTo(
        $user,
        RestablecerContrasena::class,
        fn ($notification, array $channels, $notifiable) => $channels === ['mail']
            && $notifiable->routeNotificationFor('mail', $notification) === 'alumno.prueba.t17@alumnos.uta.cl',
    );
});

test('el RUT se encuentra escrito con puntos, sin guion o con la k en minúscula', function () {
    Notification::fake();

    // Un RUT que no esté en la base de pruebas, con K para probar la minúscula.
    do {
        $cuerpo = random_int(30000000, 39999999);
    } while (Usuario::porRut("{$cuerpo}K"));

    $user = Usuario::factory()->create(['rut' => "{$cuerpo}-K", 'email' => 'alumno.prueba.t17e@alumnos.uta.cl']);

    $this->post('/forgot-password', ['rut' => number_format($cuerpo, 0, '', '.').'k'])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, RestablecerContrasena::class);
});

test('un correo que venga en el pedido no se usa: el enlace va igual al institucional', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.prueba.t17b@alumnos.uta.cl']);

    $this->post('/forgot-password', ['rut' => $user->rut, 'email' => 'personal.t17b@gmail.com'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $user,
        RestablecerContrasena::class,
        fn ($notification, array $channels, $notifiable) => $notifiable->routeNotificationFor('mail', $notification) === 'alumno.prueba.t17b@alumnos.uta.cl',
    );
    Notification::assertSentTimes(RestablecerContrasena::class, 1);
});

test('el correo sale en español, con el nombre, el enlace y la vigencia', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.prueba.t17d@alumnos.uta.cl', 'nombre1' => 'MARÍA']);

    $this->post('/forgot-password', ['rut' => $user->rut]);

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

test('el enlace permite fijar una nueva contraseña', function () {
    Notification::fake();

    $user = Usuario::factory()->create(['email' => 'alumno.prueba.t17c@alumnos.uta.cl']);

    $this->post('/forgot-password', ['rut' => $user->rut]);

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
