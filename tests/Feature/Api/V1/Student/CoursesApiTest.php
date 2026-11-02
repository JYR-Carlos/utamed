<?php

use App\Enums\DB\TipoMensajeCurso;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\Mensaje;
use App\Models\Curso\Programa;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

uses(DatabaseTransactions::class);

function crearEstudianteCursos(array $usuarioAttrs = [], array $estudianteAttrs = []): array
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

function crearDocenteUsuarioCursos(string $nombre = 'DOCENTE', string $apellido = 'TITULAR'): array
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

test('cursos retorna 200 con la estructura completa para el período vigente por defecto', function () {
    Carbon::setTestNow('2026-11-02 12:00:00'); // Mes 11 -> Semestre 2, Agno 2026

    [$usuario, $estudiante, $carrera] = crearEstudianteCursos();

    $curso = Curso::factory()->create([
        'nombre' => 'Medicina Interna I',
        'indice_grupo' => 1, // genera letra_grupo 'A'
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

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/cursos');

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
                        'letra_grupo',
                        'fecha_inicio',
                        'fecha_fin',
                        'semestre_real',
                        'agno_real',
                        'docente',
                        'mensajes_no_leidos',
                        'tiene_programa',
                        'imagen_url',
                    ],
                ],
            ],
        ])
        ->assertJson([
            'data' => [
                'periodo' => [
                    'semestre' => 2,
                    'agno' => 2026,
                ],
                'cursos' => [
                    [
                        'id_curso' => $curso->id_curso,
                        'nombre' => 'Medicina Interna I',
                        'letra_grupo' => 'A',
                        'semestre_real' => 2,
                        'agno_real' => 2026,
                        'imagen_url' => '/img/utamed.webp',
                    ],
                ],
            ],
        ]);

    Carbon::setTestNow();
});

