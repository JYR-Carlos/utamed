<?php

use App\Enums\DB\EstadoRubrica;
use App\Enums\DB\TipoActividad;
use App\Enums\DB\TipoMensaje;
use App\Models\Administrativo\AsignacionPlan;
use App\Models\Administrativo\Asignatura;
use App\Models\Administrativo\Carrera;
use App\Models\Administrativo\Plan;
use App\Models\Agenda\Actividad;
use App\Models\Agenda\ActividadAsignadaGrupo;
use App\Models\Agenda\Agenda;
use App\Models\Agenda\Evaluacion;
use App\Models\Agenda\IntegranteGrupo;
use App\Models\Agenda\Rubrica;
use App\Models\Curso\Componente;
use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionComponente;
use App\Models\Curso\InscripcionCurso;
use App\Models\Curso\TipoComponente;
use App\Models\Curso\Unidad;
use App\Models\Operaciones\Archivo;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Rol;
use App\Models\Usuario\Usuario;
use App\Models\Usuario\UsuarioRolAsignacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

function crearEstudianteEvaluacion(array $usuarioAttrs = [], array $estudianteAttrs = []): array
{
    static $estCount = 1;
    $estCount++;

    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test',
        'codigo_carrera' => 19999,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $usuario = Usuario::factory()->create(array_merge([
        'rut' => '12.345.678-' . ($estCount % 10),
        'nombre1' => 'JUAN',
        'nombre2' => 'CARLOS',
        'apellido1' => 'PÉREZ',
        'apellido2' => 'GONZÁLEZ',
        'email' => 'juan.perez.' . $estCount . '.' . uniqid() . '@alumnos.uta.cl',
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

function crearDocenteEvaluador(string $rut, string $nombre, string $apellido): array
{
    static $docCount = 1;
    $docCount++;

    $usuario = Usuario::factory()->create([
        'rut' => $rut,
        'nombre1' => $nombre,
        'apellido1' => $apellido,
        'email' => strtolower($nombre).'.'.strtolower($apellido).'.'.$docCount.'@docentes.uta.cl',
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
    ]);

    $docente = Docente::create([
        'id_usuario' => $usuario->id_usuario,
    ]);

    return [$usuario, $docente];
}

function crearCursoYComponenteEvaluacion(): array
{
    static $cursoCount = 1;
    $cursoCount++;

    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test',
        'codigo_carrera' => 19999,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $plan = Plan::create([
        'id_carrera' => $carrera->id_carrera,
        'agno_plan' => 2024,
        'version_plan' => 1,
    ]);

    $asignatura = Asignatura::create([
        'cod_asignatura' => 'M' . substr(strval(rand(1000000, 9999999)), 0, 8),
        'nombre' => 'Medicina Interna I',
        'creditos_sct' => 6,
        'horas_catedra' => 2,
        'horas_taller' => 2,
        'horas_laboratorio' => 0,
        'horas_dirigidas' => 1,
        'horas_autonomas' => 3,
        'descripcion' => 'Tipo: FE',
    ]);

    $asignacionPlan = AsignacionPlan::create([
        'id_plan' => $plan->id_plan,
        'id_asignatura' => $asignatura->id_asignatura,
        'agno_planificado' => 2024,
        'semestre_planificado' => 2,
    ]);

    $curso = Curso::factory()->create([
        'nombre' => 'Medicina Interna I',
        'cod_curso' => 2000 + $cursoCount,
        'indice_grupo' => 1,
        'semestre_real' => 2,
        'agno_real' => 2026,
        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
        'fecha_inicio' => Carbon::parse('2026-08-01'),
        'fecha_fin' => Carbon::parse('2026-12-15'),
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

    return [$curso, $componente];
}

function crearActividadEvaluacionTest(array $attrs): Actividad
{
    $idCurso = Componente::where('id_componente', $attrs['id_componente'])->value('id_curso');
    $unidad = Unidad::firstOrCreate([
        'id_curso' => $idCurso,
        'num_unidad' => 1,
    ], [
        'nombre' => 'Unidad I',
    ]);

    return Actividad::create(array_merge([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'tipo_entrega' => 'CON_ENTREGA',
        'es_grupal' => false,
        'ponderacion' => 20.0,
        'exigencia' => 60.0,
        'id_unidad' => $unidad->id_unidad,
    ], $attrs));
}

it('rechaza con 401 si no hay token de autenticacion', function () {
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso Clínico 1',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion")
        ->assertStatus(401);
});

it('rechaza con 403 si el usuario no tiene rol o perfil estudiante', function () {
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso Clínico 1',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $docenteUser = Usuario::factory()->create([
        'rut' => '11.111.111-1',
        'passhash' => Hash::make('docente-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $token = $docenteUser->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion")
        ->assertStatus(403)
        ->assertJson(['message' => 'Acceso exclusivo para estudiantes activos.']);
});

it('rechaza con 403 Forbidden cuando el estudiante no esta inscrito en el curso', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-1']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso Clínico 1',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion")
        ->assertStatus(403)
        ->assertJson(['message' => 'No estás inscrito en este curso.']);
});

it('rechaza con 404 Not Found si la actividad pertenece a otro curso (anti-IDOR)', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-2']);
    [$curso1, $comp1] = crearCursoYComponenteEvaluacion();
    [$curso2, $comp2] = crearCursoYComponenteEvaluacion();

    InscripcionCurso::create([
        'id_curso' => $curso1->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividadCurso2 = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Actividad Curso 2',
        'id_componente' => $comp2->id_componente,
        'es_grupal' => false,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso1->id_curso}/actividades/{$actividadCurso2->id_actividad}/evaluacion")
        ->assertStatus(404)
        ->assertJson(['message' => 'Actividad no encontrada en este curso.']);
});

it('rechaza con 403 Forbidden si la actividad tiene visible = false', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-3']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividadOculta = crearActividadEvaluacionTest([
        'visible' => false,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Actividad Oculta',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividadOculta->id_actividad}/evaluacion")
        ->assertStatus(403)
        ->assertJson(['message' => 'Esta actividad no está disponible.']);
});

it('retorna 404 si el estudiante no tiene un grupo asignado en la actividad', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-4']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Actividad Sin Grupo',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion")
        ->assertStatus(404)
        ->assertJson(['message' => 'No tienes un grupo asignado para esta actividad.']);
});

it('retorna 404 si la actividad aun no ha sido evaluada', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-6']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Actividad Pendiente de Evaluación',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo Test',
        'nota' => null,
    ]);

    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion")
        ->assertStatus(404)
        ->assertJson(['message' => 'Esta actividad aún no ha sido evaluada.']);
});

it('retorna 200 con evaluacion sumativa, nota grupal y nota individual ajustada por decimas (+0.3)', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-7']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    [$docenteUser, $docente] = crearDocenteEvaluador('9.876.543-1', 'ROBERTO', 'SILVA');

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso Clínico Semanal 1',
        'id_componente' => $componente->id_componente,
        'es_grupal' => true,
        'ponderacion' => 15.0,
        'exigencia' => 60.0,
    ]);

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo 1',
        'nota' => 6.2,
    ]);

    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
        'nota_individual' => 6.5,
        'diferencia_decimas' => 0.3,
    ]);

    $agenda = Agenda::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN,
        'mensaje' => 'Excelente análisis del diagnóstico diferencial y manejo farmacológico.',
        'fecha_envio' => '2026-11-04 15:30:00',
    ]);

    Evaluacion::create([
        'id_agenda' => $agenda->id_agenda,
        'id_usuario_evaluador' => $docenteUser->id_usuario,
        'fecha_evaluacion' => '2026-11-04 15:30:00',
        'evaluacion_obtenida' => '6.2',
        'puntaje_obtenido' => 36.0,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion");

    $response->assertStatus(200)
        ->assertJsonPath('data.curso.id_curso', $curso->id_curso)
        ->assertJsonPath('data.actividad.nombre', 'Caso Clínico Semanal 1')
        ->assertJsonPath('data.actividad.es_sumativa', true)
        ->assertJsonPath('data.actividad.es_grupal', true)
        ->assertJsonPath('data.calificacion.nota_grupal', 6.2)
        ->assertJsonPath('data.calificacion.diferencia_decimas', 0.3)
        ->assertJsonPath('data.calificacion.nota_individual', 6.5)
        ->assertJsonPath('data.calificacion.es_aprobatoria', true)
        ->assertJsonPath('data.calificacion.evaluacion_cualitativa', null)
        ->assertJsonPath('data.feedback.docente_evaluador', 'ROBERTO SILVA')
        ->assertJsonPath('data.feedback.retroalimentacion', 'Excelente análisis del diagnóstico diferencial y manejo farmacológico.')
        ->assertJsonPath('data.rubrica', null)
        ->assertJsonPath('data.entrega_evaluada', null);
});

