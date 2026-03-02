# UTAMED — Contexto Completo del Sistema

**UTAMED** es un sistema de gestión académica universitaria diseñado para administrar la estructura organizacional de una institución educativa, sus cursos, los programas/syllabi de cada asignatura y el acceso de los distintos actores del proceso educativo. El sistema actúa como la plataforma central donde se define qué se enseña (estructura académica), quién lo enseña (docentes), a quiénes (estudiantes), cómo está organizado el contenido del curso (programa/syllabus) y quién puede supervisar o apoyar ese proceso (ayudantes, administradores).

La arquitectura es una aplicación **monolítica full-stack** construida con **Laravel 12** en el backend y **Svelte 5 + Inertia.js** en el frontend. No existe una separación de API REST independiente: el servidor renderiza páginas completas vía Inertia, que actúa como un puente reactivo entre el routing de Laravel y los componentes Svelte. La autenticación opera con **sesiones de servidor** gestionadas por Laravel Fortify, sin tokens JWT. La base de datos es **PostgreSQL** con múltiples esquemas (`administrativo`, `curso`, `usuario`) que mapean directamente a la separación lógica de responsabilidades.

---

## Stack Técnico

| Capa               | Tecnología                             | Propósito                                              |
| ------------------ | -------------------------------------- | ------------------------------------------------------ |
| Backend framework  | Laravel 12, PHP 8.x                    | Routing, controladores, Eloquent ORM, Policies         |
| Frontend framework | Svelte 5 (Runes API)                   | Componentes reactivos, state con `$state`/`$derived`   |
| Puente SSR/SPA     | Inertia.js                             | Navegación sin recarga, props del servidor al frontend |
| Base de datos      | PostgreSQL (multi-esquema)             | Persistencia con modelos Eloquent                      |
| Autenticación      | Laravel Fortify                        | Login, logout, recuperación de contraseña, 2FA         |
| Autorización       | Laravel Policies + middleware de grupo | RBAC con contextos de permisos                         |
| Estilos            | Tailwind CSS                           | Diseño responsivo con componentes reutilizables        |
| Build frontend     | Vite + ESBuild                         | Compilación y HMR                                      |
| Notificaciones     | Laravel Notifications (mail)           | Reset de contraseña por correo                         |

---

## Jerarquía de Entidades Académicas

La estructura organizacional sigue una cadena jerárquica estricta. Cada nivel contiene al anterior y tiene su propio contexto de permisos:

```
Universidad
└── Facultad                    (ej. Facultad de Ingeniería)
    └── Departamento            (ej. Dpto. de Sistemas)
        └── Carrera             (ej. Ingeniería en Software)
            └── Plan de Estudio (ej. Plan 2022)
                └── Asignación Plan (asignatura dentro del plan, con créditos y semestre)
                    └── Asignatura  (ej. Programación Orientada a Objetos)
                        └── Curso   (instancia semestral, ej. POO-2024-S1)
                            ├── Secciones/Paralelos  (ej. Sección A, Sección B)
                            │   └── Inscripciones    (Estudiante ↔ Sección)
                            ├── Programa / Syllabus  (documento JSONB con 9 secciones)
                            └── Equipo               (Docente responsable + Ayudantes)
```

Cada entidad a partir de Facultad tiene un `id_contexto` que sirve como ámbito para la asignación de roles y permisos. Esto permite, por ejemplo, que un usuario sea Docente en el contexto del Curso A pero no en el Curso B.

---

## Roles del Sistema

El sistema implementa un modelo **RBAC (Control de Acceso Basado en Roles) con contextos**. Los roles no son globales: se asignan a un usuario dentro de un contexto específico (una facultad, un curso, etc.), aunque en la práctica los roles de Administrador y SuperAdmin suelen operar en contexto global.

