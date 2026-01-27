# Diccionario de Datos

La base de datos "Utamed" se compone de 4 esquemas principales

| Nombre         | Tablas Contenidas                                                                                                                               |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Administrativo | Facultad, Departamento, Asignatura, Carrera, Plan, Asignacion_Plan y Programa                                                                   |
| Usuario        | Contexto, Tipo_Contexto, Usuario_Rol_Asignacion, Rol, Asignacion_Rol_Permiso, Usuario_Permiso_Especial, Permiso, Usuario, Estudiante y Docente. |
| Agenda         | Actividad_Asignada, Estado_Actividad, Asignado_Actividad y Agenda.                                                                              |
| Curso          | Curso, Inscripcion_Curso, Inscripcion_Seccion, Seccion, Tipo_Seccion, Unidad y Actividad                                                        |

El acceso a estos esquema se realiza de la siguiente manera: "Utamed.[ Nombre esquema ]"."[Nombre Tabla ]"

## Tablas

### Facultad

| Columna        | Tipo      | Restriccion | Descripción                                                                                                                                                                                                                      |
| -------------- | --------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| id_facultad    | smallint  | PK          | Identificador de la facultad.                                                                                                                                                                                                     |
| nombre         | text      | NOT NULL    | Nombre de la facultad (Ej: Facultad de ciencias de la salud)                                                                                                                                                                      |
| fecha_creacion | timestamp | NOT NULL    | Fecha del registro de la facultad en la base de datos (Default: now())                                                                                                                                                            |
| esta_activo    | boolean   | NOT NULL    | Marca estado True si está activo y False si no lo está (Default: TRUE).                                                                                                                                                         |
| id_contexto    | integer   | FK, UQ      | Identificador del Contexto.<br /><br />**Relación:** Apunta a la tabla Contexto del esquema Usuario.<br />**Regla**: La Creación de una facultad da un contexto, este último sirve para los permisos de usuarios. |

### Departamento

| Columna         | Tipo      | Restriccion | Descripcion                                                                                                                   |
| --------------- | --------- | ----------- | ----------------------------------------------------------------------------------------------------------------------------- |
| id_departamento | smallint  | PK          | Identificador del departamento, es autoincremental.                                                                           |
| nombre          | text      | NOT NULL    | Nombre del departamento.                                                                                                      |
| fecha_creacion  | timestamp | NOT NULL    | Fecha de creación en la base de datos (Default: now()).                                                                      |
| esta_activo     | boolean   | NOT NULL    | Determina si el departamento está o no activo (Default: TRUE).                                                               |
| id_facultad     | smallint  | PK, FK      | Id de facultad a la cual pertenece el departamento.<br />**Relacion:** Se relaciona 1:N desde facultad a departamento. |

### Carrera

| Columna         | Tipo      | Restriccion  | Descripción                                                  |
| --------------- | --------- | ------------ | ------------------------------------------------------------- |
| id_carrera      | integer   | PK           | Identificador de la carrera, es autoincremental.              |
| nombre          | text      | NOT NULL     | Nombre de la carrera.                                         |
| jornada         | text      | -            | Indica si es diurna o vespertina.                             |
| sede            | text      | -            | Indica la sede a la cual pertenece.                           |
| modalidad       | text      | -            | Puede ser online, hibrida o presencial.                       |
| fecha_creacion  | timestamp | NOT NULL     | Creación de la carrera en la base de datos (Default: now()). |
| esta_activo     | boolean   | NOT NULL     | Indica si la carrera está o no activa (Default: TRUE).       |
| id_departamento | smallint  | FK, NOT NULL | Identificador del departamento al cual pertenece la carrera.  |
| id_facultad     | smallint  | FK, NOT NULL | Identificador de la facultad a la que pertenece la carrera.   |

### Asignatura

| Columna           | Tipo        | Restricción | Descripcion                                        |
| ----------------- | ----------- | ------------ | -------------------------------------------------- |
| id_asignatura     | integer     | PK           | Identificador de la asignatura.                    |
| cod_asignatura    | varchar(10) | UQ, NOT NULL | Código único de la asignatura.                   |
| nombre            | text        | NOT NULL     | Nombre de la asignatura.                           |
| descripcion       | text        | -            | Descripción detallada.                            |
| creditos_sct      | smallint    | NOT NULL     | Créditos SCT (Sistema de Créditos Transferibles) |
| horas_catedra     | smallint    | NOT NULL     | Horas de cátedra.                                 |
| horas_taller      | smallint    | NOT NULL     | Horas de taller.                                   |
| horas_laboratorio | smallint    | NOT NULL     | Horas de laboratorio.                              |
| horas_docencia    | smallint    | NOT NULL     | Horas de docencia.                                 |
| horas_autonomas   | smallint    | NOT NULL     | Horas de estudio autonoma.                         |
| fecha_creacion    | timestamp   | NOT NULL     | Fecha de creación (Default: now()).               |
| esta_activo       | boolean     | NOT NULL     | (Default: TRUE).                                   |

