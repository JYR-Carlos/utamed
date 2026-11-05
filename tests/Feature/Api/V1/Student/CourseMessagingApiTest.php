<?php

use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use App\Services\MensajeriaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(DatabaseTransactions::class);

function crearEstudianteMensajero(array $usuarioAttrs = []): array
{
    static $count = 200;
    $count++;

    $usuario = Usuario::factory()->create(array_merge([
        'rut'                   => '13.456.789-' . ($count % 10),
        'nombre1'               => 'ALUMNO',
        'apellido1'             => 'PRUEBA',
        'email'                 => 'alumno.mensajero.' . $count . '.' . uniqid() . '@alumnos.uta.cl',
        'passhash'              => Hash::make('clave-123'),
        'esta_activo'           => true,
        'fecha_cambio_passhash' => now(),
    ], $usuarioAttrs));

    $estudiante = Estudiante::factory()->create([
        'id_usuario'   => $usuario->id_usuario,
        'id_carrera'   => 1,
        'agno_ingreso' => 2024,
    ]);

    UsuarioRolAsignacion::create([
        'id_usuario'               => $usuario->id_usuario,
        'id_rol'                   => Rol::where('nombre', 'Estudiante')->value('id_rol'),
        'id_contexto'              => 1,
        'asignado_por'             => $usuario->id_usuario,
        'creado_por'               => $usuario->id_usuario,
        'fecha_inicio_planificada' => now()->subDay(),
        'fecha_fin_planificada'    => now()->addYear(),
        'esta_activo'              => true,
        'fue_eliminado'            => false,
    ]);

    return [$usuario, $estudiante];
}

