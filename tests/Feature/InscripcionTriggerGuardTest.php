<?php

use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Plan;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Usuario\Contexto;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $facultad = Facultad::create([
        'nombre'      => 'Facultad Trigger Test ' . uniqid(),
        'id_contexto' => Contexto::create(['contexto_display' => 'Facultad Trigger Test'])->id_contexto,
    ]);
    $depto = Departamento::create([
        'nombre'      => 'Depto Trigger Test ' . uniqid(),
        'id_facultad' => $facultad->id_facultad,
        'id_contexto' => Contexto::create(['contexto_display' => 'Depto Trigger Test'])->id_contexto,
    ]);
    $this->carrera = Carrera::create([
        'nombre'          => 'Carrera Trigger Test ' . uniqid(),
        'codigo_carrera'  => rand(10000, 99999),
        'id_departamento' => $depto->id_departamento,
        'id_contexto'     => Contexto::create(['contexto_display' => 'Carrera Trigger Test'])->id_contexto,
    ]);
    $plan = Plan::create([
        'cod_plan'   => rand(1000, 9999),
        'agno_plan'  => 2026,
        'id_carrera' => $this->carrera->id_carrera,
    ]);
    $asignatura = Asignatura::create([
        'cod_asignatura'   => 'TRIG' . rand(100, 999),
        'nombre'           => 'Asignatura Trigger Test',
        'creditos_sct'     => 4,
        'horas_catedra'    => 2,
        'horas_taller'     => 2,
        'horas_laboratorio'=> 0,
        'horas_dirigidas'  => 0,
        'horas_autonomas'  => 0,
    ]);
    $this->asignacionPlan = AsignacionPlan::create([
        'id_plan'              => $plan->id_plan,
        'id_asignatura'        => $asignatura->id_asignatura,
        'agno_planificado'     => 1,
        'semestre_planificado' => 1,
        'id_contexto'          => 1,
    ]);

    $usuarioDocente = Usuario::create([
        'rut'         => rand(10000000, 99999999) . '-K',
        'username'    => 'doc_trig_' . uniqid(),
        'passhash'    => bcrypt('password'),
        'nombre1'     => 'Docente',
        'apellido1'   => 'Trigger',
        'esta_activo' => true,
    ]);
    $this->docente = Docente::create([
        'id_usuario'      => $usuarioDocente->id_usuario,
        'id_departamento' => $depto->id_departamento,
    ]);

    $this->tipoCatedra = TipoComponente::firstOrCreate(['tipo' => 'CATEDRA']);
    $this->tipoTaller = TipoComponente::firstOrCreate(['tipo' => 'TALLER']);
});

function crearCursoConDosComponentes($testCase): Curso
{
    $curso = Curso::create([
        'cod_curso'          => (int) DB::table('curso.curso')->max('cod_curso') + 1,
        'nombre'             => 'Curso Trigger Guard ' . uniqid(),
        'indice_grupo'       => 1,
        'fecha_inicio'       => '2026-03-01',
        'fecha_fin'          => '2026-07-31',
        'agno_real'          => 2026,
        'semestre_real'      => 1,
        'id_asignacion_plan' => $testCase->asignacionPlan->id_asignacion_plan,
        'id_contexto'        => Contexto::create(['contexto_display' => 'Curso Trig ' . uniqid()])->id_contexto,
        'id_docente_titular' => $testCase->docente->id_docente,
    ]);
    $curso->refresh();

    Componente::create([
        'id_curso'                          => $curso->id_curso,
        'id_tipo_componente'                => $testCase->tipoCatedra->id_tipo_componente,
        'id_contexto'                       => Contexto::create(['contexto_display' => 'Comp Catedra ' . uniqid()])->id_contexto,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => false,
        'porcentaje_aprobacion'             => 60,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    Componente::create([
        'id_curso'                          => $curso->id_curso,
        'id_tipo_componente'                => $testCase->tipoTaller->id_tipo_componente,
        'id_contexto'                       => Contexto::create(['contexto_display' => 'Comp Taller ' . uniqid()])->id_contexto,
        'genera_acta'                       => true,
        'aprobacion_obligatoria'            => false,
        'porcentaje_aprobacion'             => 60,
        'porcentaje_asistencia_obligatoria' => 75,
    ]);

    return $curso;
}

function crearEstudianteTrigger($testCase): Estudiante
{
    $usuario = Usuario::create([
        'rut'         => rand(10000000, 99999999) . '-5',
        'username'    => 'alum_trig_' . uniqid(),
        'passhash'    => bcrypt('password'),
        'nombre1'     => 'Estudiante',
        'apellido1'   => 'Trigger',
        'esta_activo' => true,
    ]);

    return Estudiante::create([
        'id_usuario' => $usuario->id_usuario,
        'id_carrera' => $testCase->carrera->id_carrera,
    ]);
}

test('inscripcion manual sin cod_inscripcion_uta autoinscribe en todas las componentes del curso', function () {
    $curso = crearCursoConDosComponentes($this);
    $estudiante = crearEstudianteTrigger($this);

    InscripcionCurso::create([
        'id_curso'            => $curso->id_curso,
        'id_estudiante'       => $estudiante->id_estudiante,
        'cod_inscripcion_uta' => null,
        'estado_inscripcion'  => 'INSCRITO',
        'fecha_inscripcion'   => now()->toDateString(),
    ]);

    $componentesInscritas = InscripcionComponente::where('id_estudiante', $estudiante->id_estudiante)->count();
    expect($componentesInscritas)->toBe(2);
});

test('inscripcion federada con cod_inscripcion_uta NO autoinscribe en componentes a ciegas', function () {
    $curso = crearCursoConDosComponentes($this);
    $estudiante = crearEstudianteTrigger($this);

    InscripcionCurso::create([
        'id_curso'            => $curso->id_curso,
        'id_estudiante'       => $estudiante->id_estudiante,
        'cod_inscripcion_uta' => 'UTA-' . rand(100000, 999999),
        'estado_inscripcion'  => 'INSCRITO',
        'fecha_inscripcion'   => now()->toDateString(),
    ]);

    $componentesInscritas = InscripcionComponente::where('id_estudiante', $estudiante->id_estudiante)->count();
    expect($componentesInscritas)->toBe(0);
});

test('inscripcion durante sincronizacion con flag utamed.skip_componente_trigger NO autoinscribe en componentes aun sin codigo UTA', function () {
    $curso = crearCursoConDosComponentes($this);
    $estudiante = crearEstudianteTrigger($this);

    DB::statement("SET utamed.skip_componente_trigger = 'on'");

    try {
        InscripcionCurso::create([
            'id_curso'            => $curso->id_curso,
            'id_estudiante'       => $estudiante->id_estudiante,
            'cod_inscripcion_uta' => null,
            'estado_inscripcion'  => 'INSCRITO',
            'fecha_inscripcion'   => now()->toDateString(),
        ]);
    } finally {
        DB::statement("SET utamed.skip_componente_trigger = 'off'");
    }

    $componentesInscritas = InscripcionComponente::where('id_estudiante', $estudiante->id_estudiante)->count();
    expect($componentesInscritas)->toBe(0);
});
