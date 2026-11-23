<?php

use Illuminate\Support\Facades\Route;

// En los tests el middleware CSRF no verifica el token, así que la ruta de
// prueba lanza el 419 que produciría un formulario enviado con la sesión vencida.
beforeEach(function () {
    Route::middleware('web')->post('/_prueba/sesion-expirada', fn () => abort(419));
});

test('un formulario con la sesión vencida vuelve al login con el aviso', function () {
    $this->post('/_prueba/sesion-expirada')
        ->assertRedirect(route('login'))
        ->assertSessionHas('login_error.code', 'SESSION_EXPIRED');
});

test('una petición JSON con la sesión vencida sigue respondiendo 419', function () {
    $this->postJson('/_prueba/sesion-expirada')->assertStatus(419);
});

