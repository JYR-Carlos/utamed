<?php

test('la raíz lleva directo al login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
