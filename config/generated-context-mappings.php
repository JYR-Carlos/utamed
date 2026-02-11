<?php

return array (
  'utamed.Administrativo.Carrera' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'utamed.Administrativo.Departamento' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'utamed.Administrativo.Facultad' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'utamed.Agenda.Actividad' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'utamed.Curso.Curso' => 
  array (
    'type' => 'direct',
    'paths' => 
    array (
    ),
  ),
  'utamed.Administrativo.Asignatura' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'utamed.Usuario.Docente' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'utamed.Usuario.Estudiante' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'utamed.Usuario.Rol' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'utamed.Usuario.Usuario' => 
  array (
    'type' => 'global',
    'paths' => 
    array (
    ),
  ),
  'utamed.Administrativo.AsignacionPlan' => 
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
  'utamed.Administrativo.Plan' => 
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
  'utamed.Administrativo.Programa' => 
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
  'utamed.Agenda.ActividadAsignada' => 
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
  'utamed.Agenda.AsignadoActividad' => 
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
  'utamed.Curso.Asistencia' => 
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
  'utamed.Curso.InscripcionCurso' => 
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
  'utamed.Curso.InscripcionSeccion' => 
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
  'utamed.Curso.Seccion' => 
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
  'utamed.Curso.Unidad' => 
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
