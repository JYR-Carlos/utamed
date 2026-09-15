<?php

use App\Console\Commands\ForzarResetPasswordUsuario;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearUsuarioPrueba(array $datos = []): Usuario
{
    $sufijo = random_int(1000000, 9999999);
    $fechaCambio = array_key_exists('fecha_cambio_passhash', $datos)
        ? $datos['fecha_cambio_passhash']
        : now();
    unset($datos['fecha_cambio_passhash']);

    $usuario = Usuario::create(array_merge([
        'username' => "reset_test_{$sufijo}",
        'passhash' => Hash::make('clave_anterior_segura_123'),
        'email' => "reset_test_{$sufijo}@example.test",
        'rut' => "{$sufijo}-8",
        'nombre1' => 'Roberto',
        'nombre2' => 'Antonio',
        'apellido1' => 'González',
        'apellido2' => 'Vargas',
        'esta_activo' => true,
    ], $datos));

    if ($fechaCambio !== null) {
        $usuario->fecha_cambio_passhash = $fechaCambio;
        $usuario->save();
    }

    return $usuario;
}

it('extrae correctamente la contraseña desde diversos formatos de RUT', function () {
    $cmd = new ForzarResetPasswordUsuario();

    expect($cmd->extraerPasswordDesdeRut('18234567-8'))->toBe('18234567')
        ->and($cmd->extraerPasswordDesdeRut('18.234.567-8'))->toBe('18234567')
        ->and($cmd->extraerPasswordDesdeRut('9876543-K'))->toBe('9876543')
        ->and($cmd->extraerPasswordDesdeRut('9.876.543-k'))->toBe('9876543')
        ->and($cmd->extraerPasswordDesdeRut('12345678-9'))->toBe('12345678');
});

it('resetea la contraseña por ID de usuario y setea fecha_cambio_passhash en null', function () {
    $usuario = crearUsuarioPrueba([
        'rut' => '19876543-2',
        'fecha_cambio_passhash' => now(),
    ]);

    expect($usuario->debeCambiarPassword())->toBeFalse();

    $this->artisan('usuario:forzar-reset-password', [
        'termino' => (string) $usuario->id_usuario,
        '--force' => true,
    ])
        ->expectsOutputToContain('CONTRASEÑA RESETEADA CON ÉXITO')
        ->expectsOutputToContain('19876543')
        ->assertSuccessful();

    $usuario->refresh();

    // Contraseña debe ser el RUT sin DV, sin guion y sin puntos: "19876543"
    expect(Hash::check('19876543', $usuario->passhash))->toBeTrue()
        ->and($usuario->fecha_cambio_passhash)->toBeNull()
        ->and($usuario->debeCambiarPassword())->toBeTrue()
        ->and($usuario->motivoCambioPasswordObligatorio())->toBe(Usuario::CAMBIO_PASSWORD_PRIMER_INGRESO);
});

it('resetea la contraseña buscando por RUT con puntos y guion', function () {
    $usuario = crearUsuarioPrueba([
        'rut' => '17654321-K',
        'fecha_cambio_passhash' => now(),
    ]);

    $this->artisan('usuario:reset-password', [
        'termino' => '17.654.321-k',
        '--force' => true,
    ])
        ->expectsOutputToContain('CONTRASEÑA RESETEADA CON ÉXITO')
        ->assertSuccessful();

    $usuario->refresh();

    // Contraseña debe ser "17654321"
    expect(Hash::check('17654321', $usuario->passhash))->toBeTrue()
        ->and($usuario->fecha_cambio_passhash)->toBeNull()
        ->and($usuario->debeCambiarPassword())->toBeTrue();
});

it('maneja colisiones mostrando lista y permite seleccionar el usuario con su número', function () {
    $tokenUnico = 'ColisionUnica' . random_int(1000, 9999);

    $suf1 = random_int(10000000, 19999999);
    $suf2 = random_int(20000000, 29999999);

    $u1 = crearUsuarioPrueba([
        'nombre1' => $tokenUnico,
        'apellido1' => 'Primero',
        'rut' => "{$suf1}-1",
        'fecha_cambio_passhash' => now(),
    ]);

    $u2 = crearUsuarioPrueba([
        'nombre1' => $tokenUnico,
        'apellido1' => 'Segundo',
        'rut' => "{$suf2}-2",
        'fecha_cambio_passhash' => now(),
    ]);

    $expectedPasswordU2 = (string) $suf2;

    // Seleccionamos la opción 2 (usuario $u2)
    $this->artisan('usuario:forzar-reset-password', [
        'termino' => $tokenUnico,
        '--force' => true,
    ])
        ->expectsQuestion('Seleccione el número del usuario a modificar (1 - 2, 0 para cancelar)', '2')
        ->expectsOutputToContain('CONTRASEÑA RESETEADA CON ÉXITO')
        ->expectsOutputToContain($expectedPasswordU2)
        ->assertSuccessful();

    $u1->refresh();
    $u2->refresh();

    // El usuario 2 debe haber sido modificado
    expect(Hash::check($expectedPasswordU2, $u2->passhash))->toBeTrue()
        ->and($u2->fecha_cambio_passhash)->toBeNull();

    // El usuario 1 NO debe haber sido alterado
    expect(Hash::check('clave_anterior_segura_123', $u1->passhash))->toBeTrue()
        ->and($u1->fecha_cambio_passhash)->not->toBeNull();
});

it('permite cancelar la operación ante colisiones ingresando 0', function () {
    $tokenUnico = 'Cancelacion' . random_int(1000, 9999);

    $u1 = crearUsuarioPrueba(['nombre1' => $tokenUnico, 'apellido1' => 'Uno']);
    $u2 = crearUsuarioPrueba(['nombre1' => $tokenUnico, 'apellido1' => 'Dos']);

    $this->artisan('usuario:forzar-reset-password', [
        'termino' => $tokenUnico,
    ])
        ->expectsQuestion('Seleccione el número del usuario a modificar (1 - 2, 0 para cancelar)', '0')
        ->expectsOutputToContain('Operación cancelada')
        ->assertSuccessful();

    $u1->refresh();
    $u2->refresh();

    expect(Hash::check('clave_anterior_segura_123', $u1->passhash))->toBeTrue()
        ->and(Hash::check('clave_anterior_segura_123', $u2->passhash))->toBeTrue();
});