it('retorna 200 con rubrica evaluada desglosando criterios, escalas seleccionadas y puntos', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-8']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    [$docenteUser, $docente] = crearDocenteEvaluador('9.876.543-2', 'ROBERTO', 'SILVA');

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso Clínico con Rúbrica',
        'id_componente' => $componente->id_componente,
        'es_grupal' => true,
    ]);

    $rubrica = Rubrica::create([
        'id_actividad' => $actividad->id_actividad,
        'estado_rubrica' => EstadoRubrica::CERRADA,
        'rubrica' => [
            'niveles' => [
                [
                    'id_nivel' => 'niv1',
                    'nombre' => 'Diagnóstico Diferencial',
                    'descripcion' => 'Precisión y fundamentación diagnóstica',
                    'ponderacion' => 50.0,
                    'escalas' => [
                        [
                            'id_escala' => 'esc1',
                            'nombre_nivel' => 'Insuficiente',
                            'puntos' => 10.0,
                            'criterio' => 'No fundamenta adecuadamente',
                        ],
                        [
                            'id_escala' => 'esc2',
                            'nombre_nivel' => 'Destacado',
                            'puntos' => 20.0,
                            'criterio' => 'Identifica y fundamenta exhaustivamente',
                        ],
                    ],
                ],
                [
                    'id_nivel' => 'niv2',
                    'nombre' => 'Plan Terapéutico',
                    'descripcion' => 'Manejo farmacológico y no farmacológico',
                    'ponderacion' => 50.0,
                    'escalas' => [
                        [
                            'id_escala' => 'esc3',
                            'nombre_nivel' => 'Básico',
                            'puntos' => 10.0,
                            'criterio' => 'Plan incompleto',
                        ],
                        [
                            'id_escala' => 'esc4',
                            'nombre_nivel' => 'Sobresaliente',
                            'puntos' => 20.0,
                            'criterio' => 'Plan integral según guías',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo Rúbrica',
        'nota' => 6.0,
    ]);

    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
        'nota_individual' => 6.0,
        'diferencia_decimas' => 0.0,
    ]);

    $agenda = Agenda::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN,
        'mensaje' => 'Buen trabajo de integración.',
        'fecha_envio' => '2026-11-04 15:30:00',
    ]);

    Evaluacion::create([
        'id_agenda' => $agenda->id_agenda,
        'id_rubrica' => $rubrica->id_rubrica,
        'id_usuario_evaluador' => $docenteUser->id_usuario,
        'fecha_evaluacion' => '2026-11-04 15:30:00',
        'evaluacion_obtenida' => '6.0',
        'puntaje_obtenido' => 36.0,
        'resultado' => [
            'niv1' => 'esc2', // Destacado: 20 pts
            'niv2' => 'esc3', // Básico: 10 pts
        ],
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion");

    $response->assertStatus(200)
        ->assertJsonPath('data.rubrica.id_rubrica', $rubrica->id_rubrica)
        ->assertJsonPath('data.rubrica.estado_rubrica', 'CERRADA')
        ->assertJsonPath('data.rubrica.total_criterios', 2)
        ->assertJsonPath('data.rubrica.puntaje_maximo', fn ($val) => (float) $val === 40.0)
        ->assertJsonPath('data.rubrica.puntaje_obtenido', fn ($val) => (float) $val === 36.0)
        ->assertJsonPath('data.rubrica.porcentaje_logro', fn ($val) => (float) $val === 90.0)
        ->assertJsonPath('data.rubrica.criterios_evaluados.0.id_criterio', 'niv1')
        ->assertJsonPath('data.rubrica.criterios_evaluados.0.escala_seleccionada.id_escala', 'esc2')
        ->assertJsonPath('data.rubrica.criterios_evaluados.0.escala_seleccionada.puntos', fn ($val) => (float) $val === 20.0)
        ->assertJsonPath('data.rubrica.criterios_evaluados.0.escalas.1.seleccionada', true)
        ->assertJsonPath('data.rubrica.criterios_evaluados.1.id_criterio', 'niv2')
        ->assertJsonPath('data.rubrica.criterios_evaluados.1.escala_seleccionada.id_escala', 'esc3')
        ->assertJsonPath('data.rubrica.criterios_evaluados.1.escala_seleccionada.puntos', fn ($val) => (float) $val === 10.0);
});

it('retorna 200 con evaluacion formativa cualitativa (Bueno/Regular/Malo) sin nota numerica', function () {
    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-9']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    [$docenteUser, $docente] = crearDocenteEvaluador('9.876.543-3', 'MARÍA', 'ROJAS');

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::FORMATIVA,
        'nombre' => 'Taller Formativo de ECG',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'JUAN PÉREZ',
        'nota' => null,
    ]);

    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
        'nota_individual' => null,
        'diferencia_decimas' => 0.0,
    ]);

    $agenda = Agenda::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN,
        'mensaje' => 'Buen reconocimiento de las ondas patológicas en derivaciones precordiales.',
        'fecha_envio' => '2026-11-04 16:00:00',
    ]);

    Evaluacion::create([
        'id_agenda' => $agenda->id_agenda,
        'id_usuario_evaluador' => $docenteUser->id_usuario,
        'fecha_evaluacion' => '2026-11-04 16:00:00',
        'evaluacion_obtenida' => 'Bueno',
        'puntaje_obtenido' => null,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion");

    $response->assertStatus(200)
        ->assertJsonPath('data.actividad.es_sumativa', false)
        ->assertJsonPath('data.calificacion.nota_grupal', null)
        ->assertJsonPath('data.calificacion.nota_individual', null)
        ->assertJsonPath('data.calificacion.evaluacion_cualitativa', 'Bueno')
        ->assertJsonPath('data.calificacion.es_aprobatoria', true)
        ->assertJsonPath('data.feedback.docente_evaluador', 'MARÍA ROJAS')
        ->assertJsonPath('data.rubrica', null);
});

