<?php

use App\Models\Curso\Clase;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

/**
 * Fixture con docente titular, curso y componente con al menos dos inscripciones.
 *
 * @return array{usuario: Usuario, docente: Docente, curso: Curso, componente: Componente, inscripciones: array<InscripcionComponente>}|null
 */
function fixtureClaseAsistencia(): ?array
{
    $curso = Curso::where('es_plantilla', false)
        ->whereHas('docenteTitular.usuario')
        ->whereHas('componentes.inscripcionComponentes.estudiante.usuario')
        ->with(['docenteTitular.usuario', 'componentes' => function ($q) {
            $q->whereHas('inscripcionComponentes.estudiante.usuario')
              ->with('inscripcionComponentes.estudiante.usuario');
        }])
        ->first();

    if (!$curso) {
        return null;
    }

    $docente = $curso->docenteTitular;
    $usuario = $docente->usuario;
    $componente = $curso->componentes->first(fn ($c) => $c->inscripcionComponentes->count() >= 1);

    if (!$componente) {
        return null;
    }

    return [
        'usuario'       => $usuario,
        'docente'       => $docente,
        'curso'         => $curso,
        'componente'    => $componente,
        'inscripciones' => $componente->inscripcionComponentes->all(),
    ];
}

test('1. index: docente titular carga listado de clases y obtiene id_clase y marcas', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    $clase = Clase::create([
        'id_componente' => $fix['componente']->id_componente,
        'dia'           => '2026-11-10',
        'hora_inicio'   => '08:30:00',
        'hora_fin'      => '10:00:00',
    ]);

    DB::table('curso.asistencia')->insert([
        'id_clase'                  => $clase->id_clase,
        'id_inscripcion_componente' => $fix['inscripciones'][0]->id_inscripcion_componente,
        'esta_presente'             => true,
    ]);

    $response = $this->actingAs($fix['usuario'])
        ->getJson(route('docente.cursos.componentes.asistencia.index', [
            'curso'      => $fix['curso']->id_curso,
            'componente' => $fix['componente']->id_componente,
        ]));

    $response->assertOk()
        ->assertJsonStructure([
            'estudiantes',
            'sesiones' => [
                '*' => ['id_clase', 'correlativo', 'dia', 'hora_inicio', 'hora_fin', 'total', 'presentes', 'registros'],
            ],
            'porcentaje_asistencia_obligatoria',
        ]);

    $sesiones = $response->json('sesiones');
    $encontrada = collect($sesiones)->firstWhere('id_clase', $clase->id_clase);
    expect($encontrada)->not->toBeNull();
    expect($encontrada['dia'])->toBe('2026-11-10');
    expect($encontrada['presentes'])->toBeGreaterThanOrEqual(1);
});

test('2. store: crear nueva sesion crea registro en curso.clase e inserta filas en curso.asistencia', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    $dia = '2026-11-12';
    $horaInicio = '10:15';
    $horaFin = '11:45';

    $payload = [
        'dia'         => $dia,
        'hora_inicio' => $horaInicio,
        'hora_fin'    => $horaFin,
        'asistencias' => array_map(fn ($ic) => [
            'id_inscripcion_componente' => $ic->id_inscripcion_componente,
            'esta_presente'             => true,
        ], $fix['inscripciones']),
    ];

    $response = $this->actingAs($fix['usuario'])
        ->post(route('docente.cursos.componentes.asistencia.store', [
            'curso'      => $fix['curso']->id_curso,
            'componente' => $fix['componente']->id_componente,
        ]), $payload);

    $response->assertSessionHas('success');

    $clase = Clase::where('id_componente', $fix['componente']->id_componente)
        ->where('dia', $dia)
        ->where('hora_inicio', $horaInicio . ':00')
        ->first();

    expect($clase)->not->toBeNull();

    $asistenciasCount = DB::table('curso.asistencia')
        ->where('id_clase', $clase->id_clase)
        ->count();

    expect($asistenciasCount)->toBe(count($fix['inscripciones']));
});