### Plan

| Columna              | Tipo      | Restricción | Descripcion               |
| -------------------- | --------- | ------------ | ------------------------- |
| id_plan              | smallint  | PK           | Identificador del plan.   |
| id_carrera           | integer   | FK, NOT NULL | Carrera asociada al plan. |
| agno                 | smallint  | -            | Año del plan.            |
| version              | smallint  | -            | Versión del plan.        |
| creditos_sct_totales | smallint  | -            | Total de créditos SCT.   |
| fecha_creacion       | timestamp | NOT NULL     | (Default: now()).         |
| esta_activo          | boolean   | NOT NULL     | (Default: TRUE).          |

### Asignacion_Plan

| Columna              | Tipo      | Restricción | Descripcion                     |
| -------------------- | --------- | ------------ | ------------------------------- |
| id_asignatura        | integer   | PK, FK       | Identificador de la asignatura. |
| id_plan              | smallint  | PK, FK       | Identificador del plan.         |
| agno_planificado     | smallint  | NOT NULL     | Año planificado.               |
| semestre_planificado | smallint  | NOT NULL     | Semestre planificado.           |
| tipo_ramo            | smallint  | -            | Tipo de ramo.                   |
| fecha_creacion       | timestamp | NOT NULL     | (Default: now()).               |
| esta_activo          | boolean   | NOT NULL     | (Default: TRUE).                |

### Programa

| Columna        | Tipo      | Restricción     | Descripcion                              |
| -------------- | --------- | ---------------- | ---------------------------------------- |
| id_programa    | integer   | PK               | Identificador del programa.              |
| unc_programa   | smallint  | NOT NULL         | UNC del programa.                        |
| fecha_creacion | timestamp | NOT NULL         | (Default: now()).                        |
| es_actual      | boolean   | NOT NULL         | (Default: TRUE).                         |
| id_usuario     | integer   | FK, UQ, NOT NULL | Usuario asociado.                        |
| id_curso       | integer   | FK, NOT NULL     | Curso asociado.                          |
| es_plantilla   | boolean   | FK, NOT NULL     | Indica si es plantilla (Default: FALSE). |

Obs: Plantilla indica si el programa es un borrador que solo el academico/docente puede modificar con fines de prueba.

### Usuario

| Columna        | Tipo        | Restricción | Descripcion               |
| -------------- | ----------- | ------------ | ------------------------- |
| id_usuario     | integer     | PK           | Identificador de usuario. |
| username       | varchar(30) | NOT NULL     | Nombre de usuario.        |
| passhash       | varchar     | NOT NULL     | Hash de la contraseña.   |
| email          | varchar     | -            | Correo electrónico.      |
| nombre1        | text        | NOT NULL     | Primer nombre.            |
| nombre2        | text        | -            | Segundo nombre.           |
| apellido1      | text        | NOT NULL     | Primer apellido.          |
| apellido2      | text        | -            | Segundo apellido.         |
| rut            | varchar(20) | UQ, NOT NULL | RUT único.               |
| fecha_creacion | timestamp   | NOT NULL     | (Default: now()).         |
| esta_activo    | boolean     | NOT NULL     | (Default: TRUE).          |

### Contexto

| Columna          | Tipo    | Restricción | Descripcion                   |
| ---------------- | ------- | ------------ | ----------------------------- |
| id_contexto      | integer | PK           | Identificador del contexto.   |
| contexto_display | text    | -            | Nombre mostrado del contexto. |

### Tipo_Contexto

| Columna            | Tipo     | Restricción | Descripcion                     |
| ------------------ | -------- | ------------ | ------------------------------- |
| id_tipo_contexto   | smallint | PK           | Identificador tipo de contexto. |
| categoria          | smallint | -            | Categoría.                     |
| tabla_referenciada | smallint | -            | Referencia a tabla.             |
| id_contexto        | integer  | PK, FK       | Contexto asociado.              |

### Estudiante