| Rol               | Middleware de acceso                       | Descripción de responsabilidad                                                                                                                                  |
| ----------------- | ------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **SuperAdmin**    | Bypass en `before()` de todas las Policies | Acceso irrestricto a todo el sistema. No necesita ningún permiso evaluado.                                                                                      |
| **Administrador** | `is_admin` en grupo `/admin/*`             | Gestiona toda la estructura académica, usuarios, permisos y el ciclo de vida de los programas.                                                                  |
| **Docente**       | `is_docente` en grupo `/docente/*`         | Responsable de sus cursos asignados. Crea y edita programas, gestiona el equipo de ayudantes y las inscripciones de sus cursos.                                 |
| **Estudiante**    | `is_estudiante` en grupo `/estudiante/*`   | Acceso de solo lectura a sus propios cursos inscritos y a los programas aprobados de esos cursos.                                                               |
| **Ayudante**      | `is_ayudante` en grupo `/ayudante/*`       | Estudiante con rol adicional en un curso específico. Sus capacidades de escritura son granulares y configuradas individualmente por el docente o administrador. |

El sidebar de navegación (`RoleSidebar.svelte`) lee `$page.props.auth.roles` (array de strings) en cada request y muestra u oculta secciones del menú según los roles activos del usuario.

---

## Módulos por Rol

---

### 🔴 Administrador — Panel `/admin/*`

El administrador tiene la responsabilidad más amplia del sistema. Accede mediante el prefijo `/admin/` que está protegido por el middleware `is_admin`, el cual verifica que el usuario autenticado tenga un rol de Administrador o SuperAdmin activo y no eliminado.

#### 1. Estructura Académica

La gestión de la estructura académica es la función más crítica para el funcionamiento del sistema. Sin esta jerarquía, no es posible crear cursos ni programas. El administrador puede crear, editar y eliminar cada nivel:

| Módulo                | Qué permite hacer            | Detalles importantes                                                                                                                                                                                                        |
| --------------------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Facultades**        | CRUD completo                | Al crear una facultad, se genera automáticamente un `Contexto` asociado (necesario para asignación de permisos). Tiene soft-delete. Protegido por `FacultadPolicy`.                                                         |
| **Departamentos**     | CRUD completo                | Pertenecen a una Facultad. Búsqueda y listado paginado.                                                                                                                                                                     |
| **Carreras**          | CRUD completo                | Pertenecen a un Departamento. Acceso rapido via select de cascada (Facultad → Departamento → Carrera).                                                                                                                      |
| **Planes de Estudio** | CRUD completo                | Cada Carrera puede tener múltiples planes (ej. Plan 2018, Plan 2022). Permite comparar/ver detalle de malla.                                                                                                                |
| **Malla Curricular**  | Asignar asignaturas a planes | Desde la vista Detalle Malla (`DetalleMalla.svelte`), el administrador vincura asignaturas a un plan con créditos y semestre correspondiente.                                                                               |
| **Asignaturas**       | CRUD completo                | Repositorio central de materias. Una asignatura puede estar en múltiples planes.                                                                                                                                            |
| **Cursos**            | CRUD + asignación docente    | Un curso es la instancia semestral de una asignatura. Tiene `cod_curso`, `fecha_inicio`, `fecha_fin`, `semestre_real`, `agno_real`, `estado_interno` y `estado_acta`. Se puede asignar o desasignar un docente responsable. |

#### 2. Gestión de Secciones

Dentro de cada curso existen **secciones** (también llamadas paralelos o grupos). El administrador puede crear secciones con su tipo, capacidad y asignación de docente por sección. Esto permite que un mismo curso tenga múltiples grupos con diferentes horarios o docentes.

#### 3. Gestión de Usuarios

El módulo de usuarios es uno de los más críticos en materia de seguridad. El administrador puede:

- **Crear y editar usuarios**: Datos personales, RUT, correo, nombre completo.
- **Activar/desactivar usuarios**: Control de acceso sin eliminar la cuenta (`toggleActive`).
- **Cambiar contraseña**: El administrador puede resetear contraseñas de otros usuarios.
- **Asignar roles**: Por usuario y por contexto. Por ejemplo, asignar el rol "Docente" al usuario X en el contexto del Curso Y.
- **Gestionar permisos granulares**: Desde la pantalla `Usuarios.svelte`, se puede abrir el modal de permisos (`PermissionsModal`) para sincronizar permisos específicos a un usuario dentro de un contexto. Esto usa la tabla `usuario.usuario_permiso_especial`.
- **Distinguir tipos de usuario**: El sistema diferencia entre usuarios regulares, con perfil Docente (`usuario.docente`) y con perfil Estudiante (`usuario.estudiante`, con carrera asignada).

#### 4. Gestión de Programas (Syllabus)

El programa es el documento académico central del sistema. El administrador tiene una vista global (`Programas.svelte`) que lista todos los programas con filtros por estado. Sus acciones son:

- **Ver listado global**: Todos los programas del sistema con indicadores de estado (`BASICO_COMPLETO`, `COMPLETO`, `APROBADO`, `PUBLICADO`).
- **Revisar un programa**: Accede al detalle completo del syllabus de un curso. Ve todas las 9 secciones con su contenido.
- **Aprobar un programa**: Si el programa está en estado `COMPLETO`, el administrador puede aprobarlo. Esto cambia el estado a `APROBADO`, momento en el cual el docente ya no puede editarlo sin que el administrador lo rechace primero.
- **Rechazar un programa**: Devuelve el programa al docente para que realice correcciones. El programa regresa a un estado editable.
- **Iniciar creación desde admin**: El administrador puede disparar la creación de un programa en nombre del docente.

#### 5. Equipo del Curso (CourseTeam)

Desde el panel de administración también se puede gestionar el equipo de cada curso. Esto incluye:
- Ver la lista actual de docente y ayudantes asignados.
- Añadir un ayudante (buscando usuarios del sistema).
- Quitar un ayudante del curso.
- Definir los **permisos granulares** del ayudante: qué secciones del programa puede crear o editar, usando el sistema de slugs `cursos/programas:modificar:modulo_X`.

#### 6. Inscripciones de Estudiantes

El módulo de inscripciones permite matricular y gestionar la asistencia de estudiantes a los cursos:
- CRUD completo de inscripciones (`estado_inscripcion`).
- Filtros por curso para ver quiénes están inscritos.
- Búsqueda de estudiantes disponibles para inscribir.
- **Exportación CSV** de la lista de inscritos.

---

### 🟡 Docente — Panel `/docente/*`

El docente es el actor principal del proceso educativo. Su acceso está acotado a los cursos en los que tiene secciones asignadas. El middleware `is_docente` verifica que el usuario tenga el rol Docente activo.

#### 1. Dashboard del Docente

Al ingresar, el docente ve un resumen de todos sus cursos (obtenidos a través de la relación `Usuario → Docente → Secciones → Cursos`). El dashboard muestra estadísticas básicas (número de cursos, programas creados) y permite ver de inmediato qué cursos ya tienen un programa activo y cuáles no.

#### 2. Mis Cursos

La vista de cursos (`Cursos.svelte`) muestra los cursos del docente organizados por semestre. Para cada curso el docente puede:
- Ver el detalle completo del curso (asignatura, carrera, fechas, código, créditos).
- Navegar al programa del curso.
- Acceder a las actividades y al equipo del curso.

#### 3. Programa / Syllabus

Esta es la función más importante del docente. El ciclo de vida del programa comienza aquí:

- **Sin programa**: El docente ve un botón para "Crear Programa". Al hacer clic, se abre el `SyllabusModal`, un wizard de múltiples pasos que guía al docente por las 9 secciones del programa. Si el docente completa solo las secciones básicas (I y II), el programa queda en estado `BASICO_COMPLETO`. Si completa todo, queda en `COMPLETO`.
- **BASICO_COMPLETO**: El programa existe pero le faltan las secciones III–IX. El docente ve un botón "Completar Syllabus" que reabre el wizard pre-cargado con los datos existentes para continuar desde donde lo dejó.
- **COMPLETO**: El docente puede seguir editando el programa hasta que el administrador lo revise.
- **APROBADO**: El programa fue aprobado por el administrador. El docente ya no puede editarlo.

