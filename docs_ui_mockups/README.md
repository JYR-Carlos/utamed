# Documentación UI/UX por Rol — UTAMED

Sistema institucional de gestión académica de la Universidad de Tarapacá (UTA),
en uso para la carrera de **Diseño Multimedia** y carreras afines.

> **Origen de esta documentación:** análisis directo del código fuente
> (`routes/web.php`, `app/Http/Middleware/*`, `app/Http/Controllers/*`,
> `app/Policies/*`, `app/Support/Permissions.php`, `resources/js/pages/**`).
> Cada afirmación es rastreable a un archivo del repositorio.
> **No contiene funcionalidad especulativa**; lo pendiente o inconsistente se
> marca explícitamente como `⚠️ HUECO`.

---

## Árbol de directorios

```
/docs_ui_mockups/
├── README.md                     ← este archivo (índice, matriz de roles, stack UI)
├── rol_super_admin.md            ← wildcard global, contexto Global
├── rol_administrador.md          ← panel /admin, gobierno de la estructura académica
├── rol_jefe_carrera.md           ← dashboards de carrera + aprobación de syllabus
├── rol_docente_titular.md        ← dueño del curso (DT): equipo, delegación, syllabus
├── rol_docente_componente.md     ← docente de cátedra/lab/taller (+ variantes)
├── rol_ayudante.md               ← apoyo acotado a un curso (syllabus + mensajería)
├── rol_estudiante.md             ← cursos, actividades, entregas, mensajería
└── vistas_compartidas.md         ← pantallas unificadas con variación por rol
```

---

## Stack y convenciones de UI (inferido del código)

| Aspecto | Implementación real |
|---|---|
| Backend | Laravel 12 + Inertia.js (`Inertia::render`) |
| Frontend | **Svelte 5** (runas `$state`, `$derived`, `$props`) + TypeScript |
| Estilos | Tailwind CSS + capa semántica propia (`.btn-*`, `.badge-*`, `.field-*`, `page-shell` en `app.css`) |
| Iconografía | `lucide-svelte` |
| Navegación | `@inertiajs/svelte` (`Link`, `router.visit`, `router.post`) — SPA sin recarga |
| Feedback | Props globales `flash.success` / `flash.error` (`HandleInertiaRequests::share`) |
| Datos parciales | `Inertia::lazy()` para hilos de conversación y entregas (carga bajo demanda) |
| Paleta institucional | Azul UTA `#002F6C`, arena `#F5F1EA`, tinta `#1A1A24`, gris texto `#5A5E6E`, acento admin `indigo-600` |
| Tema oscuro | **Desactivado a propósito** — no diseñar variantes dark |

### Layouts existentes (`resources/js/layouts/`)
`AdminLayout` · `DocenteLayout` · `StudentLayout` · `AyudanteLayout` · `AuthLayout` ·
`settings/Layout` — todos montan el **mismo** `RoleSidebar.svelte`.

---

## Roles reales implementados

Los nombres de rol son **strings en base de datos** (`usuario.rol.nombre`),
resueltos por `Usuario::hasRole()` / `hasAnyRole()` con normalización
`strtolower(trim())` (`app/Models/Usuario/Usuario.php:173`).

| Rol (string en BD) | Puerta de entrada | Middleware | Archivo |
|---|---|---|---|
| `Super Admin` / `SuperAdmin` | permiso wildcard global | `is_admin` | `rol_super_admin.md` |
| `Administrador` / `Admin` | rol en **contexto Global** | `is_admin` | `rol_administrador.md` |
| `Jefe de Carrera` | rol en contexto de categoría `carrera` | `is_docente` + `ResolvesJefaturaCarrera` | `rol_jefe_carrera.md` |
| `Docente Titular` | `curso.id_docente_titular` | `is_docente` | `rol_docente_titular.md` |
| `Docente Titular Restringido` | idem, con permisos recortados | `is_docente` | `rol_docente_componente.md` |
| `Docente Componente` | fila en `docente_componente` | `is_docente` | `rol_docente_componente.md` |
| `Docente Componente Colegiado` | varios docentes en un componente | `is_docente` | `rol_docente_componente.md` |
| `Ayudante` | rol en contexto de un curso | `is_ayudante` | `rol_ayudante.md` |
| `Estudiante` | rol asignado al inscribirse | `is_estudiante` | `rol_estudiante.md` |
| *(sin rol)* | — | `auth` | pantalla `SinRol.svelte` |

