<?php

use App\Models\Administrativo\Carrera;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Mensajeria\Mensaje;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearEstudianteDashboard(array $usuarioAttrs = [], array $estudianteAttrs = []): array
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

function crearDocenteUsuario(string $nombre = 'DOCENTE', string $apellido = 'TITULAR'): array
{
    $u = Usuario::factory()->create([
        'nombre1' => $nombre,
        'apellido1' => $apellido,
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $d = Docente::factory()->create([
        'id_usuario' => $u->id_usuario,
    ]);

    return [$u, $d];
}

test('dashboard retorna 200 con la estructura completa para estudiante autenticado', function () {
    Carbon::setTestNow('2026-11-02 12:00:00'); // Mes 11 -> Semestre 2, Agno 2026

    [$usuario, $estudiante] = crearEstudianteDashboard();

    $curso = Curso::factory()->create([
        'semestre_real' => 2,
        'agno_real' => 2026,
    ]);

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/dashboard');

    $res->assertOk()
        ->assertJsonStructure([
            'data' => [
                'periodo' => ['semestre', 'agno'],
                'cursos' => [
                    '*' => [
                        'id_curso',
                        'nombre',
                        'cod_curso',
                        'cod_asignatura',
                        'asignatura_nombre',
                        'carrera_nombre',
                        'fecha_inicio',
                        'fecha_fin',
                        'semestre_real',
                        'agno_real',
                        'docente',
                        'mensajes_no_leidos',
                    ],
                ],
                'proximas_a_vencer',
                'notas_recientes',
                'mensajes_no_leidos' => [
                    'total',
                    'cursos',
                ],
            ],
        ])
        ->assertJson([
            'data' => [
                'periodo' => [
                    'semestre' => 2,
                    'agno' => 2026,
                ],
            ],
        ]);

    Carbon::setTestNow();
});

test('dashboard filtra solo cursos INSCRITO del semestre y agno vigentes', function () {
    Carbon::setTestNow('2026-11-02 12:00:00'); // Semestre 2, Agno 2026

    [$usuario, $estudiante] = crearEstudianteDashboard();

    // Curso vigente e inscrito -> Debe aparecer
    $cursoVigente = Curso::factory()->create(['semestre_real' => 2, 'agno_real' => 2026, 'nombre' => 'Curso Vigente']);
    InscripcionCurso::create([
        'id_curso' => $cursoVigente->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    // Curso de otro semestre -> Excluido
    $cursoOtroSemestre = Curso::factory()->create(['semestre_real' => 1, 'agno_real' => 2026, 'nombre' => 'Curso Semestre 1']);
    InscripcionCurso::create([
        'id_curso' => $cursoOtroSemestre->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    // Curso vigente pero RETIRADO -> Excluido
    $cursoRetirado = Curso::factory()->create(['semestre_real' => 2, 'agno_real' => 2026, 'nombre' => 'Curso Retirado']);
    InscripcionCurso::create([
        'id_curso' => $cursoRetirado->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'RETIRADO',
        'num_intento' => 1,
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/dashboard');

    $res->assertOk();
    $cursosRetornados = collect($res->json('data.cursos'));

    expect($cursosRetornados->pluck('id_curso')->all())->toEqual([$cursoVigente->id_curso]);

    Carbon::setTestNow();
});

test('dashboard resuelve el docente titular del componente donde está inscrito el estudiante', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario, $estudiante] = crearEstudianteDashboard();

    $curso = Curso::factory()->create(['semestre_real' => 2, 'agno_real' => 2026]);
    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    // Crear dos componentes (secciones)
    $comp1 = Componente::factory()->create(['id_curso' => $curso->id_curso]);
    $comp2 = Componente::factory()->create(['id_curso' => $curso->id_curso]);

    // Docente de sección 1
    [$uDoc1, $doc1] = crearDocenteUsuario('CAROLINA', 'GARCIA');
    DocenteComponente::factory()->create([
        'id_componente' => $comp1->id_componente,
        'id_docente' => $doc1->id_docente,
        'es_titular' => true,
    ]);

    // Docente de sección 2
    [$uDoc2, $doc2] = crearDocenteUsuario('ROBERTO', 'SANCHEZ');
    DocenteComponente::factory()->create([
        'id_componente' => $comp2->id_componente,
        'id_docente' => $doc2->id_docente,
        'es_titular' => true,
    ]);

    // Inscribir al estudiante únicamente en comp2
    InscripcionComponente::factory()->create([
        'id_componente' => $comp2->id_componente,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/dashboard');

    $res->assertOk();
    $cursoData = collect($res->json('data.cursos'))->firstWhere('id_curso', $curso->id_curso);
    expect($cursoData['docente'])->toBe('ROBERTO SANCHEZ');

    Carbon::setTestNow();
});

test('dashboard no contiene claves de integración SGEQ ni flags de ayudante web', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario] = crearEstudianteDashboard();

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/dashboard')->assertOk();

    $json = $res->json();
    $data = $json['data'];

    expect(array_key_exists('puedeAbrirSgeq', $data))->toBeFalse();
    expect(array_key_exists('sgeq', $data))->toBeFalse();
    expect(array_key_exists('isAyudante', $data))->toBeFalse();

    Carbon::setTestNow();
});

test('dashboard rechaza con 423 Locked cuando la contraseña expiró o es primer ingreso', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario] = crearEstudianteDashboard([
        'fecha_cambio_passhash' => now()->subDays(190), // Expiró límite de 180 días
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/dashboard');

    // Bloqueado por middleware force_password_change_api
    $res->assertStatus(423)
        ->assertJson([
            'message' => 'Debes actualizar tu contraseña antes de continuar.',
            'error' => 'password_change_required',
            'motivo' => 'vencida',
        ]);

    Carbon::setTestNow();
});

test('dashboard rechaza con 401 si no hay token y 403 si el rol no es estudiante', function () {
    // 1. Sin token Bearer
    $this->getJson('/api/v1/estudiante/dashboard')->assertUnauthorized();

    // 2. Con usuario Docente (sin rol Estudiante activo)
    $usuarioDocente = Usuario::factory()->create([
        'rut' => '11.111.111-1',
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);
    Docente::factory()->create(['id_usuario' => $usuarioDocente->id_usuario]);
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

    $tokenDocente = $this->postJson('/api/v1/auth/login', [
        'rut' => '11.111.111-1',
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $this->withToken($tokenDocente)
        ->getJson('/api/v1/estudiante/dashboard')
        ->assertForbidden();
});
