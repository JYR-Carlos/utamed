<?php

use App\Models\Administrativo\Carrera;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearEstudianteParaPassword(array $usuarioAttrs = []): Usuario
{
    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test',
        'codigo_carrera' => 19999,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $usuario = Usuario::factory()->create(array_merge([
        'rut' => '12.345.678-5',
        'nombre1' => 'JUAN',
        'nombre2' => 'CARLOS',
        'apellido1' => 'PÉREZ',
        'apellido2' => 'GONZÁLEZ',
        'email' => 'juan.perez@alumnos.uta.cl',
        'passhash' => Hash::make('Actual123!'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => null,
    ], $usuarioAttrs));

    Estudiante::factory()->create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'agno_ingreso' => 2024,
    ]);

    UsuarioRolAsignacion::create([
        'id_usuario' => $usuario->id_usuario,
        'id_rol' => Rol::where('nombre', 'Estudiante')->value('id_rol'),
        'id_contexto' => 1,
        'asignado_por' => $usuario->id_usuario,
        'creado_por' => $usuario->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    return $usuario;
}

test('permite cambiar clave obligatoria en primer ingreso o vencida y levanta requerimiento', function (?callable $modificadorFecha) {
    $fecha = $modificadorFecha ? $modificadorFecha() : null;
    $usuario = crearEstudianteParaPassword([
        'fecha_cambio_passhash' => $fecha,
    ]);

    $token = $usuario->createToken('test-app')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/v1/auth/cambio-password-obligatorio', [
            'current_password' => 'Actual123!',
            'password' => 'NuevaClaveSegura1!',
            'password_confirmation' => 'NuevaClaveSegura1!',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.requiere_cambio_password', false)
        ->assertJsonPath('data.motivo_cambio_password', null)
        ->assertJsonPath('message', 'Contraseña actualizada correctamente.');

    $usuario->refresh();
    expect(Hash::check('NuevaClaveSegura1!', $usuario->passhash))->toBeTrue()
        ->and($usuario->fecha_cambio_passhash)->not->toBeNull()
        ->and($usuario->debeCambiarPassword())->toBeFalse();
})->with([
    'primer ingreso' => [null],
    'clave vencida' => [fn () => now()->subMonths(7)],
]);

test('rechaza con 403 Forbidden si el estudiante tiene su contraseña vigente', function () {
    $usuario = crearEstudianteParaPassword([
        'fecha_cambio_passhash' => now()->subMonths(2),
    ]);

    $token = $usuario->createToken('test-app')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/v1/auth/cambio-password-obligatorio', [
            'current_password' => 'Actual123!',
            'password' => 'NuevaClaveSegura1!',
            'password_confirmation' => 'NuevaClaveSegura1!',
        ]);

    $response->assertForbidden();
    expect($response->json('message'))->toContain('vigente');

    $usuario->refresh();
    expect(Hash::check('Actual123!', $usuario->passhash))->toBeTrue();
});

test('rechaza con 422 si la contraseña actual es errónea', function () {
    $usuario = crearEstudianteParaPassword([
        'fecha_cambio_passhash' => null,
    ]);

    $token = $usuario->createToken('test-app')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/v1/auth/cambio-password-obligatorio', [
            'current_password' => 'ClaveErronea123!',
            'password' => 'NuevaClaveSegura1!',
            'password_confirmation' => 'NuevaClaveSegura1!',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

test('rechaza con 422 si la nueva contraseña no cumple complejidad o confirmación', function () {
    $usuario = crearEstudianteParaPassword([
        'fecha_cambio_passhash' => null,
    ]);

    $token = $usuario->createToken('test-app')->plainTextToken;

    // Débil
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/v1/auth/cambio-password-obligatorio', [
            'current_password' => 'Actual123!',
            'password' => 'simple',
            'password_confirmation' => 'simple',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    // Sin confirmación coincidente
    $responseNoMatch = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/v1/auth/cambio-password-obligatorio', [
            'current_password' => 'Actual123!',
            'password' => 'NuevaClaveSegura1!',
            'password_confirmation' => 'OtraCosa123!',
        ]);

    $responseNoMatch->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('rechaza con 401 si no hay token y 403 si el rol no es estudiante activo', function () {
    // 401 sin token
    $this->postJson('/api/v1/auth/cambio-password-obligatorio', [
        'current_password' => 'Actual123!',
        'password' => 'NuevaClaveSegura1!',
        'password_confirmation' => 'NuevaClaveSegura1!',
    ])->assertUnauthorized();

    // Docente sin rol de estudiante
    $usuarioDocente = Usuario::factory()->create([
        'passhash' => Hash::make('Actual123!'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => null,
    ]);

    Docente::factory()->create([
        'id_usuario' => $usuarioDocente->id_usuario,
    ]);

    UsuarioRolAsignacion::create([
        'id_usuario' => $usuarioDocente->id_usuario,
        'id_rol' => Rol::where('nombre', 'Docente')->value('id_rol'),
        'id_contexto' => 1,
        'asignado_por' => $usuarioDocente->id_usuario,
        'creado_por' => $usuarioDocente->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    $tokenDocente = $usuarioDocente->createToken('test-docente')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer ' . $tokenDocente)
        ->postJson('/api/v1/auth/cambio-password-obligatorio', [
            'current_password' => 'Actual123!',
            'password' => 'NuevaClaveSegura1!',
            'password_confirmation' => 'NuevaClaveSegura1!',
        ])->assertForbidden();
});

test('la API no bloquea otras rutas cuando la contraseña está vencida (solo avisa)', function () {
    $usuario = crearEstudianteParaPassword([
        'fecha_cambio_passhash' => now()->subMonths(8),
    ]);

    $token = $usuario->createToken('test-app')->plainTextToken;

    // /estudiante/perfil debe responder 200 OK aunque la clave esté vencida
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/v1/estudiante/perfil');

    $response->assertOk();
});
