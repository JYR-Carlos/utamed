<?php

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

use App\Models\Administrativo\Facultad;
use App\Models\Administrativo\Departamento;
use App\Models\Administrativo\Carrera;
use App\Models\Curso\Curso;
use App\Models\Curso\Seccion;
use App\Models\Curso\InscripcionSeccion;
use App\Models\Curso\TipoSeccion;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Docente;
use App\Models\Usuario\Usuario;

beforeEach(function () {
});

test("llave compuesta triple - curso > seccion > inscripcion_seccion > estudiante", function () {
  // 1. Crear un Contexto
  $contexto = \App\Models\Usuario\Contexto::create(['contexto_display' => 'Test']);

  // 2. Crear Facultad
  $facultad = Facultad::create(['nombre' => 'A', 'id_contexto' => $contexto->id_contexto]);

  // 3. Crear Departamento sin factory usando create() - PRUEBA DE BASEMODEL
  $departamento = Departamento::create([
    'nombre' => 'B',
    'id_facultad' => $facultad->id_facultad,
    'id_contexto' => $contexto->id_contexto
  ]);
  echo "departamento clave: " . json_encode($departamento->getKey()) . "\n";
  echo "departamento->id_departamento: " . $departamento->id_departamento . "\n";
  echo "departamento->id_facultad: " . $departamento->id_facultad . "\n";

  // 4. Crear Carrera usando relación
  $carrera = $departamento->carreras()->create([
    'nombre' => 'C',
    'id_facultad' => $facultad->id_facultad,
    'id_contexto' => $contexto->id_contexto
  ]);

  // 5. Crear un Curso
  $curso = Curso::create([
    'nombre' => 'Test Curso',
    'indice_grupo' => 1,
    'fecha_inicio' => now(),
    'fecha_fin' => now()->addMonths(4),
    'id_contexto' => $contexto->id_contexto
  ]);
  echo "curso {$curso->id_curso}\n";

  // 6. Obtener TipoSeccion existente o crear
  $tipoSeccion = TipoSeccion::first() ?? TipoSeccion::create(['id_tipo_seccion' => 1, 'tipo' => 'Teoría']);
  echo "tipo seccion {$tipoSeccion->id_tipo_seccion}\n";

  // 7. Crear un Usuario y Docente
  $uniqueId = substr(uniqid(), -8);
  $usuario = Usuario::create([
    'username' => 'doc_' . $uniqueId,
    'passhash' => 'test',
    'rut' => 'T' . $uniqueId,
    'nombre1' => 'Test',
    'apellido1' => 'Docente'
  ]);
  $docente = Docente::create([
    'id_usuario' => $usuario->id_usuario,
    'grado' => 'test'
  ]);
  echo "docente {$docente->id_docente}\n";

  // 8. Crear una Seccion sin factory (llave compuesta)
  $seccion = Seccion::create([
    'id_curso' => $curso->id_curso,
    'id_tipo_seccion' => $tipoSeccion->id_tipo_seccion,
    'id_docente' => $docente->id_docente,
    'genera_acta' => false,
    'porcentaje_aprobacion' => 60,
    'aprobacion_obligatoria' => false,
    'porcentaje_asistencia_obligatoria' => 0,
  ]);
  echo "seccion clave: " . json_encode($seccion->getKey()) . "\n";
  echo "seccion->id_seccion: " . $seccion->id_seccion . "\n";
  echo "seccion->id_curso: " . $seccion->id_curso . "\n";

  // 9. Crear otro Usuario para Estudiante
  $uniqueIdEst = substr(uniqid(), -8);
  $usuarioEst = Usuario::create([
    'username' => 'est_' . $uniqueIdEst,
    'passhash' => 'test',
    'rut' => 'E' . $uniqueIdEst,
    'nombre1' => 'Test',
    'apellido1' => 'Estudiante'
  ]);

  // 10. Crear un Estudiante
  $estudiante = Estudiante::create([
    'id_usuario' => $usuarioEst->id_usuario,
    'id_carrera' => $carrera->id_carrera
  ]);

  // 11. Crear una InscripcionSeccion sin factory (llave compuesta triple)
  $inscripcion = InscripcionSeccion::create([
    'id_seccion' => $seccion->id_seccion,
    'id_curso' => $seccion->id_curso,
    'id_estudiante' => $estudiante->id_estudiante,
  ]);
  echo "inscripcion seccion clave: " . json_encode($inscripcion->getKey()) . "\n";

  // Verificar que los datos son correctos
  expect($inscripcion->id_curso)->toBe($seccion->id_curso);
  expect($inscripcion->id_seccion)->toBe($seccion->id_seccion);
  expect($inscripcion->id_estudiante)->toBe($estudiante->id_estudiante);

  // Verificaciones cruciales: Departamento con clave compuesta recuperó sus valores
  expect($departamento->id_departamento)->toBeTruthy();
  expect($departamento->id_facultad)->toBe($facultad->id_facultad);

  $depfound = Departamento::
    where('id_departamento', $departamento->id_departamento)
    ->where('id_facultad', $departamento->id_facultad)
    ->first();
  echo "departamento found: " . json_encode($depfound) . "\n";
});