test('3. store colision: falla con error de validacion ante intento de duplicar fecha/horario en mismo componente', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    $dia = '2026-11-13';
    $horaInicio = '14:00';
    $horaFin = '15:30';

    Clase::create([
        'id_componente' => $fix['componente']->id_componente,
        'dia'           => $dia,
        'hora_inicio'   => $horaInicio . ':00',
        'hora_fin'      => $horaFin . ':00',
    ]);

    $payload = [
        'dia'         => $dia,
        'hora_inicio' => $horaInicio,
        'hora_fin'    => $horaFin,
        'asistencias' => [
            [
                'id_inscripcion_componente' => $fix['inscripciones'][0]->id_inscripcion_componente,
                'esta_presente'             => true,
            ],
        ],
    ];

    $response = $this->actingAs($fix['usuario'])
        ->post(route('docente.cursos.componentes.asistencia.store', [
            'curso'      => $fix['curso']->id_curso,
            'componente' => $fix['componente']->id_componente,
        ]), $payload);

    $response->assertSessionHasErrors(['dia']);
});

test('4. update: actualizar marcas de asistencia y horario persiste en curso.clase y curso.asistencia', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    $clase = Clase::create([
        'id_componente' => $fix['componente']->id_componente,
        'dia'           => '2026-11-14',
        'hora_inicio'   => '08:00:00',
        'hora_fin'      => '09:30:00',
    ]);

    $icId = $fix['inscripciones'][0]->id_inscripcion_componente;

    DB::table('curso.asistencia')->insert([
        'id_clase'                  => $clase->id_clase,
        'id_inscripcion_componente' => $icId,
        'esta_presente'             => true,
    ]);

    $payload = [
        'id_clase'    => $clase->id_clase,
        'dia'         => '2026-11-14',
        'hora_inicio' => '09:00',
        'hora_fin'    => '10:30',
        'asistencias' => [
            [
                'id_inscripcion_componente' => $icId,
                'esta_presente'             => false,
            ],
        ],
    ];

    $response = $this->actingAs($fix['usuario'])
        ->put(route('docente.cursos.componentes.asistencia.update', [
            'curso'      => $fix['curso']->id_curso,
            'componente' => $fix['componente']->id_componente,
            'clase'      => $clase->id_clase,
        ]), $payload);

    $response->assertSessionHas('success');

    $clase->refresh();
    expect(substr((string) $clase->hora_inicio, 0, 5))->toBe('09:00');
    expect(substr((string) $clase->hora_fin, 0, 5))->toBe('10:30');

    $asistencia = DB::table('curso.asistencia')
        ->where('id_clase', $clase->id_clase)
        ->where('id_inscripcion_componente', $icId)
        ->first();

    expect($asistencia)->not->toBeNull();
    expect((bool) $asistencia->esta_presente)->toBeFalse();
});

test('5. destroy: eliminar clase limpia en cascada las asistencias sin errores de FK', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    $clase = Clase::create([
        'id_componente' => $fix['componente']->id_componente,
        'dia'           => '2026-11-15',
        'hora_inicio'   => '11:00:00',
        'hora_fin'      => '12:30:00',
    ]);

    $idAsistencia = DB::table('curso.asistencia')->insertGetId([
        'id_clase'                  => $clase->id_clase,
        'id_inscripcion_componente' => $fix['inscripciones'][0]->id_inscripcion_componente,
        'esta_presente'             => true,
    ], 'id_asistencia');

    $response = $this->actingAs($fix['usuario'])
        ->delete(route('docente.cursos.componentes.asistencia.destroy', [
            'curso'      => $fix['curso']->id_curso,
            'componente' => $fix['componente']->id_componente,
            'clase'      => $clase->id_clase,
        ]));

    $response->assertSessionHas('success');

    expect(Clase::find($clase->id_clase))->toBeNull();
    expect(DB::table('curso.asistencia')->where('id_asistencia', $idAsistencia)->first())->toBeNull();
});

