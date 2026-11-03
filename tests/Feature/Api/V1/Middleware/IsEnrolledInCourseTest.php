<?php

use App\Api\V1\Middleware\IsEnrolledInCourse;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(DatabaseTransactions::class);

function crearEstudianteParaMiddleware(array $usuarioAttrs = [], array $estudianteAttrs = []): array
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
        'email' => 'juan.perez.middleware@alumnos.uta.cl',
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

function crearCursoParaMiddleware(Carrera $carrera): Curso
{
    $plan = Plan::create([
        'id_carrera' => $carrera->id_carrera,
        'agno_plan' => 2024,
        'version_plan' => 1,
    ]);

    static $seq = 100;
    $seq++;

    $asignatura = Asignatura::create([
        'cod_asignatura' => 'MT' . $seq,
        'nombre' => 'Asignatura Prueba ' . $seq,
        'creditos_sct' => 4,
        'horas_catedra' => 2,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 1,
        'horas_autonomas' => 3,
    ]);

    $asignacion = AsignacionPlan::create([
        'id_plan' => $plan->id_plan,
        'id_asignatura' => $asignatura->id_asignatura,
        'agno_planificado' => 2024,
        'semestre_planificado' => 1,
    ]);

    return Curso::factory()->create([
        'nombre' => 'Curso Test Middleware ' . $seq,
        'cod_curso' => (string) $seq,
        'indice_grupo' => 1,
        'semestre_real' => 1,
        'agno_real' => 2026,
        'id_asignacion_plan' => $asignacion->id_asignacion_plan,
        'fecha_inicio' => Carbon::parse('2026-03-01'),
        'fecha_fin' => Carbon::parse('2026-07-15'),
    ]);
}

beforeEach(function () {
    // Definir rutas de prueba en el grupo 'api' o con SubstituteBindings para resolver Route Model Binding
    Route::middleware(['api', 'auth:sanctum', 'is_enrolled_in_course'])->group(function () {
        Route::get('/test-route-middleware-curso-obj/{curso}', function (Curso $curso) {
            return response()->json(['message' => 'Acceso concedido', 'id_curso' => $curso->id_curso]);
        });

        Route::get('/test-route-middleware-curso-id/{curso}', function ($curso) {
            return response()->json(['message' => 'Acceso concedido', 'curso_param' => $curso]);
        })->whereNumber('curso');
    });
});

it('permite el paso cuando el estudiante esta inscrito con estado INSCRITO (con Route Model Binding)', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteParaMiddleware();
    $curso = crearCursoParaMiddleware($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/test-route-middleware-curso-obj/{$curso->id_curso}")
        ->assertStatus(200)
        ->assertJson([
            'message' => 'Acceso concedido',
            'id_curso' => $curso->id_curso,
        ]);
});

it('permite el paso cuando el estudiante esta inscrito con estado INSCRITO (sin Route Model Binding, resolviendo id)', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteParaMiddleware();
    $curso = crearCursoParaMiddleware($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'INSCRITO',
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/test-route-middleware-curso-id/{$curso->id_curso}")
        ->assertStatus(200)
        ->assertJson([
            'message' => 'Acceso concedido',
        ]);
});

it('rechaza con 403 cuando el estudiante no esta inscrito en el curso', function () {
    [$usuario, , $carrera] = crearEstudianteParaMiddleware();
    $curso = crearCursoParaMiddleware($carrera);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/test-route-middleware-curso-obj/{$curso->id_curso}")
        ->assertStatus(403)
        ->assertJson([
            'message' => IsEnrolledInCourse::NO_INSCRITO_MENSAJE,
        ]);
});

it('rechaza con 403 cuando el estudiante esta en estado RETIRADO u otro distinto a INSCRITO', function () {
    [$usuario, $estudiante, $carrera] = crearEstudianteParaMiddleware();
    $curso = crearCursoParaMiddleware($carrera);

    InscripcionCurso::create([
        'id_estudiante' => $estudiante->id_estudiante,
        'id_curso' => $curso->id_curso,
        'estado_inscripcion' => 'RETIRADO',
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/test-route-middleware-curso-obj/{$curso->id_curso}")
        ->assertStatus(403)
        ->assertJson([
            'message' => IsEnrolledInCourse::NO_INSCRITO_MENSAJE,
        ]);
});

it('rechaza con 403 cuando el usuario autenticado no es estudiante', function () {
    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test',
        'codigo_carrera' => 19999,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);
    $curso = crearCursoParaMiddleware($carrera);

    $docenteUser = Usuario::factory()->create([
        'rut' => '11.111.111-1',
        'email' => 'docente@uta.cl',
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    Docente::factory()->create([
        'id_usuario' => $docenteUser->id_usuario,
    ]);

    $token = $docenteUser->createToken('test-docente')->plainTextToken;

    $this->withToken($token)
        ->getJson("/test-route-middleware-curso-obj/{$curso->id_curso}")
        ->assertStatus(403)
        ->assertJson([
            'message' => IsEnrolledInCourse::NO_INSCRITO_MENSAJE,
        ]);
});

it('retorna 404 cuando el curso id no existe', function () {
    [$usuario] = crearEstudianteParaMiddleware();
    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/test-route-middleware-curso-id/99999999')
        ->assertStatus(404);
});