El `SyllabusModal` usa datos contextuales del curso (secciones, actividades cargadas) para auto-completar algunos campos, especialmente la sección I (datos generales) y la sección VI (cronograma basado en las actividades del curso).

- **Eliminar programa**: Solo si aún no ha sido aprobado.

#### 4. Actividades del Curso

El docente puede gestionar las actividades académicas asociadas a su curso (evaluaciones, tareas, prácticas, etc.). Estas actividades se almacenan en la base de datos y el `SyllabusModal` las usa para poblar automáticamente la sección del cronograma del programa. Las operaciones son CRUD completo por curso.

#### 5. Equipo del Curso

El docente puede gestionar quiénes le asisten en el curso:
- Ver los ayudantes actuales.
- **Agregar un ayudante**: Busca estudiantes registrados y los asigna al curso con rol Ayudante. Esto crea una entrada en `usuario.usuario_rol_asignacion` con el contexto del curso.
- **Quitar un ayudante**: Revoca la asignación.
- **Configurar permisos del ayudante**: Puede definir exactamente qué permisos tiene cada ayudante sobre el programa (qué secciones puede ver o editar).

#### 6. Inscripciones

El docente también tiene acceso a la gestión de inscripciones de sus cursos: ver la lista de estudiantes inscritos, crear nuevas inscripciones, y editar el estado de inscripciones existentes.

---

### 🟢 Estudiante — Panel `/estudiante/*`

El estudiante es el actor con el acceso más restringido del sistema. El middleware `is_estudiante` verifica el rol activo. Su rol en el sistema es **exclusivamente de consulta**.

#### 1. Dashboard del Estudiante

Al ingresar, el sistema verifica que el usuario tenga un perfil de estudiante (`usuario.estudiante`) y carga todos los cursos en los que tiene una inscripción con estado `INSCRITO`. Se muestra el nombre del estudiante, su número de cursos activos y la lista de cursos con nombre de asignatura, carrera y fechas.

Una nota especial: si el estudiante también tiene el rol de Ayudante asignado, se muestra un indicador `isAyudante` que puede activar componentes adicionales en la vista del dashboard.

#### 2. Mis Cursos

El estudiante puede listar sus cursos inscritos y ver el detalle de cada uno (nombre del curso, código, asignatura, información del docente, carrera).

#### 3. Ver Programa / Syllabus

La función más relevante para el estudiante es poder consultar el programa académico del curso. Solo puede ver programas en estado `APROBADO` o `PUBLICADO`. El programa se renderiza de forma de solo lectura usando el mismo componente `ProgramaDocument` que usan los demás roles, pero sin ningún botón de edición o acción.

El estudiante **no puede crear, editar ni eliminar ningún recurso del sistema**.

---

### 🔵 Ayudante — Panel `/ayudante/*`

El ayudante es un estudiante que ha recibido un rol adicional en uno o más cursos específicos. Es el rol más dinámico en términos de permisos, ya que sus capacidades varían según lo que el docente o administrador haya configurado para cada curso.

Al autenticarse, sus permisos granulares para cada curso se cargan en `$page.props.auth.ayudante_courses`, un array que contiene por cada curso: `id_curso`, `nombre`, `tiene_programa` y un `userPermissions[]` con los slugs y estados de cada permiso. Esto permite que el frontend evalúe en tiempo real qué acciones mostrar sin necesidad de hacer peticiones adicionales.

#### 1. Dashboard del Ayudante

El ayudante ve la lista de cursos en los que está asignado como ayudante (basado en su `id_contexto`). Para cada curso ve si ya existe un programa creado. El dashboard reutiliza la vista general `Dashboard.svelte` con el componente `DashboardAyudante`.

#### 2. Mis Cursos como Ayudante