function crearDocenteMensajero(string $nombre, string $apellido): array
{
    static $docCount = 200;
    $docCount++;

    $usuario = Usuario::factory()->create([
        'rut'                   => '14.567.890-' . ($docCount % 10),
        'nombre1'               => $nombre,
        'apellido1'             => $apellido,
        'email'                 => 'doc.' . strtolower($nombre) . '.' . $docCount . '.' . uniqid() . '@docentes.uta.cl',
        'passhash'              => Hash::make('clave-123'),
        'esta_activo'           => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $docente = Docente::create([
        'id_usuario' => $usuario->id_usuario,
    ]);

    return [$usuario, $docente];
}

function crearEstructuraCursoMensajero(): array
{
    static $cursoNum = 500;
    $cursoNum++;

    [$usuarioDocTitular, $docenteTitular] = crearDocenteMensajero('Carlos', 'DocenteTitular');
    [$usuarioDocColegiado, $docenteColegiado] = crearDocenteMensajero('Laura', 'DocenteColegiado');

    $curso = Curso::factory()->create([
        'nombre'             => 'Curso Mensajero Test ' . $cursoNum,
        'cod_curso'          => 4000 + $cursoNum,
        'indice_grupo'       => 1,
        'semestre_real'      => 2,
        'agno_real'          => 2026,
        'id_docente_titular' => $docenteTitular->id_docente,
        'fecha_inicio'       => Carbon::parse('2026-08-01'),
        'fecha_fin'          => Carbon::parse('2026-12-15'),
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $tipoTaller = TipoComponente::firstOrCreate(['tipo' => 'Taller']);

    $compCatedra = Componente::create([
        'id_curso'                         => $curso->id_curso,
        'id_tipo_componente'               => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion'            => 60.0,
        'aprobacion_obligatoria'           => false,
        'porcentaje_asistencia_obligatoria'=> 75.0,
        'genera_acta'                      => true,
    ]);

    $compTaller = Componente::create([
        'id_curso'                         => $curso->id_curso,
        'id_tipo_componente'               => $tipoTaller->id_tipo_componente,
        'porcentaje_aprobacion'            => 60.0,
        'aprobacion_obligatoria'           => false,
        'porcentaje_asistencia_obligatoria'=> 75.0,
        'genera_acta'                      => true,
    ]);

    // Docentes en Cátedra
    DB::table('curso.docente_componente')->insert([
        [
            'id_componente' => $compCatedra->id_componente,
            'id_docente'    => $docenteTitular->id_docente,
            'es_titular'    => true,
        ],
        [
            'id_componente' => $compCatedra->id_componente,
            'id_docente'    => $docenteColegiado->id_docente,
            'es_titular'    => false,
        ],
    ]);

    // Docentes en Taller (sólo colegiado)
    DB::table('curso.docente_componente')->insert([
        [
            'id_componente' => $compTaller->id_componente,
            'id_docente'    => $docenteColegiado->id_docente,
            'es_titular'    => false,
        ],
    ]);

    return [
        'curso'                => $curso,
        'compCatedra'          => $compCatedra,
        'compTaller'           => $compTaller,
        'usuarioDocTitular'    => $usuarioDocTitular,
        'docenteTitular'       => $docenteTitular,
        'usuarioDocColegiado'  => $usuarioDocColegiado,
        'docenteColegiado'     => $docenteColegiado,
    ];
}

it('rechaza con 401 si la peticion no esta autenticada', function () {
    $response = $this->getJson('/api/v1/estudiante/cursos/1/mensajero');
    $response->assertStatus(401);

    $responsePost = $this->postJson('/api/v1/estudiante/cursos/1/mensajero/componentes/1/mensaje', [
        'mensaje' => 'Hola profesor',
    ]);
    $responsePost->assertStatus(401);
});

it('rechaza con 403 si el usuario autenticado no tiene rol estudiante', function () {
    [$usuarioDoc] = crearDocenteMensajero('Pedro', 'Profesor');
    Sanctum::actingAs($usuarioDoc);

    $response = $this->getJson('/api/v1/estudiante/cursos/1/mensajero');
    $response->assertStatus(403);
});

it('rechaza con 423 si el estudiante tiene la clave vencida', function () {
    [$usuarioEst] = crearEstudianteMensajero(['fecha_cambio_passhash' => now()->subMonths(7)]);

    $token = $this->postJson('/api/v1/auth/login', [
        'rut'      => $usuarioEst->rut,
        'password' => 'clave-123',
    ])->assertOk()->json('data.token');

    $response = $this->withToken($token)->getJson('/api/v1/estudiante/cursos/1/mensajero');
    $response->assertStatus(423)
        ->assertJson([
            'message' => 'Debes actualizar tu contraseña antes de continuar.',
        ]);
});

it('rechaza con 403 Forbidden si el estudiante no esta inscrito en el curso', function () {
    [$usuarioEst] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];

    $response = $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero");
    $response->assertStatus(403)
        ->assertJson(['message' => 'No estás inscrito en este curso.']);
});

it('retorna 200 con estructura consolidada de mensajeria y componentes', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];
    $compTaller = $setup['compTaller'];
    $usuarioDocTitular = $setup['usuarioDocTitular'];

    // Inscribir al alumno en el curso (con skip de trigger para controlar qué componentes inscribe)
    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    // Crear una difusión y un mensaje privado
    $mensajeriaService = app(MensajeriaService::class);
    $mensajeriaService->enviarDifusion(
        idComponente: (int) $compCatedra->id_componente,
        idUsuarioEmisor: (int) $usuarioDocTitular->id_usuario,
        mensaje: 'Bienvenidos al curso',
        tema: 'Aviso General',
    );

    $mensajeriaService->enviarIndividual(
        idComponente: (int) $compCatedra->id_componente,
        idUsuarioEmisor: (int) $usuarioDocTitular->id_usuario,
        idUsuarioReceptor: (int) $usuarioEst->id_usuario,
        idUsuarioAlumno: (int) $usuarioEst->id_usuario,
        mensaje: 'Estimado alumno, recuerde la lectura.',
        tema: 'Bienvenida',
    );

    $response = $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero?marcar_leido=false");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'curso' => ['id_curso', 'nombre', 'cod_curso', 'cod_asignatura'],
                'componentes' => [
                    '*' => [
                        'id_componente',
                        'tipo',
                        'no_leidos',
                        'docentes' => [
                            '*' => ['id_usuario', 'nombre', 'es_titular'],
                        ],
                        'difusiones' => [
                            '*' => ['id_mensaje', 'tema', 'mensaje', 'fecha_creacion', 'emisor', 'es_mio'],
                        ],
                        'mensajes' => [
                            '*' => ['id_mensaje', 'tema', 'mensaje', 'fecha_creacion', 'emisor', 'receptor', 'es_alumno', 'es_mio'],
                        ],
                    ],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['curso']['id_curso'])->toBe((int) $curso->id_curso);
    expect(count($data['componentes']))->toBe(1);
    expect($data['componentes'][0]['no_leidos'])->toBe(2);
    expect(count($data['componentes'][0]['difusiones']))->toBe(1);
    expect(count($data['componentes'][0]['mensajes']))->toBe(1);
    expect($data['componentes'][0]['difusiones'][0]['es_mio'])->toBeFalse();
    expect($data['componentes'][0]['mensajes'][0]['es_mio'])->toBeFalse();
    expect($data['componentes'][0]['mensajes'][0]['es_alumno'])->toBeFalse();
});

it('marca automaticamente como leidos los mensajes al consultar mensajeria sin parametro marcar_leido', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];
    $usuarioDocTitular = $setup['usuarioDocTitular'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    $mensajeriaService = app(MensajeriaService::class);
    $idDifusion = $mensajeriaService->enviarDifusion(
        idComponente: (int) $compCatedra->id_componente,
        idUsuarioEmisor: (int) $usuarioDocTitular->id_usuario,
        mensaje: 'Aviso de prueba',
        tema: 'Aviso',
    );

    // Consulta con marcar_leido por defecto (true)
    $response = $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero");
    $response->assertStatus(200);

    // En la primera respuesta, el contador no_leidos refleja el estado antes del marcado
    expect($response->json('data.componentes.0.no_leidos'))->toBe(1);

    // Verifica que se haya insertado en curso.interaccion_mensaje
    $leido = DB::table('curso.interaccion_mensaje')
        ->where('id_mensaje', $idDifusion)
        ->where('id_usuario_lector', $usuarioEst->id_usuario)
        ->exists();
    expect($leido)->toBeTrue();

    // Consulta subsecuente: ahora el no_leidos es 0
    $segunda = $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero?marcar_leido=false");
    expect($segunda->json('data.componentes.0.no_leidos'))->toBe(0);
});

