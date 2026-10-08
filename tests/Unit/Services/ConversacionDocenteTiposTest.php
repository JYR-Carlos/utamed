<?php

/*
 * El hilo completo que ve el docente en la agenda filtra por
 * ConversacionDocenteService::TIPOS_HILO_COMPLETO. La apelación de la nota
 * (T48) tiene que aparecer ahí sin perder los tipos que ya se mostraban.
 */

use App\Enums\DB\TipoMensaje;
use App\Services\Docente\ConversacionDocenteService;

test('el hilo completo incluye la solicitud y la resolución de apelación', function () {
    expect(ConversacionDocenteService::TIPOS_HILO_COMPLETO)
        ->toContain(TipoMensaje::SOLICITUD_DE_APELACIÓN->value)
        ->toContain(TipoMensaje::RESOLUCIÓN_DE_APELACIÓN->value);
});

test('el hilo completo conserva los tipos que ya mostraba', function (TipoMensaje $tipo) {
    expect(ConversacionDocenteService::TIPOS_HILO_COMPLETO)->toContain($tipo->value);
})->with([
    'Mensaje al profesor' => [TipoMensaje::MENSAJE_AL_PROFESOR],
    'Feedback' => [TipoMensaje::FEEDBACK],
    'Entrega de archivo' => [TipoMensaje::ENTREGA_DE_ARCHIVO],
    'Cancelación de entrega' => [TipoMensaje::CANCELACIÓN_DE_ENTREGA],
    'Evaluación' => [TipoMensaje::EVALUACIÓN],
    'Cierre de actividad' => [TipoMensaje::CIERRE_DE_ACTIVIDAD],
]);

test('todos los tipos del hilo completo son casos válidos de TipoMensaje', function () {
    foreach (ConversacionDocenteService::TIPOS_HILO_COMPLETO as $valor) {
        expect(TipoMensaje::tryFrom($valor))->not->toBeNull("«{$valor}» no es un TipoMensaje");
    }
});

test('el hilo completo no repite tipos', function () {
    $tipos = ConversacionDocenteService::TIPOS_HILO_COMPLETO;

    expect($tipos)->toHaveCount(count(array_unique($tipos)));
});