### ⚠️ Regla crítica de diseño: rol ≠ perfil

`IsDocente` exige **rol Docente\* Y fila `usuario.docente`**
(`app/Http/Middleware/IsDocente.php:44`). `IsStudent` exige sólo el rol
(`IsStudent.php:34`), pero los controladores comprueban `$user->estudiante`
y redirigen si falta (`Student/DashboardController.php:28`).
→ La UI debe contemplar el estado **"tiene rol pero no perfil"**: el usuario
aterriza en `/dashboard` con un `flash.error` y sin sección.

### ⚠️ Un usuario tiene N roles a la vez

`RoleSidebar.svelte:203` construye `availableSections` y, si hay más de una,
renderiza un **conmutador de pestañas de rol** en la cabecera del sidebar
(`Docente · Jefe de Carrera · Estudiante · Ayudantía · Administración`).
Casos reales soportados: docente que además es Jefe de Carrera; estudiante que
además es Ayudante (flag `isAyudante` en el dashboard del alumno).

---

## Sistema de autorización: RelBAC contextual

**No es RBAC plano.** Cada permiso se evalúa contra un **contexto**
(`app/Enums/ContextType.php`):

```
global → facultad → departamento → carrera → curso → componente → actividad
```

- Un rol asignado en el contexto "curso 42" **no** aplica al curso 43.
- `IsAdmin` exige el rol administrativo **en el contexto Global**
  (`IsAdmin.php:53`) — un "Administrador" de un departamento **no** entra a `/admin`.
- Catálogo de permisos: `app/Support/Permissions.php` (enum autogenerado,
  ~90 slugs con formato `recurso:acción` y comodines `recurso:*`).
- El frontend recibe `auth.permissions` (array de slugs) y lo consulta con
  `hasPermission()` / `usePermissions()`.
  > ⚠️ `HandleInertiaRequests.php:186` lleva el comentario `// FIX: esto esta mal
  > debe ser contextual`: los permisos globales del sidebar **no** están
  > contextualizados. Las vistas de curso sí reciben `userPermissions` por contexto.

### Familias de permisos (para agrupar toggles en mockups)
`actividades:*` · `actividades/grupos:*` · `asignaturas:*` · `carreras:*` ·
`carreras/planes:*` · `componentes:*` · `componentes/asistencia:*` ·
`componentes/docentesColegiados:*` · `componentes/inscripciones:*` · `cursos:*` ·
`cursos/inscripciones:*` · `cursos/programas:*` · `cursos/programas/modificar:modulo_1..9` ·
`cursos/unidades:*` · `departamentos:*` · `facultades:*` · `usuarios:*` ·
`usuarios/permisos:*` · `usuarios/permisos/roles:*`

---

## Modelo de dominio (entidades que se pintan en pantalla)

```
Facultad → Departamento → Carrera → Plan (malla) → AsignacionPlan → Asignatura
                                                          ↓
                                                        Curso  (id_docente_titular, letra_grupo,
                                                          │      agno_real, semestre_real, estado_interno)
                                                          ├── Componente (Cátedra / Laboratorio / Taller)
                                                          │      ├── DocenteComponente (es_titular)
                                                          │      └── InscripcionComponente (nota_componente) → Asistencia
                                                          ├── InscripcionCurso (estado_inscripcion)
                                                          ├── Programa (Syllabus, data_syllabus JSONB, estado, version)
                                                          └── Actividad ── ActividadAsignadaGrupo ── IntegranteGrupo
                                                                   ├── Rubrica
                                                                   └── Agenda (mensajes/entregas de la actividad)
```

