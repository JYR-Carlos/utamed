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
  'Curso\\Componente' => 
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
  'Operations\\Archivos' => 
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
  'Agenda\\ActividadAsignadaGrupo' => 
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
  'Agenda\\Agenda' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'ActividadAsignadaGrupo',
          'method' => 'actividadAsignadaGrupo',
        ),
        1 => 
        array (
          'target' => 'Actividad',
          'method' => 'actividad',
        ),
      ),
    ),
  ),
  'Agenda\\Evaluacion' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Agenda',
          'method' => 'agenda',
        ),
        1 => 
        array (
          'target' => 'ActividadAsignadaGrupo',
          'method' => 'actividadAsignadaGrupo',
        ),
        2 => 
        array (
          'target' => 'Actividad',
          'method' => 'actividad',
        ),
      ),
    ),
  ),
  'Agenda\\IntegranteGrupo' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'ActividadAsignadaGrupo',
          'method' => 'actividadAsignadaGrupo',
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
          'target' => 'InscripcionComponente',
          'method' => 'inscripcionComponente',
        ),
        1 => 
        array (
          'target' => 'Componente',
          'method' => 'componente',
        ),
      ),
      1 => 
      array (
        0 => 
        array (
          'target' => 'InscripcionComponente',
          'method' => 'inscripcionComponente',
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
    ),
  ),
  'Curso\\DocenteComponente' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Componente',
          'method' => 'componente',
        ),
      ),
    ),
  ),
  'Curso\\InscripcionComponente' => 
  array (
    'type' => 'hierarchical',
    'paths' => 
    array (
      0 => 
      array (
        0 => 
        array (
          'target' => 'Componente',
          'method' => 'componente',
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
  'Curso\\Programa' => 
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