| Columna       | Tipo    | Restricción     | Descripcion               |
| ------------- | ------- | ---------------- | ------------------------- |
| id_estudiante | integer | PK               | Identificador estudiante. |
| agno_ingreso  | integer | -                | Año de ingreso.          |
| id_carrera    | integer | FK, NOT NULL     | Carrera.                  |
| id_usuario    | integer | FK, UQ, NOT NULL | Usuario asociado.         |

### Docente

| Columna    | Tipo    | Restricción     | Descripcion            |
| ---------- | ------- | ---------------- | ---------------------- |
| id_docente | integer | PK               | Identificador docente. |
| grado      | text    | -                | Grado académico.      |
| titulo     | text    | -                | Título profesional.   |
| cargo      | text    | -                | Cargo.                 |
| id_usuario | integer | FK, UQ, NOT NULL | Usuario asociado.      |

### Rol

| Columna | Tipo        | Restricción | Descripcion        |
| ------- | ----------- | ------------ | ------------------ |
| id_rol  | smallint    | PK           | Identificador rol. |
| nombre  | varchar(50) | -            | Nombre del rol.    |

### Permiso

| Columna     | Tipo        | Restricción | Descripcion            |
| ----------- | ----------- | ------------ | ---------------------- |
| id_permiso  | smallint    | PK           | Identificador permiso. |
| slug        | varchar(50) | NOT NULL     | Slug único.           |
| nombre      | text        | NOT NULL     | Nombre del permiso.    |
| descripcion | text        | -            | Descripción.          |

### Usuario_Rol_Asignacion

| Columna        | Tipo         | Restricción | Descripcion                  |
| -------------- | ------------ | ------------ | ---------------------------- |
| asignado_por   | integer      | FK, NOT NULL | Usuario que asignó.         |
| duracion       | interval DAY | NOT NULL     | Duración de la asignación. |
| fecha_creacion | timestamp    | NOT NULL     | (Default: now()).            |
| esta_activo    | boolean      | NOT NULL     | (Default: TRUE).             |
| id_contexto    | integer      | PK, FK       | Contexto.                    |
| id_rol         | smallint     | PK, FK       | Rol asignado.                |
| id_usuario     | integer      | PK, FK       | Usuario receptor.            |

### Asignacion_Rol_Permiso

| Columna                | Tipo     | Restricción | Descripcion                  |
| ---------------------- | -------- | ------------ | ---------------------------- |
| puede_delegar_permisos | boolean  | NOT NULL     | Si puede delegar el permiso. |
| id_rol                 | smallint | PK, FK       | Rol.                         |
| id_permiso             | smallint | PK, FK       | Permiso.                     |

### Usuario_Permiso_Especial

| Columna        | Tipo         | Restricción | Descripcion            |
| -------------- | ------------ | ------------ | ---------------------- |
| fecha_inicio   | timestamp    | NOT NULL     | Fecha inicio vigencia. |
| fecha_fin      | timestamp    | NOT NULL     | Fecha fin vigencia.    |
| duracion_dias  | interval DAY | NOT NULL     | Duración.             |
| puede_delegar  | boolean      | NOT NULL     | Si puede delegar.      |
| fecha_creacion | timestamp    | NOT NULL     | (Default: now()).      |
| esta_activo    | boolean      | NOT NULL     | (Default: TRUE).       |
| id_usuario     | integer      | PK, FK       | Usuario.               |
| id_permiso     | smallint     | PK, FK       | Permiso especial.      |

### Curso

| Columna            | Tipo        | Restricción     | Descripcion              |
| ------------------ | ----------- | ---------------- | ------------------------ |
| id_curso           | integer     | PK               | Identificador curso.     |
| cod_curso_intranet | varchar     | UQ               | Código Intranet.        |
| fecha_inicio       | date        | -                | Fecha de inicio.         |
| agno_real          | smallint    | -                | Año real.               |
| semestre_real      | smallint    | -                | Semestre real.           |
| estado_interno     | varchar(20) | -                | (Default: 'ABIERTO').    |
| estado_acta        | varchar(20) | -                | (Default: 'NO_ENVIADO'). |
| es_plantilla       | boolean     | UQ, NOT NULL     | (Default: FALSE).        |
| id_contexto        | integer     | FK, UQ, NOT NULL | Contexto del curso.      |
| id_asignatura      | integer     | FK               | Asignatura base.         |
| id_plan            | smallint    | FK               | Plan base.               |
| id_docente         | integer     | FK, NOT NULL     | Docente asignado.        |

### Inscripcion_Curso