it('permite descargar el archivo de entrega calificada con encabezados correctos', function () {
    Storage::fake('local_archives');

    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-0']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    [$docenteUser, $docente] = crearDocenteEvaluador('9.876.543-4', 'ROBERTO', 'SILVA');

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso con Entrega',
        'id_componente' => $componente->id_componente,
        'es_grupal' => true,
    ]);

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo Entrega',
        'nota' => 6.5,
    ]);

    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    // Crear archivo simulado
    $ruta = 'entregas/informe_calificado.pdf';
    Storage::disk('local_archives')->put($ruta, '%PDF-1.4 Informe Evaluado');

    $archivo = Archivo::create([
        'nombre_original' => 'informe_calificado.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'ruta_fisica' => $ruta,
        'peso_bytes' => 1024,
    ]);

    $agenda = Agenda::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN,
        'mensaje' => 'Excelente entrega.',
        'uuid_archivo_subido' => $archivo->uuid_archivo,
        'fecha_envio' => '2026-11-04 15:30:00',
    ]);

    Evaluacion::create([
        'id_agenda' => $agenda->id_agenda,
        'id_usuario_evaluador' => $docenteUser->id_usuario,
        'fecha_evaluacion' => '2026-11-04 15:30:00',
        'evaluacion_obtenida' => '6.5',
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    // 1. Probar que el endpoint de evaluación informe la entrega con url_descarga
    $resEval = $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/evaluacion");

    $resEval->assertStatus(200)
        ->assertJsonPath('data.entrega_evaluada.id_agenda', $agenda->id_agenda)
        ->assertJsonPath('data.entrega_evaluada.nombre_original', 'informe_calificado.pdf');

    // 2. Descargar archivo
    $resDescarga = $this->withToken($token)
        ->get("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$agenda->id_agenda}/descargar");

    $resDescarga->assertStatus(200)
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('rechaza con 403 Forbidden la descarga de entrega si el alumno pertenece a otro grupo del curso', function () {
    Storage::fake('local_archives');

    [$usuario1, $estudiante1] = crearEstudianteEvaluacion(['rut' => '12.345.678-1']);
    [$usuario2, $estudiante2] = crearEstudianteEvaluacion(['rut' => '12.345.678-2']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    [$docenteUser, $docente] = crearDocenteEvaluador('9.876.543-5', 'ROBERTO', 'SILVA');

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante1->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante2->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso Clínico Multi-Grupo',
        'id_componente' => $componente->id_componente,
        'es_grupal' => true,
    ]);

    // Grupo 1: Alumno 1
    $grupo1 = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo 1',
    ]);
    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo1->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante1->id_estudiante,
    ]);

    // Grupo 2: Alumno 2
    $grupo2 = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo 2',
    ]);
    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo2->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante2->id_estudiante,
    ]);

    $ruta = 'entregas/g1.pdf';
    Storage::disk('local_archives')->put($ruta, '%PDF-1.4 G1');
    $archivo = Archivo::create([
        'nombre_original' => 'g1.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'ruta_fisica' => $ruta,
        'peso_bytes' => 1024,
    ]);

    $agendaG1 = Agenda::create([
        'id_actividad_asignada_grupo' => $grupo1->id_actividad_asignada_grupo,
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN,
        'mensaje' => 'Evaluación G1',
        'uuid_archivo_subido' => $archivo->uuid_archivo,
    ]);

    // Alumno 2 intenta descargar la entrega de Grupo 1
    $token2 = $usuario2->createToken('test-token')->plainTextToken;

    $this->withToken($token2)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$agendaG1->id_agenda}/descargar")
        ->assertStatus(403)
        ->assertJson(['message' => 'Esta entrega no te pertenece.']);
});

