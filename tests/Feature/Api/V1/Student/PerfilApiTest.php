<?php

use App\Models\Administrativo\Carrera;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\FotoIntranetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearEstudianteConPerfil(array $usuarioAttrs = [], array $estudianteAttrs = []): array
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
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ], $usuarioAttrs));

    $estudiante = Estudiante::factory()->create(array_merge([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $carrera->id_carrera,
        'agno_ingreso' => 2024,
    ], $estudianteAttrs));

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

    return [$usuario, $estudiante, $carrera];
}

test('perfil retorna 200 OK con todos los datos requeridos e inmutables del estudiante', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteConPerfil();

    $curso1 = Curso::factory()->create();
    $curso2 = Curso::factory()->create();
    $curso3 = Curso::factory()->create();

    // Inscribir 2 cursos con estado INSCRITO y 1 con RETIRADO
    InscripcionCurso::create([
        'id_curso' => $curso1->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);
    InscripcionCurso::create([
        'id_curso' => $curso2->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);
    InscripcionCurso::create([
        'id_curso' => $curso3->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'RETIRADO',
        'num_intento' => 1,
    ]);

    $loginRes = $this->postJson('/api/v1/auth/login', [
        'rut' => '12.345.678-5',
        'password' => 'secreto-123',
    ])->assertOk();

    $token = $loginRes->json('data.token');

    $fotoService = app(FotoIntranetService::class);
    $urlEsperadaFoto = $fotoService->getImagenPerfilURL($usuario->rut);

    $response = $this->withToken($token)->getJson('/api/v1/estudiante/perfil');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id_usuario' => $usuario->id_usuario,
                'id_estudiante' => $estudiante->id_estudiante,
                'nombre_completo' => $usuario->nombre_completo,
                'rut' => '12345678-5',
                'rut_formateado' => '12.345.678-5',
                'email' => 'juan.perez@alumnos.uta.cl',
                'correo_institucional' => 'juan.perez@alumnos.uta.cl',
                'carrera' => $carrera->nombre,
                'id_carrera' => $carrera->id_carrera,
                'cohorte' => 2024,
                'agno_ingreso' => 2024,
                'total_cursos_inscritos' => 2,
                'total_cursos' => 2,
                'url_foto_perfil' => $urlEsperadaFoto,
            ],
        ]);
});

test('perfil rechaza peticiones sin autenticación con 401', function () {
    $this->getJson('/api/v1/estudiante/perfil')->assertUnauthorized();
});

test('perfil rechaza a usuarios con rol docente sin perfil estudiante con 403', function () {
    [$usuario] = crearEstudianteConPerfil();
    Estudiante::where('id_usuario', $usuario->id_usuario)->delete();
    Docente::factory()->create(['id_usuario' => $usuario->id_usuario]);

    $this->postJson('/api/v1/auth/login', [
        'rut' => '12.345.678-5',
        'password' => 'secreto-123',
    ])->assertForbidden();

    // Intentar directamente simulando autenticación
    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/estudiante/perfil')
        ->assertForbidden();
});

test('perfil rechaza a estudiantes inactivos con 403', function () {
    [$usuario] = crearEstudianteConPerfil(['esta_activo' => false]);

    $this->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/estudiante/perfil')
        ->assertForbidden();
});

test('perfil blindado: mutaciones HTTP responden 405 Method Not Allowed', function () {
    [$usuario] = crearEstudianteConPerfil();

    $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/estudiante/perfil', ['nombre' => 'Hack'])
        ->assertMethodNotAllowed();

    $this->actingAs($usuario, 'sanctum')
        ->putJson('/api/v1/estudiante/perfil', ['email' => 'hack@uta.cl'])
        ->assertMethodNotAllowed();

    $this->actingAs($usuario, 'sanctum')
        ->patchJson('/api/v1/estudiante/perfil', ['telefono' => '123456'])
        ->assertMethodNotAllowed();

    $this->actingAs($usuario, 'sanctum')
        ->deleteJson('/api/v1/estudiante/perfil')
        ->assertMethodNotAllowed();
});