Vista de todos los cursos donde tiene rol Ayudante activo. Puede acceder al detalle de cada curso (`Show.svelte`), que muestra la información del curso y un indicador de sus permisos disponibles en ese contexto.

#### 3. Programa / Syllabus (con permisos granulares)

Esta es la función diferenciadora del rol Ayudante. Lo que puede hacer sobre el programa depende de los permisos asignados:

| Acción                        | Slug del permiso requerido                               |
| ----------------------------- | -------------------------------------------------------- |
| Ver programa del curso        | siempre permitido                                        |
| Crear un programa nuevo       | `cursos/programas:crear:*`                               |
| Editar secciones del programa | `cursos/programas:modificar:modulo_1`, `:modulo_2`, etc. |
| Acceder al JSON del programa  | siempre permitido                                        |

El ayudante usa el mismo `SyllabusModal` que el docente para crear o editar programas, pero las rutas apuntan a `/ayudante/cursos/{curso}/programa/...`.

---

## El Programa / Syllabus — Funcionamiento Detallado

El programa es el documento central que define el contenido académico de un curso. Se almacena en la columna `data_syllabus` de la tabla `administrativo.programa` como un campo **JSONB**, lo que permite una estructura flexible y versionable sin necesidad de alterar el esquema de la base de datos.

### Estructura del JSONB

El documento JSONB tiene dos partes principales: `metadata` (información del programa) y `secciones` (el contenido académico organizado en 9 secciones).

```json
{
  "metadata": {
    "tipo_syllabus": "BASICO | COMPLETO",
    "version": 1,
    "created_at": "2024-01-15"
  },
  "secciones": {
    "seccion_1": { "datos_generales": { ... } },
    "seccion_2": { "descripcion": "...", "objetivos": [...] },
    "seccion_3": { "unidades": [...] },
    "seccion_4": { "estrategias_metodologicas": [...] },
    "seccion_5": { "recursos": [...] },
    "seccion_6": { "cronograma": [...] },
    "seccion_7": { "evaluacion": { ... } },
    "seccion_8": { "bibliografia": [...] },
    "seccion_9": { "informacion_adicional": "..." }
  }
}
```

### Estados del Programa

| Estado            | Descripción                              | Quién puede editarlo             |
| ----------------- | ---------------------------------------- | -------------------------------- |
| `BASICO_COMPLETO` | Wizard completó solo secciones I y II    | Docente, Ayudante con permiso    |
| `COMPLETO`        | Todas las 9 secciones completadas        | Docente, Ayudante con permiso    |
| `APROBADO`        | Revisado y aprobado por el Administrador | Nadie (requiere rechazo primero) |
| `PUBLICADO`       | Publicado para acceso general            | Solo lectura para todos          |

### Transiciones de Estado

```
Sin programa
    │  (Docente/Ayudante crea con wizard)
    ▼
BASICO_COMPLETO  ─────────────────────────────────────────────────────────┐
    │  (Docente/Ayudante completa                                          │
    │   las secciones III–IX)                                              │
    ▼                                                                      │
COMPLETO                                                                   │
    │  (Administrador revisa y aprueba)                                    │
    ▼                                                                      │
APROBADO                                                                   │
    │  (Publicación)                                                       │
    ▼                                                                      │
PUBLICADO  ←── visible en modo lectura para Estudiantes                   │
                                                                           │
    ◄──────────────────── Admin RECHAZA ───────────────────────────────────┘
    (vuelve a COMPLETO o BASICO_COMPLETO según el estado anterior,
     el docente puede volver a editar y reenviar)
```

El versionado es automático: cada vez que se regenera un programa, se incrementa `version_programa` y se marca el anterior como `es_actual = false`.

### Las 9 Secciones del Syllabus