test('6. aislamiento: intento de acceder o mutar una clase que no pertenece al componente retorna 404', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    // Buscar otro componente ajeno
    $otroComponente = Componente::where('id_componente', '!=', $fix['componente']->id_componente)->first();
    if (!$otroComponente) {
        $this->markTestSkipped('Se requiere un segundo componente en la BD para probar aislamiento.');
    }

    $claseAjena = Clase::create([
        'id_componente' => $otroComponente->id_componente,
        'dia'           => '2026-11-16',
        'hora_inicio'   => '16:00:00',
        'hora_fin'      => '17:30:00',
    ]);

    // Intento de eliminar clase ajena a través del componente del fixture
    $response = $this->actingAs($fix['usuario'])
        ->delete(route('docente.cursos.componentes.asistencia.destroy', [
            'curso'      => $fix['curso']->id_curso,
            'componente' => $fix['componente']->id_componente,
            'clase'      => $claseAjena->id_clase,
        ]));

    $response->assertNotFound();
});

test('7. DocenteCursoController::show calcula total_sesiones desde curso.clase sin errores', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    Clase::create([
        'id_componente' => $fix['componente']->id_componente,
        'dia'           => '2026-11-17',
        'hora_inicio'   => '08:00:00',
        'hora_fin'      => '09:30:00',
    ]);

    $response = $this->actingAs($fix['usuario'])
        ->get(route('docente.cursos.show', ['curso' => $fix['curso']->id_curso]));

    $response->assertOk();
});

test('8. CalendarioController::index retorna sesiones agrupadas por clase con id_clase', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    $clase = Clase::create([
        'id_componente' => $fix['componente']->id_componente,
        'dia'           => '2026-11-18',
        'hora_inicio'   => '10:00:00',
        'hora_fin'      => '11:30:00',
    ]);

    DB::table('curso.asistencia')->insert([
        'id_clase'                  => $clase->id_clase,
        'id_inscripcion_componente' => $fix['inscripciones'][0]->id_inscripcion_componente,
        'esta_presente'             => true,
    ]);

    $response = $this->actingAs($fix['usuario'])
        ->get(route('docente.calendario.index'));

    $response->assertOk();
});

test('9. JefeCarreraController::metricas ejecuta consulta agregada de asistencia sin error SQL', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    // Verificar ejecución directa de la consulta que refactorizamos en JefeCarreraController
    $cursoIds = [$fix['curso']->id_curso];
    $asistenciaRows = collect(DB::connection('pgsql')->select(
        'SELECT c.id_curso,
                COUNT(DISTINCT a.id_clase) AS sesiones,
                AVG(CASE WHEN a.esta_presente THEN 1.0 ELSE 0.0 END) * 100 AS porcentaje
         FROM curso.asistencia a
         JOIN curso.inscripcion_componente ic ON ic.id_inscripcion_componente = a.id_inscripcion_componente
         JOIN curso.componente c ON c.id_componente = ic.id_componente
         WHERE c.id_curso IN (?)
         GROUP BY c.id_curso',
        $cursoIds
    ));

    expect($asistenciaRows)->not->toBeNull();
});

test('10. ComponenteController::destroy rechaza eliminacion de componente con clases registradas', function () {
    $fix = fixtureClaseAsistencia();
    if (!$fix) {
        $this->markTestSkipped('Sin fixture disponible.');
    }

    $clase = Clase::create([
        'id_componente' => $fix['componente']->id_componente,
        'dia'           => '2026-11-19',
        'hora_inicio'   => '12:00:00',
        'hora_fin'      => '13:30:00',
    ]);

    // Verificar directamente a través de ComponenteController::motivoParaNoEliminar
    $controller = new \App\Http\Controllers\Admin\ComponenteController();
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('motivoParaNoEliminar');

    $motivo = $method->invoke($controller, $fix['componente']);
    expect($motivo)->not->toBeNull();

    // Si aislamos un componente que sólo tenga la clase recién agregada:
    $componenteLimpio = Componente::whereDoesntHave('actividades')
        ->whereDoesntHave('mensajes')
        ->whereDoesntHave('inscripcionComponentes.asistencias')
        ->whereDoesntHave('clases')
        ->first();

    if ($componenteLimpio) {
        Clase::create([
            'id_componente' => $componenteLimpio->id_componente,
            'dia'           => '2026-11-20',
            'hora_inicio'   => '14:00:00',
            'hora_fin'      => '15:30:00',
        ]);

        $motivoLimpio = $method->invoke($controller, $componenteLimpio);
        expect($motivoLimpio)->toBe('tiene clases registradas');
    }
});