it('no altera marcas de lectura cuando marcar_leido=false', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];
    $usuarioDocTitular = $setup['usuarioDocTitular'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    $mensajeriaService = app(MensajeriaService::class);
    $idDifusion = $mensajeriaService->enviarDifusion(
        idComponente: (int) $compCatedra->id_componente,
        idUsuarioEmisor: (int) $usuarioDocTitular->id_usuario,
        mensaje: 'Aviso no leído',
        tema: 'Aviso',
    );

    $response = $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero?marcar_leido=false");
    $response->assertStatus(200);

    $leido = DB::table('curso.interaccion_mensaje')
        ->where('id_mensaje', $idDifusion)
        ->where('id_usuario_lector', $usuarioEst->id_usuario)
        ->exists();
    expect($leido)->toBeFalse();
});

it('retorna componentes vacios si el alumno no tiene componentes inscritos en el curso', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    $response = $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero");
    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'curso' => [
                    'id_curso' => (int) $curso->id_curso,
                ],
                'componentes' => [],
            ],
        ]);
});

it('envia un mensaje exitosamente asignando al docente titular por defecto (POST)', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];
    $usuarioDocTitular = $setup['usuarioDocTitular'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    $response = $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero/componentes/{$compCatedra->id_componente}/mensaje", [
        'mensaje' => 'Hola profesor, tengo una duda sobre la materia.',
        'tema'    => 'Duda Unidad 1',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Mensaje enviado correctamente.',
            'data' => [
                'tema'      => 'Duda Unidad 1',
                'mensaje'   => 'Hola profesor, tengo una duda sobre la materia.',
                'receptor'  => trim($usuarioDocTitular->nombre1 . ' ' . $usuarioDocTitular->apellido1),
                'es_mio'    => true,
                'es_alumno' => true,
            ],
        ]);

    $this->assertDatabaseHas('curso.mensaje', [
        'id_componente'       => $compCatedra->id_componente,
        'id_usuario_emisor'   => $usuarioEst->id_usuario,
        'id_usuario_receptor' => $usuarioDocTitular->id_usuario,
        'tipo_mensaje'        => 'MENSAJE_INDIVIDUAL',
        'mensaje'             => 'Hola profesor, tengo una duda sobre la materia.',
    ]);
});

