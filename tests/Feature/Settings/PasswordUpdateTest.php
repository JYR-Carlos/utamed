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
            'password' => 'new-password-2026',
            'password_confirmation' => 'new-password-2026',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/password');

    expect(Hash::check('new-password-2026', $user->refresh()->passhash))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = Usuario::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-2026',
            'password_confirmation' => 'new-password-2026',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/settings/password');
});