<?php

use App\Models\Usuario\Docente;
use App\Models\Usuario\Rol;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function estudianteApi(array $attrs = []): Usuario
{
    $user = Usuario::factory()->create(array_merge([
        'rut' => '12345678-5',
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ], $attrs));
    Estudiante::factory()->create(['id_usuario' => $user->id_usuario]);
    UsuarioRolAsignacion::create([
        'id_usuario' => $user->id_usuario,
        'id_rol' => Rol::where('nombre', 'Estudiante')->value('id_rol'),
        'id_contexto' => 1,
        'asignado_por' => $user->id_usuario,
        'creado_por' => $user->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    return $user;
}

test('login con RUT en distintos formatos entrega token Bearer', function (string $rut) {
    estudianteApi();

    $this->postJson('/api/v1/auth/login', ['rut' => $rut, 'password' => 'secreto-123'])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.requiere_cambio_password', false)
        ->assertJsonStructure(['data' => ['token', 'usuario' => ['id', 'rut'], 'estudiante' => ['id']]]);
})->with(['12.345.678-5', '12345678-5', '123456785']);

test('credenciales inválidas responden 401', function () {
    estudianteApi();

    $this->postJson('/api/v1/auth/login', ['rut' => '12345678-5', 'password' => 'mala'])->assertUnauthorized();
});

test('el login no acepta email', function () {
    $user = estudianteApi();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secreto-123'])
        ->assertUnprocessable();
});

test('un docente sin perfil de estudiante recibe 403', function () {
    $user = estudianteApi();
    Estudiante::where('id_usuario', $user->id_usuario)->delete();
    Docente::factory()->create(['id_usuario' => $user->id_usuario]);

    $this->postJson('/api/v1/auth/login', ['rut' => '12345678-5', 'password' => 'secreto-123'])->assertForbidden();
});

test('un estudiante inactivo recibe 403', function () {
    estudianteApi(['esta_activo' => false]);

    $this->postJson('/api/v1/auth/login', ['rut' => '12345678-5', 'password' => 'secreto-123'])->assertForbidden();
});

test('clave vencida o primer ingreso activa requiere_cambio_password', function (?string $fecha) {
    estudianteApi(['fecha_cambio_passhash' => $fecha ? now()->subMonths(7) : null]);

    $this->postJson('/api/v1/auth/login', ['rut' => '12345678-5', 'password' => 'secreto-123'])
        ->assertOk()
        ->assertJsonPath('data.requiere_cambio_password', true);
})->with(['vencida' => 'x', 'primer ingreso' => null]);

test('me responde con el estudiante y logout revoca el token', function () {
    estudianteApi();
    $token = $this->postJson('/api/v1/auth/login', ['rut' => '12345678-5', 'password' => 'secreto-123'])
        ->json('data.token');

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.usuario.rut', '12345678-5');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});
