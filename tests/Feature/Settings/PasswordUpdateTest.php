<?php

use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'New-password-2026',
            'password_confirmation' => 'New-password-2026',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    expect(Hash::check('New-password-2026', $user->refresh()->passhash))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'New-password-2026',
            'password_confirmation' => 'New-password-2026',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/settings/password');
});
test('la nueva contraseña debe llevar al menos una mayúscula', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'sin-mayuscula-2026',
            'password_confirmation' => 'sin-mayuscula-2026',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/password');

    expect(session('errors')->first('password'))->toContain('mayúscula');
    expect(Hash::check('sin-mayuscula-2026', $user->refresh()->passhash))->toBeFalse();
});
