<?php

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\DocenteComponente;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\Programa;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
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

function crearEstudianteFicha(array $usuarioAttrs = [], array $estudianteAttrs = []): array
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

function crearDocenteUsuarioFicha(string $nombre = 'DOCENTE', string $apellido = 'TITULAR'): array
{
    $u = Usuario::factory()->create([
        'nombre1' => $nombre,
        'nombre2' => null,
        'apellido1' => $apellido,
        'apellido2' => null,
        'email' => strtolower($nombre) . '.' . strtolower($apellido) . '@uta.cl',
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $d = Docente::factory()->create([
        'id_usuario' => $u->id_usuario,
    ]);

    return [$u, $d];
}

function crearEstructuraCursoFicha(Carrera $carrera, ?Docente $docenteTitular = null): array
{
    $plan = Plan::create([
        'id_carrera' => $carrera->id_carrera,
        'agno_plan' => 2024,
        'version_plan' => 1,
    ]);

    $asignatura = Asignatura::create([
        'cod_asignatura' => 'MED-101',
        'nombre' => 'Medicina Interna I',
        'creditos_sct' => 6,
        'horas_catedra' => 2,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 1,
        'horas_autonomas' => 3,
        'descripcion' => 'Tipo: FE',
    ]);

    $asignacion = AsignacionPlan::create([
        'id_plan' => $plan->id_plan,
        'id_asignatura' => $asignatura->id_asignatura,
        'agno_planificado' => 2024,
        'semestre_planificado' => 2,
    ]);

    if (!$docenteTitular) {
        [, $docenteTitular] = crearDocenteUsuarioFicha('TITULAR', 'DEFAULT');
    }

    $curso = Curso::factory()->create([
        'nombre' => 'Medicina Interna I',
        'cod_curso' => 1011,
        'indice_grupo' => 1,
        'semestre_real' => 2,
        'agno_real' => 2026,
        'id_asignacion_plan' => $asignacion->id_asignacion_plan,
        'id_docente_titular' => $docenteTitular->id_docente,
        'fecha_inicio' => Carbon::parse('2026-08-01'),
        'fecha_fin' => Carbon::parse('2026-12-15'),
    ]);

    return [$curso, $asignatura, $plan];
}

it('rechaza peticion no autenticada con 401', function () {
    $this->getJson('/api/v1/estudiante/cursos/1')
        ->assertStatus(401);
});

it('rechaza con 403 a usuario sin rol o perfil estudiante', function () {
    $usuario = Usuario::factory()->create([
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $token = $usuario->createToken('test-docente')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/estudiante/cursos/1')
        ->assertStatus(403);
});

it('retorna 404 cuando el curso solicitado no existe', function () {
    [$usuario] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/estudiante/cursos/999999')
        ->assertStatus(404);
});

it('rechaza con 403 Forbidden cuando el alumno no esta inscrito en el curso (Anti-IDOR)', function () {
    [$usuario] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$uDoc, $docente] = crearDocenteUsuarioFicha();
    $carrera = Carrera::first();
    [$curso] = crearEstructuraCursoFicha($carrera, $docente);

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(403)
        ->assertJson([
            'message' => 'No estás inscrito en este curso.',
        ]);
});

it('rechaza con 403 Forbidden cuando el alumno tiene estado de inscripcion RETIRADO', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$uDoc, $docente] = crearDocenteUsuarioFicha();
    [$curso] = crearEstructuraCursoFicha($carrera, $docente);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'RETIRADO',
    ]);

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(403)
        ->assertJson([
            'message' => 'No estás inscrito en este curso.',
        ]);
});

