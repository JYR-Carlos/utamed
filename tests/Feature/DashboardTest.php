<?php

use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('authenticated users without role are redirected to sin-rol', function () {
    $user = Usuario::factory()->create([
        'fecha_cambio_passhash' => now(),
    ]);
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertRedirect(route('sin-rol'));
});

test('authenticated admin users can visit the admin dashboard', function () {
    $superadmin = Usuario::where('username', 'superadmin')->first();
    $superadmin->fecha_cambio_passhash = now();
    $superadmin->save();

    $response = $this->actingAs($superadmin)->get('/admin/dashboard');
    $response->assertStatus(200);
});