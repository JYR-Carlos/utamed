<?php

use App\Models\Usuario\Usuario;
use Inertia\Testing\AssertableInertia;

/**
 * Al pulsar "atrás" después del logout el navegador restauraba la página
 * anterior con los datos del usuario que cerró sesión. Estas pruebas cubren las
 * dos piezas que lo impiden.
 */

test('las páginas autenticadas no se guardan en la caché del navegador', function () {
    $user = Usuario::factory()->create();

    $response = $this->actingAs($user)->get('/settings/appearance');

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('las páginas de Inertia cifran su historial', function () {
    $user = Usuario::factory()->create();

    $page = AssertableInertia::fromTestResponse(
        $this->actingAs($user)->get('/settings/appearance')
    )->toArray();

    expect($page['encryptHistory'])->toBeTrue();
});

test('el logout marca el historial de Inertia para ser invalidado', function () {
    $user = Usuario::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    // La marca se escribe en la sesión nueva (Fortify ya invalidó la anterior)
    // y la recoge la primera respuesta Inertia posterior: la del login.
    $page = AssertableInertia::fromTestResponse($this->get('/'))->toArray();

    expect($page['clearHistory'])->toBeTrue();
});