it('retorna 200 con la estructura completa de curso, asignatura, componentes y unidades', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$uDoc, $docente] = crearDocenteUsuarioFicha('CARLOS', 'MÉDICO');
    [$curso, $asignatura] = crearEstructuraCursoFicha($carrera, $docente);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    DocenteComponente::create([
        'id_componente' => $componente->id_componente,
        'id_docente' => $docente->id_docente,
        'es_titular' => true,
    ]);

    Unidad::create([
        'id_curso' => $curso->id_curso,
        'num_unidad' => 1,
        'nombre' => 'Introducción a la Patología Médica',
        'descripcion' => 'Bases fisiopatológicas y semiología básica',
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(200);

    $response->assertJsonStructure([
        'data' => [
            'curso' => [
                'id_curso',
                'nombre',
                'cod_curso',
                'cod_asignatura',
                'asignatura_nombre',
                'etiqueta',
                'carrera_nombre',
                'letra_grupo',
                'semestre_real',
                'agno_real',
                'fecha_inicio',
                'fecha_fin',
                'docente_titular',
                'tiene_programa',
                'imagen_url',
            ],
            'asignatura' => [
                'cod_asignatura',
                'nombre',
                'creditos_sct',
                'horas_catedra',
                'horas_taller',
                'horas_laboratorio',
                'horas_dirigidas',
                'horas_autonomas',
                'descripcion',
            ],
            'componentes' => [
                '*' => [
                    'id_componente',
                    'tipo',
                    'porcentaje_aprobacion',
                    'aprobacion_obligatoria',
                    'porcentaje_asistencia_obligatoria',
                    'genera_acta',
                    'docentes' => [
                        '*' => [
                            'nombre',
                            'email',
                            'es_titular',
                        ],
                    ],
                ],
            ],
            'unidades' => [
                '*' => [
                    'id_unidad',
                    'num_unidad',
                    'nombre',
                    'descripcion',
                ],
            ],
        ],
    ]);

    $data = $response->json('data');
    expect($data['curso']['id_curso'])->toBe($curso->id_curso);
    expect($data['curso']['nombre'])->toBe('Medicina Interna I');
    expect($data['curso']['docente_titular'])->toBe('CARLOS MÉDICO');
    expect($data['asignatura']['creditos_sct'])->toBe(6);
    expect($data['asignatura']['horas_catedra'])->toBe(2);
    expect($data['componentes'])->toHaveCount(1);
    expect($data['componentes'][0]['tipo'])->toBe('Cátedra');
    expect($data['componentes'][0]['docentes'][0]['nombre'])->toBe('CARLOS MÉDICO');
    expect($data['componentes'][0]['docentes'][0]['es_titular'])->toBeTrue();
    expect($data['unidades'])->toHaveCount(1);
    expect($data['unidades'][0]['num_unidad'])->toBe(1);
});

it('filtra y expone exclusivamente los componentes en los que el alumno esta inscrito', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$curso] = crearEstructuraCursoFicha($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $tipoTaller = TipoComponente::firstOrCreate(['tipo' => 'Taller']);

    $compCatedra = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 50.0,
        'aprobacion_obligatoria' => true,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    $compTaller = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoTaller->id_tipo_componente,
        'porcentaje_aprobacion' => 50.0,
        'aprobacion_obligatoria' => true,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    // Inscribe al alumno solo en compCatedra
    InscripcionComponente::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_componente' => $compCatedra->id_componente,
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(200);

    $componentes = $response->json('data.componentes');
    expect($componentes)->toHaveCount(1);
    expect($componentes[0]['id_componente'])->toBe($compCatedra->id_componente);
    expect($componentes[0]['tipo'])->toBe('Cátedra');
});

it('aplica fallback a todos los componentes del curso si no existen filas de inscripcion_componente', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$curso] = crearEstructuraCursoFicha($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $tipoTaller = TipoComponente::firstOrCreate(['tipo' => 'Taller']);

    $c1 = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    $c2 = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoTaller->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    // Ninguna inscripción en inscripcion_componentes para este estudiante
    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(200);

    $componentes = $response->json('data.componentes');
    expect($componentes)->toHaveCount(2);
});

