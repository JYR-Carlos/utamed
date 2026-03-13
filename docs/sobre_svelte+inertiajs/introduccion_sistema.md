# UTAMED: "Fase administración"
La utamed es una plataforma renovada para la gestión administrativa y académica del departamento de [...] cuyos objetivos son la mejora del flujo ...


Actualmente este sistema se encuentra en periodo de pruebas para el administrador, por lo que su funcionalidad radica en la gestión efectiva de cada una de las secciones que posee el sistema. 

## Funcionalidades y Roles
Se contempla las funcionalidades basada en permisos, estas solo son accesibles si el rol está adjudicado a un usuario. Por ende, cada rol posee permisos especificos que permiten el accionar por cada sección del programa. 


### Roles
Los roles considerados en esta fase son:
- Jefe de Carrera
- Administrador
- Docente
- Director de Departamento
- Supervisor
- Ayudante
- Alumno

Se considera que se puede asignar uno o más roles a un usuario, sin embargo, lo que predomina son los permisos que cada rol tendrá asignado por defecto.

Los permisos de cada rol son:
( Por definir )

### Funcionalidades
Las funcionalidades son únicamente accesibles mediante los permisos asignados, tanto las vistas como la autorización se monitorean si el recurso posee la acción requerida.

El sistema de permisos es algo que se explicará en detalle en otro archivo, pero en palabras sencillas es una descripción breve del recurso y accion, por ejemplo:

"programas:ver" -> Permite ver los programas
"programas:editar" -> Permite editar los programas

Actualmente, los permisos están obligatoriamente contextualizados, es decir, un permiso se puede dar en un curso, en una carrera u otro 'recurso' que por lo general los recursos son un simil de los contextos.

Por ende, las funcionalidades son acciones que se aplican sobre un recurso, estos son los siguientes:
- Syllabus
- Curso
- Facultad
- Departamento
- Carrera
- Malla/Plan de Carrera
- Gestion de Usuarios

#### Syllabus
El Syllabus contempla 2 fases que son visibles para diferentes roles, existe la versión básica que es la versión simplificada del syllabus y que es visible para estudiantes ... *agregar descripción de contenido  ... y la versión completa que es visible para el sector acádemico (docentes, jefe de carrera, director de departamento y ayudante**)