| Sección | Nombre                     | Contenido principal                                                          |
| ------- | -------------------------- | ---------------------------------------------------------------------------- |
| I       | Datos Generales            | Código del curso, créditos, carrera, semestre, nombre del docente, modalidad |
| II      | Descripción y Objetivos    | Descripción de la asignatura, objetivos generales y específicos              |
| III     | Unidades Temáticas         | Lista de unidades con sus contenidos y horas teóricas/prácticas              |
| IV      | Estrategias Metodológicas  | Métodos de enseñanza utilizados (clases magistrales, laboratorio, etc.)      |
| V       | Recursos y Materiales      | Herramientas, software, materiales necesarios para el curso                  |
| VI      | Cronograma de Actividades  | Calendario semana a semana con actividades, fechas y evaluaciones            |
| VII     | Sistema de Evaluación      | Tipos de evaluación, porcentajes, criterios de aprobación                    |
| VIII    | Bibliografía               | Referencias obligatorias y complementarias                                   |
| IX      | Información Complementaria | Políticas de asistencia, conducta, contacto del docente                      |

---

## Sistema de Autorización — Modelo RBAC con Contextos

La autorización en UTAMED opera en tres capas independientes y complementarias:

### Capa 1 — Middleware de Grupo (primera barrera)

Aplicado al grupo de rutas completo. Bloquea el acceso antes de llegar a cualquier controlador.

```
/admin/*      → middleware is_admin    → verifica rol 'Administrador' o 'SuperAdmin' activo
/docente/*    → middleware is_docente  → verifica rol 'Docente' activo
/estudiante/* → middleware is_estudiante → verifica rol 'Estudiante' activo
/ayudante/*   → middleware is_ayudante → verifica rol 'Ayudante' activo en al menos un contexto
```

Si falla: redirige a `/login` o a la página de error según configuración.

### Capa 2 — Laravel Policies (segunda barrera, en el controlador)

Cada controlador llama `$this->authorize('accion', $modelo)` que delega a la Policy correspondiente. Las policies heredan de una clase base autogenerada (`BaseFacultadPolicy`, `BaseProgramaPolicy`, etc.) que implementa el trait `HasBasePolicyMethods`.

**Bypass de SuperAdmin**: El método `before(Usuario $user, string $ability)` en el trait retorna `true` para cualquier usuario con `isSuperAdmin() === true`, antes de evaluar cualquier permiso.

Las policies consultan la vista de base de datos `vw_permisos_usuario` que resuelve los permisos efectivos por usuario y contexto (considerando permisos de rol + permisos especiales individuales).

### Capa 3 — Frontend Condicional (solo UI, no es barrera de seguridad)

El servidor envía al frontend flags booleanos como `canCreate`, `canEdit`, `canDelete` evaluados en el momento del render. Los componentes Svelte usan `{#if canCreate}` para mostrar u ocultar botones. Esto mejora la experiencia de usuario, pero **no reemplaza la validación del backend**.

### Modelo de Permisos por Contexto

Los permisos no son globales: se asignan a la triada `(usuario, contexto, permiso)`.

- Cada entidad de la jerarquía tiene un `id_contexto` (FK a `usuario.contexto`).
- Un permiso asociado a un contexto de Facultad puede heredarse a sus Cursos hijos.
- Los slugs siguen el patrón: `recurso/sub-recurso:accion:ambito`.
  - Ejemplo: `cursos/programas:modificar:modulo_1`
  - Ejemplo: `facultades:ver`, `facultades:crear`

---

## Datos Compartidos Globalmente (Inertia)

En cada request, `HandleInertiaRequests.php` inyecta en `$page.props` el siguiente objeto, disponible en **todos** los componentes Svelte sin necesidad de prop drilling:

```
$page.props.auth.user              → objeto Usuario completo con relaciones cargadas
$page.props.auth.roles             → array de nombres de roles activos: ['Administrador', ...]
$page.props.auth.docente           → perfil Docente del usuario (null si no aplica)
$page.props.auth.estudiante        → perfil Estudiante del usuario (null si no aplica)
$page.props.auth.docente_courses   → cursos asignados al docente (con carrera y tiene_programa)
$page.props.auth.estudiante_courses→ cursos inscritos del estudiante
$page.props.auth.ayudante_courses  → cursos donde es ayudante + userPermissions[] por curso
$page.props.flash.success          → mensaje de éxito de la sesión (POST/PUT/DELETE)
$page.props.flash.error            → mensaje de error de la sesión
$page.props.name                   → nombre de la aplicación
$page.props.quote                  → cita inspiradora aleatoria (mensaje + autor)
```

