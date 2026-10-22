<?php

use App\Models\Usuario\Usuario;
use Laravel\Fortify\Features;

test('email verification feature is disabled in fortify', function () {
    expect(Features::enabled(Features::emailVerification()))->toBeFalse();
});

test('unverified user can access protected routes without email verification challenge', function () {
    /** @var Usuario $user */
    $user = Usuario::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    // Debe permitir acceso o redirigir según su rol/estado de perfil, sin redirigir a verificación
    $response->assertRedirect();
    $this->assertStringNotContainsString('email/verify', (string) $response->headers->get('Location'));
});
