<?php

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(DatabaseTransactions::class);

function crearEstudianteAgenda(array $usuarioAttrs = [], array $estudianteAttrs = []): array
{
    static $estCount = 100;
    $estCount++;

    $carrera = Carrera::first() ?? Carrera::create([
        'nombre' => 'Medicina Test',
        'codigo_carrera' => 19999,
        'id_departamento' => 1,
        'id_contexto' => 1,
    ]);

    $usuario = Usuario::factory()->create(array_merge([
        'rut' => '12.345.678-' . ($estCount % 10),
        'nombre1' => 'ESTUDIANTE',
        'nombre2' => 'TEST',
        'apellido1' => 'PÉREZ',
        'apellido2' => 'GONZÁLEZ',
        'email' => 'est.agenda.' . $estCount . '.' . uniqid() . '@alumnos.uta.cl',
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

function crearDocenteAgenda(string $rut, string $nombre, string $apellido): array
{
    static $docCount = 1;
    $docCount++;

    $usuario = Usuario::factory()->create([
        'rut' => $rut,
        'nombre1' => $nombre,
        'apellido1' => $apellido,
        'email' => strtolower($nombre . '.' . $apellido . '.' . $docCount . '@docentes.uta.cl'),
        'passhash' => Hash::make('secreto-123'),
        'esta_activo' => true,
        'fecha_cambio_passhash' => now(),
    ]);

    $docente = Docente::create([
        'id_usuario' => $usuario->id_usuario,
    ]);

    return [$usuario, $docente];
}

function crearEstructuraCursoAgenda(array $actividadOverrides = []): array
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
        'cod_curso' => 3000 + $cursoCount,
        'indice_grupo' => 1,
        'semestre_real' => 2,
        'agno_real' => 2026,
        'id_asignacion_plan' => $asignacionPlan->id_asignacion_plan,
        'fecha_inicio' => Carbon::parse('2026-08-01'),
        'fecha_fin' => Carbon::parse('2026-12-15'),
    ]);

    $tipoC = TipoComponente::firstOrCreate(['tipo' => 'Cátedra']);
    $componente = Componente::create([
        'id_curso' => $curso->id_curso,
        'id_tipo_componente' => $tipoC->id_tipo_componente,
        'porcentaje_aprobacion' => 60.0,
        'aprobacion_obligatoria' => false,
        'porcentaje_asistencia_obligatoria' => 75.0,
        'genera_acta' => true,
    ]);

    $unidad = Unidad::firstOrCreate([
        'id_curso' => $curso->id_curso,
        'num_unidad' => 1,
    ], [
        'nombre' => 'Unidad I',
    ]);

    $actividad = Actividad::create(array_merge([
        'id_componente' => $componente->id_componente,
        'id_unidad' => $unidad->id_unidad,
        'nombre' => 'Caso Clínico Cardiológico',
        'descripcion' => 'Desarrollar el caso clínico del paciente coronario',
        'tipo_actividad' => TipoActividad::SUMATIVA,
        'tipo_entrega' => 'ARCHIVO',
        'es_grupal' => false,
        'visible' => true,
        'ponderacion' => 20.0,
        'exigencia' => 60,
        'fecha_limite' => Carbon::now()->addDays(5),
        'nro_dias_adicionales_para_bloqueo' => 2,
    ], $actividadOverrides));

    return [$curso, $componente, $actividad];
}

describe('APP-12: Endpoints de Agenda de Actividad y Entregas', function () {
    beforeEach(function () {
        Storage::fake(config('files.storage.disk', 'local_archives'));
    });

    it('rechaza el acceso sin token de autenticacion con 401 Unauthorized', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();

        $this->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda")
            ->assertStatus(401);

        $this->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda", [
            'mensaje' => 'Hola profesor',
        ])->assertStatus(401);
    });

    it('rechaza si el usuario autenticado no tiene rol de estudiante con 403 Forbidden', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$docenteUser] = crearDocenteAgenda('9.999.999-9', 'Fernando', 'Gutiérrez');

        $this->actingAs($docenteUser, 'sanctum')
            ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda")
            ->assertStatus(403);
    });

    it('rechaza si el estudiante no esta matriculado en el curso con 403 Forbidden', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser] = crearEstudianteAgenda();

        $this->actingAs($alumnoUser, 'sanctum')
            ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda")
            ->assertStatus(403);
    });

    it('rechaza con 404 si la actividad pertenece a otro curso', function () {
        [$curso1, , $actividad1] = crearEstructuraCursoAgenda();
        [$curso2] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso2->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $this->actingAs($alumnoUser, 'sanctum')
            ->getJson("/api/v1/estudiante/cursos/{$curso2->id_curso}/actividades/{$actividad1->id_actividad}/agenda")
            ->assertStatus(404);
    });

    it('rechaza con 403 si la actividad no es visible', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda(['visible' => false]);
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $this->actingAs($alumnoUser, 'sanctum')
            ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda")
            ->assertStatus(403);
    });

    it('rechaza con 404 si la actividad es grupal y el alumno no tiene grupo asignado', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda(['es_grupal' => true]);
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $this->actingAs($alumnoUser, 'sanctum')
            ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda")
            ->assertStatus(404)
            ->assertJson(['message' => 'No tienes un grupo asignado para esta actividad.']);
    });

    it('asegura grupo individual y lista cronologicamente interacciones con visto_por y lectura automatica', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda(['es_grupal' => false]);
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();
        [$docenteUser] = crearDocenteAgenda('8.888.888-8', 'Carlos', 'Docente');

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        // Invocamos GET que asegura grupo individual y crea la estructura
        $res = $this->actingAs($alumnoUser, 'sanctum')
            ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda")
            ->assertStatus(200);

        $grupoId = $res->json('data.grupo.id_actividad_asignada_grupo');
        expect($grupoId)->not->toBeNull();

        // Creamos un mensaje del docente en ese grupo
        $msgDocente = Agenda::create([
            'id_actividad_asignada_grupo' => $grupoId,
            'id_usuario_emisor' => $docenteUser->id_usuario,
            'fecha_envio' => Carbon::now()->subMinutes(10),
            'tipo_mensaje' => TipoMensaje::FEEDBACK,
            'mensaje' => 'Recuerden incluir la bibliografía APA 7.',
        ]);

        // Verificamos que al consultar de nuevo, el mensaje del docente se marque automáticamente como leído
        $res2 = $this->actingAs($alumnoUser, 'sanctum')
            ->getJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda")
            ->assertStatus(200);

        $interacciones = $res2->json('data.interacciones');
        expect($interacciones)->toHaveCount(1);
        expect($interacciones[0]['mensaje'])->toBe('Recuerden incluir la bibliografía APA 7.');
        expect($interacciones[0]['emisor']['rol'])->toBe('docente');

        // Comprobamos en base de datos lectura_agenda
        $leido = DB::table('agenda.lectura_agenda')
            ->where('id_agenda', $msgDocente->id_agenda)
            ->where('id_usuario_lector', $alumnoUser->id_usuario)
            ->exists();
        expect($leido)->toBeTrue();
    });

    it('permite enviar mensaje al docente con tipo MENSAJE_AL_PROFESOR', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $res = $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda", [
                'mensaje' => 'Profesor, ¿es posible entregar en formato ZIP?',
            ])
            ->assertStatus(201);

        $res->assertJsonPath('data.tipo_mensaje', TipoMensaje::MENSAJE_AL_PROFESOR->value);
        $res->assertJsonPath('data.mensaje', 'Profesor, ¿es posible entregar en formato ZIP?');
        $res->assertJsonPath('data.emisor.es_usuario_actual', true);

        $idAgenda = $res->json('data.id_agenda');
        $enBd = Agenda::find($idAgenda);
        expect($enBd)->not->toBeNull();
        expect($enBd->tipo_mensaje->value)->toBe(TipoMensaje::MENSAJE_AL_PROFESOR->value);
    });

    it('valida longitud del mensaje al enviar al docente', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda", [
                'mensaje' => 'A', // Demasiado corto (min: 2)
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mensaje']);
    });

    it('sube un archivo de entrega exitosamente', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $file = UploadedFile::fake()->create('informe_caso_clinico.pdf', 1024, 'application/pdf');

        $res = $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file,
                'mensaje' => 'Entrega de avance grupal',
            ])
            ->assertStatus(201);

        $res->assertJsonPath('data.tipo_mensaje', TipoMensaje::ENTREGA_DE_ARCHIVO->value);
        $res->assertJsonPath('data.archivo.nombre_original', 'informe_caso_clinico.pdf');
        expect($res->json('data.archivo.url_descarga'))->toContain('/entregas/');

        $idAgenda = $res->json('data.id_agenda');
        $agendaBd = Agenda::find($idAgenda);
        expect($agendaBd)->not->toBeNull();
        expect($agendaBd->uuid_archivo_subido)->not->toBeNull();
    });

    it('rechaza entrega si la actividad no admite entrega (SIN_ENTREGA)', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda(['tipo_entrega' => 'SIN_ENTREGA']);
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $file = UploadedFile::fake()->create('tarea.pdf', 500, 'application/pdf');

        $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    });

    it('rechaza entrega si la fecha limite con holguras ha expirado', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda([
            'fecha_limite' => Carbon::now()->subDays(10),
            'nro_dias_adicionales_para_bloqueo' => 1,
        ]);
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $file = UploadedFile::fake()->create('tardio.pdf', 500, 'application/pdf');

        $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    });

    it('rechaza entrega si el grupo ya fue evaluado', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        // Obtenemos o aseguramos el grupo
        $grupo = app(\App\Services\Agenda\GrupoIndividualService::class)->asegurarGrupo($actividad, $estudiante->id_estudiante);
        $grupo->update(['nota' => 6.5]);

        $file = UploadedFile::fake()->create('segunda_entrega.pdf', 500, 'application/pdf');

        $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    });

    it('permite cancelar la ultima entrega activa no evaluada', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $file = UploadedFile::fake()->create('entrega_a_cancelar.pdf', 500, 'application/pdf');

        $resUpload = $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file,
            ])
            ->assertStatus(201);

        $idAgenda = $resUpload->json('data.id_agenda');
        $uuidArchivo = $resUpload->json('data.archivo.uuid_archivo');

        // Cancelamos la entrega
        $this->actingAs($alumnoUser, 'sanctum')
            ->deleteJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$idAgenda}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Entrega cancelada exitosamente.']);

        // Verificamos que el archivo físico esté marcado como pendiente_de_borrado
        $archivoBd = Archivo::find($uuidArchivo);
        expect($archivoBd->pendiente_de_borrado)->toBeTrue();

        // Verificamos que exista el registro Cancelación de entrega en agenda
        $cancelEvent = Agenda::where('uuid_archivo_subido', $uuidArchivo)
            ->where('tipo_mensaje', TipoMensaje::CANCELACIÓN_DE_ENTREGA->value)
            ->first();
        expect($cancelEvent)->not->toBeNull();
    });

    it('rechaza cancelar una entrega que ya fue cancelada', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $file = UploadedFile::fake()->create('entrega_doble_cancel.pdf', 500, 'application/pdf');

        $resUpload = $this->actingAs($alumnoUser, 'sanctum')
            ->post("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file,
            ]);

        $idAgenda = $resUpload->json('data.id_agenda');

        // Primera cancelación (exitosa)
        $this->actingAs($alumnoUser, 'sanctum')
            ->deleteJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$idAgenda}")
            ->assertStatus(200);

        // Segunda cancelación (debe fallar con 422)
        $this->actingAs($alumnoUser, 'sanctum')
            ->deleteJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$idAgenda}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agenda']);
    });

    it('rechaza cancelar si no es la entrega mas reciente del grupo', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $file1 = UploadedFile::fake()->create('entrega1.pdf', 500, 'application/pdf');
        $res1 = $this->actingAs($alumnoUser, 'sanctum')
            ->post("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file1,
            ]);
        $idAgenda1 = $res1->json('data.id_agenda');

        // Simulamos un delay o diferente timestamp
        Carbon::setTestNow(Carbon::now()->addMinutes(1));

        $file2 = UploadedFile::fake()->create('entrega2.pdf', 600, 'application/pdf');
        $res2 = $this->actingAs($alumnoUser, 'sanctum')
            ->post("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas", [
                'archivo' => $file2,
            ]);
        $idAgenda2 = $res2->json('data.id_agenda');

        // Intentar cancelar la entrega 1 (debe fallar)
        $this->actingAs($alumnoUser, 'sanctum')
            ->deleteJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$idAgenda1}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agenda']);

        // Cancelar entrega 2 debe funcionar
        $this->actingAs($alumnoUser, 'sanctum')
            ->deleteJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/entregas/{$idAgenda2}")
            ->assertStatus(200);

        Carbon::setTestNow();
    });

    it('permite marcar explícitamente como leída la agenda con POST agenda/leida', function () {
        [$curso, , $actividad] = crearEstructuraCursoAgenda();
        [$alumnoUser, $estudiante] = crearEstudianteAgenda();
        [$docenteUser] = crearDocenteAgenda('7.777.777-7', 'Doctor', 'Docente');

        InscripcionCurso::create([
            'id_curso' => $curso->id_curso,
            'id_estudiante' => $estudiante->id_estudiante,
            'estado_inscripcion' => 'INSCRITO',
        ]);

        $grupo = app(\App\Services\Agenda\GrupoIndividualService::class)->asegurarGrupo($actividad, $estudiante->id_estudiante);

        Agenda::create([
            'id_actividad_asignada_grupo' => $grupo->id_actividad_asignada_grupo,
            'id_usuario_emisor' => $docenteUser->id_usuario,
            'fecha_envio' => Carbon::now()->subMinutes(5),
            'tipo_mensaje' => TipoMensaje::FEEDBACK,
            'mensaje' => 'Aviso urgente de clase práctica.',
        ]);

        $res = $this->actingAs($alumnoUser, 'sanctum')
            ->postJson("/api/v1/estudiante/cursos/{$curso->id_curso}/actividades/{$actividad->id_actividad}/agenda/leida")
            ->assertStatus(200);

        $res->assertJson([
            'message' => 'Agenda marcada como leída.',
            'no_leidos' => 0,
        ]);
    });
});