| Columna             | Tipo        | Restricción | Descripcion                                   |
| ------------------- | ----------- | ------------ | --------------------------------------------- |
| cod_inscripcion_uta | varchar     | UQ           | Código inscripción.                         |
| num_intento         | smallint    | -            | (Default: 1).                                 |
| fecha_inscripcion   | date        | -            | Fecha inscripción.                           |
| estado_inscripcion  | varchar(20) | -            | (Default: 'INSCRITO').                        |
| promedio_parcial    | decimal     | -            | Calculado con todas las notas del estudiante. |
| id_curso            | integer     | PK, FK       | Curso.                                        |
| id_estudiante       | integer     | PK, FK       | Estudiante.                                   |

### Seccion

| Columna         | Tipo     | Restricción | Descripcion             |
| --------------- | -------- | ------------ | ----------------------- |
| id_seccion      | smallint | PK, UQ       | Identificador sección. |
| id_curso        | integer  | PK, FK, UQ   | Curso.                  |
| es_plantilla    | boolean  | UQ, NOT NULL | (Default: FALSE).       |
| id_tipo_seccion | smallint | FK, NOT NULL | Tipo seccion.           |

### Tipo_Seccion

| Columna         | Tipo        | Restricción | Descripcion         |
| --------------- | ----------- | ------------ | ------------------- |
| id_tipo_seccion | smallint    | PK           | Identificador tipo. |
| tipo            | varchar(50) | -            | Nombre del tipo.    |

### Inscripcion_Seccion

| Columna       | Tipo     | Restricción | Descripcion          |
| ------------- | -------- | ------------ | -------------------- |
| nota_seccion  | smallint | -            | Nota en la sección. |
| id_seccion    | smallint | PK, FK       | Sección.            |
| id_curso      | integer  | PK, FK       | Curso.               |
| id_estudiante | integer  | PK, FK       | Estudiante.          |

### Unidad

| Columna      | Tipo     | Restricción | Descripcion               |
| ------------ | -------- | ------------ | ------------------------- |
| id_unidad    | smallint | PK, UQ       | Identificador unidad.     |
| num_unidad   | smallint | UQ           | Número de unidad.        |
| nombre       | varchar  | -            | Nombre unidad.            |
| descripcion  | text     | -            | Descripción.             |
| id_curso     | integer  | PK, FK, UQ   | Curso.                    |
| es_plantilla | boolean  | FK           | Si pertenece a plantilla. |

### Actividad

| Columna         | Tipo     | Restricción | Descripcion              |
| --------------- | -------- | ------------ | ------------------------ |
| id_actividad    | integer  | PK           | Identificador actividad. |
| nombre          | text     | -            | Nombre.                  |
| fecha_limite    | date     | -            | Fecha límite.           |
| visible         | boolean  | NOT NULL     | (Default: TRUE).         |
| tipo_actividad  | integer  | NOT NULL     | Tipo actividad.          |
| tipo_entrega    | varchar  | NOT NULL     | Tipo entrega.            |
| es_grupal       | boolean  | NOT NULL     | (Default: FALSE).        |
| max_integrantes | smallint | NOT NULL     | (Default: 1).            |
| es_plantilla    | boolean  | NOT NULL     | (Default: FALSE).        |
| id_curso        | integer  | FK, NOT NULL | Curso.                   |
| id_seccion      | smallint | FK, NOT NULL | Sección.                |
| id_unidad       | smallint | FK, NOT NULL | Unidad.                  |

### Actividad_Asignada

| Columna      | Tipo     | Restricción | Descripcion          |
| ------------ | -------- | ------------ | -------------------- |
| grupo        | integer  | PK           | Identificador grupo. |
| nota         | smallint | -            | Nota.                |
| id_actividad | integer  | FK, NOT NULL | Actividad.           |
| id_estado    | smallint | FK, NOT NULL | Estado.              |

### Asignado_Actividad

| Columna            | Tipo     | Restricción | Descripcion                 |
| ------------------ | -------- | ------------ | --------------------------- |
| nota_individual    | smallint | -            | Nota individual.            |
| diferencia_decimas | smallint | -            | Diferencia décimas.        |
| grupo              | integer  | PK, FK       | Grupo (Actividad Asignada). |
| id_estudiante      | integer  | PK, FK       | Estudiante.                 |

### Estado_Actividad

| Columna     | Tipo     | Restricción | Descripcion           |
| ----------- | -------- | ------------ | --------------------- |
| id_estado   | smallint | PK           | Identificador estado. |
| titulo      | text     | NOT NULL     | Título.              |
| descripcion | text     | -            | Descripción.         |

### Agenda

| Columna | Tipo | Restricción | Descripcion   |
| ------- | ---- | ------------ | ------------- |
| -       | -    | -            | Tabla vacía. |