**Dos sistemas de mensajería, deliberadamente separados:**

| | Nivel actividad | Nivel curso |
|---|---|---|
| Tabla | `agenda.agenda` | `curso.mensaje` + `interaccion_mensaje` |
| Entrada UI | **Sólo desde dentro de la actividad** | Sidebar + dashboard + bandeja del curso |
| Tipos | `Mensaje al profesor`, `Feedback`, `Entrega de archivo`, `Cancelación de entrega`, `Evaluación`, `Cierre de actividad` | `MENSAJE_INDIVIDUAL`, `MENSAJE_PARA_TODO_EL_CURSO` |
| Contenedor | grupo de actividad | componente (pestañas Cátedra/Lab) |

---

## Máquinas de estado que la UI debe representar

**Syllabus / Programa** (`Admin/ProgramaController`, `Administrativo/ProgramaController`):

```
BORRADOR ──completarBasico──▶ BASICO_COMPLETO ──enviarParaRevision──▶ COMPLETO
                                                                        │
                                                     aprobar (Admin/JC) │
                                                                        ▼
                                                                    APROBADO ──▶ PUBLICADO
      ▲                                                                 │
      └──────────── rechazar (vuelve a BORRADOR + razón) ───────────────┘
```

La UI del Jefe de Carrera usa un vocabulario **derivado** distinto:
`NO_INICIADO · BORRADOR · EN_REVISION · APROBADO · RECHAZADO`
(`jefe-carrera/Seguimiento.svelte:35`).

| Otra máquina | Estados |
|---|---|
| Actividad asignada (`EstadoActividadAsignada`) | `PLANIFICADA → ACTIVA → CERRADA` |
| Rúbrica (`EstadoRubrica`) | `POSTULADA → CERRADA` |
| Tipo de actividad (`TipoActividad`) | `SUMATIVA` (con nota) / `FORMATIVA` |
| Inscripción a curso | `INSCRITO · SUSPENDIDO · APROBADO · REPROBADO` |
| Curso | `estado_interno` (p. ej. `ABIERTO`), `estado_acta` (p. ej. `ENVIADO`) |

---

## Errores de login tipificados (para la pantalla de acceso)

`app/Enums/LoginErrorCode.php` — la UI debe distinguir estos 5 casos, no un
genérico "credenciales inválidas":

`RUT_NOT_FOUND` · `PASSWORD_INCORRECT` · `USER_INACTIVE` ·
`EMAIL_NOT_VERIFIED` · `RATE_LIMIT_EXCEEDED` (incluye `retry_after` en segundos).

---

## ⚠️ Huecos detectados (no inventar UI: marcarlos)

| Hueco | Evidencia |
|---|---|
| `docente/PermisosSyllabus` y `docente/PermisosComponente` se renderizan pero **el archivo Svelte no existe** | `CursoPermisosController.php:95,166` vs. `resources/js/pages/docente/` |
| `docente/Inscripciones` y `docente/CreateInscripcion` se renderizan y **no existen** | `Admin/InscripcionCursoController.php:83,129` |
| Dashboard del Ayudante reusa `Dashboard.svelte` de admin, con TODOs en el código | `Ayudante/DashboardController.php:46` y comentarios siguientes |
| 2FA retirado: rutas comentadas, pero `pages/settings/TwoFactor.svelte` sigue en el repo | `routes/settings.php:28` |
| Páginas huérfanas (sin ruta): `admin/ProgramaEdit`, `admin/Programas/ReviewPrograma`, `docente/ActividadesSeguimiento`, `docente/EstudiantesGestion`, `student/Courses/Bibs/Index` | comparación render ↔ archivos |
| `dashboards/DashboardAlumno|Docente|Ayudante` existen como componentes pero no como páginas ruteadas | idem |