test('cursos soporta filtrado opcional por semestre y agno', function () {
    Carbon::setTestNow('2026-11-02 12:00:00'); // Período actual 2/2026

    [$usuario, $estudiante] = crearEstudianteCursos();

    $curso2025 = Curso::factory()->create([
        'nombre' => 'Curso Pasado 2025',
        'semestre_real' => 1,
        'agno_real' => 2025,
    ]);
    InscripcionCurso::create([
        'id_curso' => $curso2025->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    $curso2026 = Curso::factory()->create([
        'nombre' => 'Curso Presente 2026',
        'semestre_real' => 2,
        'agno_real' => 2026,
    ]);
    InscripcionCurso::create([
        'id_curso' => $curso2026->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    // 1. Por defecto solo entrega el 2/2026 (período vigente)
    $resDefecto = $this->withToken($token)->getJson('/api/v1/estudiante/cursos')->assertOk();
    $cursosDefecto = $resDefecto->json('data.cursos');
    expect($cursosDefecto)->toHaveCount(1);
    expect($cursosDefecto[0]['id_curso'])->toBe($curso2026->id_curso);

    // 2. Consulta explícita a semestre=1 y agno=2025
    $resHistorico = $this->withToken($token)->getJson('/api/v1/estudiante/cursos?semestre=1&agno=2025')->assertOk();
    $resHistorico->assertJson([
        'data' => [
            'periodo' => [
                'semestre' => 1,
                'agno' => 2025,
            ],
        ],
    ]);
    $cursosHistoricos = $resHistorico->json('data.cursos');
    expect($cursosHistoricos)->toHaveCount(1);
    expect($cursosHistoricos[0]['id_curso'])->toBe($curso2025->id_curso);

    Carbon::setTestNow();
});

test('cursos se ordenan alfabéticamente de forma ascendente por nombre', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario, $estudiante] = crearEstudianteCursos();

    $cursoZ = Curso::factory()->create(['nombre' => 'Zoología Médica', 'semestre_real' => 2, 'agno_real' => 2026]);
    $cursoA = Curso::factory()->create(['nombre' => 'Anatomía Humana', 'semestre_real' => 2, 'agno_real' => 2026]);
    $cursoF = Curso::factory()->create(['nombre' => 'Fisiología I', 'semestre_real' => 2, 'agno_real' => 2026]);

    foreach ([$cursoZ, $cursoA, $cursoF] as $c) {
        InscripcionCurso::create([
            'id_curso' => $c->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
            'num_intento' => 1,
        ]);
    }

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/cursos')->assertOk();
    $nombres = collect($res->json('data.cursos'))->pluck('nombre')->all();

    expect($nombres)->toBe([
        'Anatomía Humana',
        'Fisiología I',
        'Zoología Médica',
    ]);

    Carbon::setTestNow();
});

test('cursos incluye flag tiene_programa cuando el syllabus está aprobado o completo', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario, $estudiante] = crearEstudianteCursos();

    $cursoConPrograma = Curso::factory()->create(['nombre' => 'Con Syllabus', 'semestre_real' => 2, 'agno_real' => 2026]);
    $cursoSinPrograma = Curso::factory()->create(['nombre' => 'Sin Syllabus', 'semestre_real' => 2, 'agno_real' => 2026]);

    InscripcionCurso::create([
        'id_curso' => $cursoConPrograma->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    InscripcionCurso::create([
        'id_curso' => $cursoSinPrograma->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    // Programa para el primero
    Programa::create([
        'id_curso' => $cursoConPrograma->id_curso,
        'version_programa' => 1,
        'creado_por' => $usuario->id_usuario,
        'es_actual' => true,
        'estado' => 'APROBADO',
        'fecha_creacion' => now(),
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/cursos')->assertOk();
    $cursos = collect($res->json('data.cursos'));

    $cCon = $cursos->firstWhere('id_curso', $cursoConPrograma->id_curso);
    $cSin = $cursos->firstWhere('id_curso', $cursoSinPrograma->id_curso);

    expect($cCon['tiene_programa'])->toBeTrue();
    expect($cSin['tiene_programa'])->toBeFalse();

    Carbon::setTestNow();
});

test('cursos incluye conteo de mensajes no leidos por curso', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario, $estudiante] = crearEstudianteCursos();

    $curso = Curso::factory()->create(['nombre' => 'Epidemiología', 'semestre_real' => 2, 'agno_real' => 2026]);
    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado_inscripcion' => 'INSCRITO',
        'num_intento' => 1,
    ]);

    $tipoComponente = TipoComponente::where('tipo', 'Cátedra')->first()
        ?? TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);

    $contexto = Contexto::firstOrCreate(['contexto_display' => 'Comp Cat ' . uniqid()]);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoComponente->id_tipo_componente,
        'id_contexto' => $contexto->id_contexto,
        'genera_acta' => true,
        'porcentaje_aprobacion' => 60,
        'aprobacion_obligatoria' => true,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    InscripcionComponente::create([
        'id_componente' => $componente->id_componente,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    [$uDoc, $doc] = crearDocenteUsuarioCursos();

    // Crear dos mensajes no leídos en el componente enviados por el docente
    Mensaje::create([
        'id_componente' => $componente->id_componente,
        'id_usuario_emisor' => $uDoc->id_usuario,
        'tema' => 'Tema 1',
        'mensaje' => 'Mensaje de prueba 1',
        'tipo_mensaje' => TipoMensajeCurso::MENSAJE_PARA_TODO_EL_CURSO,
    ]);
    Mensaje::create([
        'id_componente' => $componente->id_componente,
        'id_usuario_emisor' => $uDoc->id_usuario,
        'tema' => 'Tema 2',
        'mensaje' => 'Mensaje de prueba 2',
        'tipo_mensaje' => TipoMensajeCurso::MENSAJE_PARA_TODO_EL_CURSO,
    ]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/cursos')->assertOk();
    $cursoData = collect($res->json('data.cursos'))->firstWhere('id_curso', $curso->id_curso);

    expect($cursoData['mensajes_no_leidos'])->toBe(2);

    Carbon::setTestNow();
});

test('cursos retorna lista vacia cuando el estudiante no tiene inscripciones en el periodo solicitado', function () {
    Carbon::setTestNow('2026-11-02 12:00:00');

    [$usuario] = crearEstudianteCursos();

    $token = $this->postJson('/api/v1/auth/login', [
        'rut' => $usuario->rut,
        'password' => 'secreto-123',
    ])->assertOk()->json('data.token');

    $res = $this->withToken($token)->getJson('/api/v1/estudiante/cursos')->assertOk();

    $res->assertJson([
        'data' => [
            'periodo' => [
                'semestre' => 2,
                'agno' => 2026,
            ],
            'cursos' => [],
        ],
    ]);

    Carbon::setTestNow();
});

test('cursos rechaza peticion no autenticada con 401', function () {
    $this->getJson('/api/v1/estudiante/cursos')->assertUnauthorized();
});

test('cursos rechaza con 403 a usuario sin rol o perfil estudiante', function () {
    $usuarioDocente = Usuario::factory()->create([
        'rut' => '11.111.111-1',
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);
    Docente::factory()->create(['id_usuario' => $usuarioDocente->id_usuario]);
    $rolDocenteId = Rol::where('nombre', 'Docente Titular')->value('id_rol') ?? 1;
    UsuarioRolAsignacion::create([
        'id_usuario' => $usuarioDocente->id_usuario,
        'id_rol' => $rolDocenteId,
        'id_contexto' => 1,
        'asignado_por' => $usuarioDocente->id_usuario,
        'creado_por' => $usuarioDocente->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada' => now()->addYear(),
        'esta_activo' => true,
        'fue_eliminado' => false,
    ]);

    $this->actingAs($usuarioDocente, 'sanctum')
        ->getJson('/api/v1/estudiante/cursos')
        ->assertForbidden();
});