---

## Autenticación

El sistema usa **sesiones Laravel** con cookies HTTP-only. No hay JWT, no hay tokens en localStorage. El flujo de autenticación es:

1. El usuario accede a `/login`, ingresa email y contraseña.
2. Laravel Fortify valida las credenciales contra `usuario.usuario`.
3. Si son válidas, crea una sesión del servidor y envía la cookie `laravel_session`.
4. Cada request posterior incluye esa cookie automáticamente.
5. CSRF se protege con la cookie `XSRF-TOKEN` que Inertia/Axios leen automáticamente.
6. **Recuperación de contraseña**: El usuario solicita un reset, el sistema envía un correo via `ForgotPassword` notification (implementa `ShouldQueue` para envío asíncrono).
7. **2FA**: Disponible via Laravel Fortify, configurable por usuario desde `/settings/two-factor-authentication`.

Al autenticarse, el sistema evalúa el rol del usuario y lo redirige a su dashboard correspondiente:
- Docente → `/docente/dashboard`
- Estudiante → `/estudiante/dashboard`
- Ayudante → `/ayudante/dashboard`
- Admin/SuperAdmin → `/dashboard` (panel general)

---

## Auditoría y Logging

| Evento                     | Responsable                 | Datos registrados                                                     |
| -------------------------- | --------------------------- | --------------------------------------------------------------------- |
| Creación de Facultad       | `FacultadService::create()` | `id_facultad`, `nombre`, `id_contexto`, `actor_id`, `actor`           |
| Actualización de Facultad  | `FacultadService::update()` | `id_facultad`, `nombre_anterior`, `nombre_nuevo`, `actor_id`, `actor` |
| Eliminación de Facultad    | `FacultadService::delete()` | `id_facultad`, `nombre`, `actor_id`, `actor`                          |
| Error en creación de Curso | `CursoController::store()`  | mensaje de error + stack trace + datos del request                    |
| Carga de roles por request | `HandleInertiaRequests`     | `user_id`, `roles_count`, `roles`, `assignments`                      |

Todos los logs van al canal por defecto de Laravel (`storage/logs/laravel.log`). No existe una tabla de auditoría en base de datos (del tipo `activity_log`). El logging es mediante `Log::info()` y `Log::error()` de Laravel.

---

## Flujo Típico de Uso — Ciclo de Vida Completo

A continuación se describe el flujo completo desde la configuración hasta que un estudiante puede ver el programa de su curso:

```
1. ADMIN crea la estructura:
   Facultad → Departamento → Carrera → Plan → Asignatura → Curso

2. ADMIN crea un Usuario de tipo Docente y lo asigna al Curso.

3. ADMIN crea usuarios de tipo Estudiante y los inscribe en las Secciones del Curso.

4. DOCENTE accede a "Mi Curso" y hace clic en "Crear Programa".
   → Se abre el SyllabusModal (wizard de 9 secciones).
   → Si completa solo I y II: estado = BASICO_COMPLETO.
   → Si completa las 9: estado = COMPLETO.

5. DOCENTE puede asignar un AYUDANTE al curso con permisos específicos de edición.

6. AYUDANTE (con permiso) puede completar o editar secciones del programa
   desde su panel `/ayudante/`.

7. ADMIN revisa el programa desde la vista de Programas.
   → Si aprueba: estado = APROBADO.
   → Si rechaza: vuelve a COMPLETO/BASICO_COMPLETO (docente edita y reenvía).

8. ESTUDIANTE accede a su curso inscrito y puede ver el programa en modo
   solo-lectura (estado APROBADO o PUBLICADO).
```