it('envia un mensaje asignando al docente seleccionado explicitamente (POST)', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];
    $usuarioDocColegiado = $setup['usuarioDocColegiado'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    $response = $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero/componentes/{$compCatedra->id_componente}/mensaje", [
        'mensaje'             => 'Estimada profesora Laura, consulta sobre el práctico.',
        'id_usuario_receptor' => $usuarioDocColegiado->id_usuario,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'receptor'  => trim($usuarioDocColegiado->nombre1 . ' ' . $usuarioDocColegiado->apellido1),
                'es_mio'    => true,
                'es_alumno' => true,
            ],
        ]);

    $this->assertDatabaseHas('curso.mensaje', [
        'id_componente'       => $compCatedra->id_componente,
        'id_usuario_emisor'   => $usuarioEst->id_usuario,
        'id_usuario_receptor' => $usuarioDocColegiado->id_usuario,
    ]);
});

it('rechaza con 403 si el alumno intenta enviar mensaje a un componente donde no esta inscrito', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];
    $compTaller = $setup['compTaller'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);

    // Inscrito sólo en Cátedra, intenta enviar a Taller
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    $response = $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero/componentes/{$compTaller->id_componente}/mensaje", [
        'mensaje' => 'Intento en componente no inscrito',
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'No estás inscrito en este componente.']);
});

it('rechaza con 422 si el componente no tiene docentes asignados', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    // Desvincular docentes del curso y componente
    DB::table('curso.docente_componente')->where('id_componente', $compCatedra->id_componente)->delete();

    // Actualizar curso.id_docente_titular a un id que no existe en docente
    DB::statement("SET session_replication_role = replica");
    DB::table('curso.curso')->where('id_curso', $curso->id_curso)->update(['id_docente_titular' => 99999999]);
    DB::statement("SET session_replication_role = DEFAULT");

    $response = $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero/componentes/{$compCatedra->id_componente}/mensaje", [
        'mensaje' => 'Hola a nadie',
    ]);

    $response->assertStatus(422)
        ->assertJson(['message' => 'Este componente todavía no tiene docentes asignados.']);
});

it('valida que el mensaje sea obligatorio y cumpla longitudes permitidas', function () {
    [$usuarioEst, $estudiante] = crearEstudianteMensajero();
    Sanctum::actingAs($usuarioEst);

    $setup = crearEstructuraCursoMensajero();
    $curso = $setup['curso'];
    $compCatedra = $setup['compCatedra'];

    DB::statement("SET utamed.skip_componente_trigger = 'on'");
    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso'      => $curso->id_curso,
        'estado'        => 'INSCRITO',
    ]);
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);
    DB::statement("SET utamed.skip_componente_trigger = 'off'");

    // Vacío
    $response = $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero/componentes/{$compCatedra->id_componente}/mensaje", []);
    $response->assertStatus(422)->assertJsonValidationErrors(['mensaje']);

    // Menor a 2 caracteres
    $responseCorto = $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero/componentes/{$compCatedra->id_componente}/mensaje", [
        'mensaje' => 'A',
    ]);
    $responseCorto->assertStatus(422)->assertJsonValidationErrors(['mensaje']);

    // Mayor a 2000 caracteres
    $responseLargo = $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/mensajero/componentes/{$compCatedra->id_componente}/mensaje", [
        'mensaje' => str_repeat('X', 2001),
    ]);
    $responseLargo->assertStatus(422)->assertJsonValidationErrors(['mensaje']);
});
