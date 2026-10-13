<?php

/**
 * Páginas de error propias (resources/views/errors): en español, con salida al
 * inicio, sin filtrar los textos internos en inglés de Laravel y respetando el
 * mensaje de un abort(…, 'mensaje') deliberado.
 */

use Symfony\Component\HttpKernel\Exception\HttpException;

function paginaDeError(int $codigo, string $mensaje = ''): string
{
    return view("errors.{$codigo}", ['exception' => new HttpException($codigo, $mensaje)])->render();
}

test('un abort con mensaje propio lo muestra tal cual', function () {
    expect(paginaDeError(403, 'Esta actividad no está disponible.'))
        ->toContain('Esta actividad no está disponible.')
        ->toContain('Ir al inicio');
});

test('los textos por defecto de Laravel en inglés se reemplazan por español', function () {
    $html = paginaDeError(403, 'This action is unauthorized.');
    expect($html)->not->toContain('unauthorized')->toContain('No tienes acceso');

    $html = paginaDeError(404, 'No query results for model [App\Models\Agenda\Actividad] 99999');
    expect($html)->not->toContain('No query results')->toContain('No encontramos lo que buscas');

    $html = paginaDeError(404, 'The route foo/bar could not be found.');
    expect($html)->not->toContain('could not be found');
});

test('cada código tiene su página y su título', function () {
    foreach ([403, 404, 419, 429, 500, 503] as $codigo) {
        $html = paginaDeError($codigo);
        expect($html)->toContain("Error {$codigo}")->toContain('UTAmed')->toContain('<html lang="es">');
    }
});

test('una ruta inexistente responde 404 con la página en español', function () {
    $this->get('/esta-ruta-no-existe-zz')
        ->assertStatus(404)
        ->assertSee('No encontramos lo que buscas');
});
