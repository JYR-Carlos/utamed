<?php

return array (
  'Administrativo\\Carrera' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'Administrativo\\Departamento' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'Administrativo\\Facultad' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'Agenda\\Actividad' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'Curso\\Curso' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'Administrativo\\Asignatura' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'Usuario\\Docente' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'Usuario\\Estudiante' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'Usuario\\Rol' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'Usuario\\Usuario' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'Administrativo\\AsignacionPlan' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Plan',
          'method' => 'plan',
        ),
        1 => 
        array (
          'target' => 'Carrera',
          'method' => 'carrera',
        ),
      ),
    ),
  ),
  'Administrativo\\Plan' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Carrera',
          'method' => 'carrera',
        ),
      ),
    ),
  ),
  'Administrativo\\Programa' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Curso',
          'method' => 'curso',
        ),
      ),
    ),
  ),
  'Agenda\\ActividadAsignada' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Actividad',
          'method' => 'actividad',
        ),
      ),
    ),
  ),
  'Agenda\\AsignadoActividad' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'ActividadAsignada',
          'method' => 'actividadAsignada',
        ),
        1 => 
        array (
          'target' => 'Actividad',
          'method' => 'actividad',
        ),
      ),
      1 => 
      array (
        0 => 
        array (
          'target' => 'Estudiante',
          'method' => 'estudiante',
        ),
        1 => 
        array (
          'target' => 'Carrera',
          'method' => 'carrera',
        ),
      ),
    ),
  ),
  'Curso\\Asistencia' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'InscripcionSeccion',
          'method' => 'inscripcionSeccion',
        ),
        1 => 
        array (
          'target' => 'Estudiante',
          'method' => 'estudiante',
        ),
        2 => 
        array (
          'target' => 'Carrera',
          'method' => 'carrera',
        ),
      ),
      1 => 
      array (
        0 => 
        array (
          'target' => 'InscripcionSeccion',
          'method' => 'inscripcionSeccion',
        ),
        1 => 
        array (
          'target' => 'Seccion',
          'method' => 'seccion',
        ),
        2 => 
        array (
          'target' => 'Curso',
          'method' => 'curso',
        ),
      ),
    ),
  ),
  'Curso\\InscripcionCurso' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Curso',
          'method' => 'curso',
        ),
      ),
      1 => 
      array (
        0 => 
        array (
          'target' => 'Estudiante',
          'method' => 'estudiante',
        ),
        1 => 
        array (
          'target' => 'Carrera',
          'method' => 'carrera',
        ),
      ),
    ),
  ),
  'Curso\\InscripcionSeccion' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Estudiante',
          'method' => 'estudiante',
        ),
        1 => 
        array (
          'target' => 'Carrera',
          'method' => 'carrera',
        ),
      ),
      1 => 
      array (
        0 => 
        array (
          'target' => 'Seccion',
          'method' => 'seccion',
        ),
        1 => 
        array (
          'target' => 'Curso',
          'method' => 'curso',
        ),
      ),
    ),
  ),
  'Curso\\Seccion' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Curso',
          'method' => 'curso',
        ),
      ),
    ),
  ),
  'Curso\\Unidad' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Curso',
          'method' => 'curso',
        ),
      ),
    ),
  ),
);