it('retorna 404 al descargar entrega si el archivo no existe en el disco', function () {
    Storage::fake('local_archives');

    [$usuario, $estudiante] = crearEstudianteEvaluacion(['rut' => '12.345.678-3']);
    [$curso, $componente] = crearCursoYComponenteEvaluacion();
    [$docenteUser, $docente] = crearDocenteEvaluador('9.876.543-6', 'ROBERTO', 'SILVA');

    InscripcionCurso::create([
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante,
        'estado' => 'INSCRITO',
    ]);

    $actividad = crearActividadEvaluacionTest([
        'visible' => true,
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'nombre' => 'Caso con Archivo Inexistente',
        'id_componente' => $componente->id_componente,
        'es_grupal' => false,
    ]);

    $grupo = ActividadAsignadaGrupo::create([
        'id_actividad' => $actividad->id_actividad,
        'nombre_grupo' => 'Grupo Individual',
    ]);
    IntegranteGrupo::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_estudiante' => $estudiante->id_estudiante,
    ]);

    $archivo = Archivo::create([
        'nombre_original' => 'desaparecido.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'ruta_fisica' => 'no_existe_en_disco.pdf',
        'peso_bytes' => 1024,
    ]);

    $agenda = Agenda::create([
        'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
        'id_usuario_emisor' => $docenteUser->id_usuario,
        'tipo_mensaje' => TipoMensaje::EVALUACIÓN,
        'mensaje' => 'Evaluado',
        'uuid_archivo_subido' => $archivo->uuid_archivo,
    ]);

    $token = $usuario->createToken('test-token')->plainTextToken;

    $this->withToken($token)
        ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$agenda->id_agenda}/descargar")
        ->assertStatus(404)
        ->assertJson(['message' => 'El archivo no existe en el servidor.']);
});
