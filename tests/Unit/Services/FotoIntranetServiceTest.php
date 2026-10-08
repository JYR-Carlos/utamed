<?php

/*
 * La foto del perfil sale del portal de la Intranet: el archivo se llama con el
 * RUT sin puntos ni guion, DV en mayúscula, relleno con ceros a la izquierda
 * hasta 10 caracteres y extensión .JPG.
 */

use App\Http\Controllers\Student\PerfilController;
use App\Services\FotoIntranetService;

dataset('ruts de la intranet', [
    'RUT de 8 dígitos con guion' => ['12024627-5', '0120246275.JPG'],
    'RUT con puntos y guion' => ['12.024.627-5', '0120246275.JPG'],
    'RUT de 7 dígitos con DV «k» minúscula' => ['9123456-k', '009123456K.JPG'],
    'RUT de 7 dígitos con DV «K» mayúscula' => ['1234567-K', '001234567K.JPG'],
    'RUT de docente de 8 dígitos' => ['15695395-4', '0156953954.JPG'],
    // Antes sólo se rellenaba con uno o dos ceros fijos según el largo.
    'RUT de 6 dígitos' => ['123456-7', '0001234567.JPG'],
]);

test('arma la URL de la foto con el RUT normalizado a 10 caracteres', function (string $rut, string $archivo) {
    $servicio = new FotoIntranetService();

    expect($servicio->getImagenPerfilURL($rut))->toBe('https://portal.uta.cl/fotos/' . $archivo);
})->with('ruts de la intranet');

test('el perfil del estudiante recibe el servicio por el contenedor', function () {
    $controlador = app(PerfilController::class);

    $propiedad = new ReflectionProperty(PerfilController::class, 'fotoIntranet');

    expect($propiedad->getValue($controlador))->toBeInstanceOf(FotoIntranetService::class);
});