it('expone creditos SCT y horas de catedra, taller y laboratorio de la asignatura', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$curso, $asignatura] = crearEstructuraCursoFicha($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(200);

    $asig = $response->json('data.asignatura');
    expect($asig['cod_asignatura'])->toBe('MED-101');
    expect($asig['nombre'])->toBe('Medicina Interna I');
    expect($asig['creditos_sct'])->toBe(6);
    expect($asig['horas_catedra'])->toBe(2);
    expect($asig['horas_taller'])->toBe(2);
    expect($asig['horas_laboratorio'])->toBe(0);
    expect($asig['horas_dirigidas'])->toBe(1);
    expect($asig['horas_autonomas'])->toBe(3);
    expect($asig['descripcion'])->toBe('Tipo: FE');
});

it('expone lista de docentes asignados con indicador de titular por componente', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$curso] = crearEstructuraCursoFicha($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoCatedra->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    [$uDoc1, $docente1] = crearDocenteUsuarioFicha('ANA', 'TITULAR');
    [$uDoc2, $docente2] = crearDocenteUsuarioFicha('PEDRO', 'AYUDANTE');

    DocenteComponente::create([
        'id_componente' => $componente->id_componente,
        'id_docente' => $docente2->id_docente,
        'es_titular' => false,
    ]);

    DocenteComponente::create([
        'id_componente' => $componente->id_componente,
        'id_docente' => $docente1->id_docente,
        'es_titular' => true,
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(200);

    $docentes = $response->json('data.componentes.0.docentes');
    expect($docentes)->toHaveCount(2);
    // El titular debe ordenarse primero
    expect($docentes[0]['nombre'])->toBe('ANA TITULAR');
    expect($docentes[0]['es_titular'])->toBeTrue();
    expect($docentes[1]['nombre'])->toBe('PEDRO AYUDANTE');
    expect($docentes[1]['es_titular'])->toBeFalse();
});

it('expone unidades de aprendizaje ordenadas por num_unidad', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$curso] = crearEstructuraCursoFicha($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    Unidad::create([
        'id_curso' => $curso->id_curso,
        'num_unidad' => 2,
        'nombre' => 'Unidad 2: Patologías Respiratorias',
    ]);

    Unidad::create([
        'id_curso' => $curso->id_curso,
        'num_unidad' => 1,
        'nombre' => 'Unidad 1: Fisiología Básica',
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(200);

    $unidades = $response->json('data.unidades');
    expect($unidades)->toHaveCount(2);
    expect($unidades[0]['num_unidad'])->toBe(1);
    expect($unidades[0]['nombre'])->toBe('Unidad 1: Fisiología Básica');
    expect($unidades[1]['num_unidad'])->toBe(2);
    expect($unidades[1]['nombre'])->toBe('Unidad 2: Patologías Respiratorias');
});

it('aplica fallback a unidades de syllabus cuando no hay registros en la tabla unidades', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteFicha();
    $token = $usuario->createToken('test')->plainTextToken;

    [$curso] = crearEstructuraCursoFicha($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    Programa::create([
        'id_curso' => $curso->id_curso,
        'version_programa' => 1,
        'creado_por' => $usuario->id_usuario,
        'es_actual' => true,
        'estado' => 'APROBADO',
        'data_syllabus' => [
            'secciones' => [
                'seccionVI' => [
                    'unidades' => [
                        [
                            'num_unidad' => 1,
                            'titulo' => 'Unidad Syllabus 1',
                            'descripcion' => 'Descripción Syllabus 1',
                        ],
                        [
                            'num_unidad' => 2,
                            'titulo' => 'Unidad Syllabus 2',
                            'descripcion' => 'Descripción Syllabus 2',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}")
        ->assertStatus(200);

    $unidades = $response->json('data.unidades');
    expect($unidades)->toHaveCount(2);
    expect($unidades[0]['id_unidad'])->toBeNull();
    expect($unidades[0]['num_unidad'])->toBe(1);
    expect($unidades[0]['nombre'])->toBe('Unidad Syllabus 1');
    expect($unidades[0]['descripcion'])->toBe('Descripción Syllabus 1');
    expect($response->json('data.curso.tiene_programa'))->toBeTrue();
});
