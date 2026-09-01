# Administrador

> **Nombres en BD:** `Administrador`, `Admin`
> **Puerta de entrada:** el rol debe estar asignado **en el contexto Global**
> (`IsAdmin::isAdmin()` → `hasAnyRoleGlobally(Usuario::ROLES_ADMINISTRATIVOS)`)
> **Middleware:** `is_admin` sobre todo el prefijo `/admin`
> **Layout:** `AdminLayout.svelte` + `RoleSidebar` (sección `Administración`)

---

## Descripción y Objetivo del Rol

Es el **gobierno de la estructura académica**: define la jerarquía institucional
(Facultad → Departamento → Carrera → Plan → Asignatura), abre la oferta de
cursos de cada período, crea y activa usuarios de los tres tipos, matricula
alumnos y sella el syllabus.

Restricción no negociable: un "Administrador" cuyo rol esté acotado a una
facultad o a un departamento **no entra a `/admin`**. El middleware lo redirige
a `/dashboard` con el mensaje *"No tienes permisos para acceder a esta sección.
Acceso restringido a administradores."* (`IsAdmin.php:41`). El comentario del
código explica el porqué: sin esa restricción, cualquier administrador local
obtenía todo el módulo.

---

## Flujos de Usuario (User Flows)

### F1. Apertura de un período académico (flujo raíz, de arriba abajo)

```
Login → /dashboard (Dashboard.svelte)
  → Facultades   → crear Facultad
  → Departamentos → crear Departamento (cascada: /admin/facultades/{f}/departamentos)
  → Carreras      → crear Carrera      (cascada: /admin/departamentos/{d}/carreras)
  → Planes        → crear Plan (año + carrera)
  → Planes ▸ "Editar malla" → DetalleMalla: arrastrar Asignaturas a semestres
  → Cursos Ofertados → wizard de creación de curso
  → Inscripciones → roster del curso
```

### F2. Alta de curso (wizard, `cursoWizardModal.svelte`)

```
/admin/cursos → "Nuevo curso"
  Paso 1  elegir Plan            → GET /admin/planes/{plan}/asignaturas-disponibles
  Paso 2  elegir Asignatura      → GET /admin/asignaturas/{a}/docentes-sugeridos
                                 → GET /admin/asignaturas/{a}/cursos-anteriores
  Paso 3  letra de grupo         → GET /admin/cursos/proxima-letra   (autocalculada)
  Paso 4  componentes            → GET /admin/cursos/preview-componentes
                                   (Cátedra / Laboratorio / Taller, con principal)
  Paso 5  docente titular + fechas + checkbox "inscribir automáticamente"
  → POST /admin/cursos
     ├─ éxito                         → flash.success "Curso creado exitosamente."
     └─ con inscripción automática    → IntranetService::inscribirAutomaticamente()
        ├─ ok    → "…Se inscribieron N alumnos automáticamente desde la Intranet."
        └─ error → flash.success (curso creado) + flash.error (falló la Intranet)
```
`Admin/CursoController::store` — los **dos flashes simultáneos** son un estado
real que el diseño debe soportar (banner de éxito + banner de advertencia).

### F3. Copiar un curso del período anterior

```
/admin/cursos → fila → "Copiar"
  → GET /admin/cursos/{curso}/preview-copia   → cursoCopyPreviewModal
     (muestra qué se copiará: componentes, programa, unidades…)
  → POST /admin/cursos/{curso}/copiar
```

### F4. Sincronización con la Intranet UTA

```
Individual:  fila de curso → GET  /admin/cursos/{curso}/sincronizar-intranet/preview
                           → POST /admin/cursos/{curso}/sincronizar-intranet
Masiva:      cabecera      → GET  /admin/cursos/sincronizar-intranet-masivo/preview
                           → POST /admin/cursos/sincronizar-intranet-masivo
```
Componentes: `cursoSincronizarIntranetModal.svelte`, `cursoSincronizarMasivoModal.svelte`.
La previsualización es obligatoria antes del POST: el diseño debe mostrar
**diff** (qué se creará / actualizará) antes de confirmar.

### F5. Alta masiva de usuarios

```
/admin/usuarios → "Importar"
  → GET  /admin/usuarios/plantilla-importacion       (descarga CSV modelo)
  → POST /admin/usuarios/importar/previsualizar      (tabla de validación fila a fila)
  → POST /admin/usuarios/importar                    (procesa por bloques, tope MAX_FILAS_IMPORTACION)
```
Las columnas esperadas las declara el servidor (`columnasImportacion($tipo)`) y
se muestran en el modal, "de modo que la pantalla y la plantilla descargable no
puedan describir columnas distintas" (comentario en `UsuarioController:239`).

### F6. Matrícula (paradigma *roster management*)

```
/admin/inscripciones_cursos
  Modo A (sin id_curso): grid de tarjetas de curso  → CursoSelector
  Modo B (con id_curso): roster completo            → RosterTable
     ├─ "+ Agregar Estudiantes" → AddEstudiantesModal
     │     → GET  /admin/inscripciones_cursos/ajax/disponibles
     │     → POST /admin/inscripciones_cursos/bulk
     ├─ cambio de estado inline → PATCH /admin/inscripciones_cursos/{i}/estado
     ├─ "Inscripción automática" → POST /admin/cursos/{curso}/inscripcion-automatica
     └─ "Exportar" → GET /admin/inscripciones_cursos/export/csv
```

### F7. Ciclo del syllabus (cierre del circuito)

```
/admin/syllabus  → tabla de cursos con estado y fechas límite
  → "Fechas"  → PUT /admin/cursos/{curso}/programa/fechas
                (fecha_limite_entrega_basico / _syllabus)
  → "Instanciar" → POST /admin/cursos/{curso}/programa/instanciar
                   (crea el contenedor en BORRADOR para que el docente trabaje)
  → "Revisar"  → GET /admin/cursos/{curso}/programa/revisar
      ├─ PUT .../aprobar   → estado APROBADO + firma revisado_por
      └─ PUT .../rechazar  → vuelve a BORRADOR + razón obligatoria
                             flash.warning "Programa devuelto a estado BORRADOR.
                             El docente puede editarlo nuevamente. Razón: …"
```

---

## Vistas Exclusivas del Rol

### V1. Dashboard Administrativo — `/dashboard` → `Dashboard.svelte`
- **Objetivo:** responder *"¿qué requiere acción hoy?"*, no lucir totales. El
  código lo dice explícitamente: *"Antes el dashboard sólo mostraba totales
  acompañados de líneas de tendencia decorativas."*
- **Datos consumidos:**
  - `stats`: `usuarios` (count Usuario), `cursos_total`, `cursos_pendientes`
    (cursos ABIERTO con `estado_acta != ENVIADO` o nulo), `facultades`, `carreras`.
  - `pendientes`: `cursos_sin_syllabus` (ABIERTO sin `programas`),
    `cursos_sin_componentes` (ABIERTO sin `componentes`),
    `carreras_sin_director` (Carrera vigente sin `jefesDeCarreraActivos`).
- **Componentes UI clave inferidos:** fila de 5 KPI cards; **panel "Requiere
  acción"** con 3 alertas accionables (cada una enlaza a su listado filtrado);
  `SoftCard` / `IllustrationWidget` como envoltorio visual.
- **No diseñar** gráficas de tendencia: no hay serie temporal en los props.

### V2. Usuarios — `/admin/usuarios` → `admin/Usuarios.svelte`
- **Objetivo:** CRUD de las tres identidades del sistema en una sola tabla.
- **Datos consumidos:** paginación de `UsuarioResource` normalizada al contrato
  `{ usuario, estudiante, docente }`; `carreras` (para el alta de estudiante);
  `tipo` (filtro activo); `columnasImportacion`.
  Campos visibles: `rut`, `username`, `nombre1`, `apellido1`, `email`,
  `esta_activo`.
- **Componentes UI clave inferidos:**
  - Segmented control `Estudiante | Docente | Administrador` (`UserType`).
  - `DataTable` con búsqueda, orden por columna (whitelist: id, rut, username,
    nombre1, apellido1, email) y paginación; **los activos siempre primero**
    (`orderByDesc('esta_activo')`).
  - `UserForm` en modal, con **campos distintos por tipo**.
  - **Autocompletado por RUT**: al teclear ≥8 caracteres en alta,
    `GET /admin/usuarios/buscar-por-rut` rellena el formulario si el RUT ya existe.
  - `UserImport` (drag & drop + tabla de previsualización con errores por fila).
  - `PasswordChangeModal` → `POST /admin/usuarios/{u}/change-password`.
  - Toggle activo/inactivo con confirmación → `POST .../toggle-active`.
  - `PermissionsModal` (wizard RBAC, ver `rol_super_admin.md` V1).
  - `AttriBadges` para marcar atributos del usuario.

### V3. Facultades — `/admin/facultades`
- **Objetivo:** raíz de la jerarquía; permite crear departamentos sin salir.
- **Datos consumidos:** `administrativo.facultad` paginada + departamentos anidados.
- **Componentes UI:** `FacultadList` = **tabla expandible con departamentos
  anidados**; `FacultadForm` (modal); `DepartamentoModal` (modal contextual
  lanzado desde la fila padre); `FacultadDeleteConfirm`.

### V4. Departamentos — `/admin/departamentos` · V5. Carreras — `/admin/carreras`
- **Datos consumidos:** `administrativo.departamento` / `administrativo.carrera`
  con `jornada`, `sede` y el derivado **`has_director`** (existe usuario con rol
  `Jefe de Carrera` activo, `CarreraController.php:62`).
- **Componentes UI:** tabla + modal CRUD + confirmación de borrado; en Carreras,
  **badge de alerta cuando `has_director` es falso** (alimenta la alerta
  `carreras_sin_director` del dashboard).

### V6. Planes de Estudio — `/admin/planes`
- **Objetivo:** versiones/años de malla por carrera.
- **Datos consumidos:** `administrativo.plan` paginado + `carrera` padre.
- **Componentes UI:** filtro por carrera, búsqueda por nombre, `PlanForm`
  (requiere año + carrera), `MallaSlideOver` (vista rápida) y acceso al editor
  completo (`visitEditarMalla`).

### V7. Detalle de Malla — `/admin/planes/{plan}/asignaturas` → `admin/DetalleMalla.svelte`
- **Objetivo:** editor visual de la malla curricular.
- **Datos consumidos:** `plan`, `malla` (`MallaData`), catálogo `asignaturas`,
  `AsignacionPlan` (año/semestre, créditos).
- **Componentes UI clave inferidos:** `MallaGrid` (rejilla año × semestre),
  `AsignaturasCatalogo` (panel lateral de asignaturas no asignadas, con
  contador de asignadas), `EditAsignacionModal`, `AsignacionDeleteConfirm`.
- **Compartida:** esta misma página la reusa el Jefe de Carrera cambiando el
  prop `routePrefix` a `/docente/jefe-carrera` (ver `vistas_compartidas.md`).

### V8. Asignaturas — `/admin/asignaturas`
- **Datos consumidos:** `asignatura` (`cod_asignatura`, `nombre`, `descripcion`,
  `creditos_sct`, `horas_catedra`, `horas_taller`, `horas_laboratorio`).
- **Interacción crítica:** `update()` **exige el campo `tipo_edicion`**
  (edición correctiva vs. versionada). El formulario debe forzar esa elección
  antes de guardar; no es un campo opcional.

### V9. Cursos Ofertados — `/admin/cursos` → `admin/Cursos.svelte`
- **Objetivo:** oferta del período y punto de entrada a todo lo del curso.
- **Datos consumidos:** `CursoResource` paginado con `asignacionPlan.asignatura`,
  `plan.carrera`, `componentes.tipoComponente`, `docenteTitular.usuario`,
  `componentes.docenteComponentes.docente.usuario`, `programas` (sólo
  `es_actual = true`); más `planes`, `carreras` (`id, nombre, jornada, sede`),
  `tipos_componente`, `filters.search`.
- **Componentes UI clave inferidos:** `cursoListAdmin` (tabla) o `cursoCard`;
  buscador por nombre/código/asignatura; `cursoSlideOver` (detalle lateral);
  `cursoWizardModal` (alta, F2); `componenteForm` / `seccionForm`;
  `EquipoDocenteSlideOver`; `ComponenteTitularModal`; `ComponentePermisosModal`;
  `SyllabusPermisosModal`; `courseTeamModal`; `cursoCopyPreviewModal`;
  `cursoSincronizarIntranetModal`; `cursoDeleteConfirm`.

### V10. Gestión de componentes de un curso (dentro de V9)
- **Endpoints:** `POST/PUT/DELETE /admin/cursos/{c}/componentes[/{comp}]`,
  `POST .../docentes`, `DELETE .../docentes/{dc}`, `PUT .../titular`,
  `PUT .../genera-acta`.
- **Componentes UI:** lista de componentes con chips de docentes; switch
  **"genera acta"**; radio/estrella para marcar **titular del componente**;
  buscador de docentes para añadir al componente.

### V11. Inscripciones — `/admin/inscripciones_cursos` (+ Create/Edit)
Ver F6. `RosterTable` con **máquina de estados inline**
(`INSCRITO · SUSPENDIDO · APROBADO · REPROBADO`), `CursoSelector` en modo A,
`AddEstudiantesModal`, `InscripcionDeleteConfirm`.
Páginas dedicadas: `admin/CreateInscripcionCurso.svelte`, `admin/EditInscripcionCurso.svelte`.

### V12. Syllabus — `/admin/syllabus` → `admin/Syllabus.svelte`
- **Objetivo:** control de cumplimiento del syllabus por curso.
- **Datos consumidos por fila:** `cod_curso`, `nombre`, `agno_real`,
  `semestre_real`, `asignatura`, `carrera`, `fecha_limite_entrega_basico`,
  `fecha_limite_entrega_syllabus`, y `programa` = `{ id_programa, estado,
  tipo_syllabus, version_programa, completud }`. Filtros `q`, `semestre`, `agno`
  con sus catálogos (`semestres`, `agnos`).
- **Componentes UI clave:** tabla con `ProgramaStateBadges`,
  `CompletenessProgressBar` (porcentaje de completitud), selector de fechas
  límite en bloque, acciones "Instanciar" / "Revisar".

### V13. Programas — `/admin/programas` → `admin/Programas.svelte`
- **Objetivo:** cola de trabajo por estado, no por curso.
- **Datos consumidos:** filas `{id_programa, id_curso, version, estado,
  tipo_syllabus, asignatura, carrera, creado_por, fecha_creacion,
  fecha_aprobacion, fecha_publicacion, completud}`; `stats` por estado
  (`basico_completo`, `completo`, `aprobados`, `publicados`, `borradores`,
  `pendientes`, `total`; `rechazados` está fijado a 0 — estado eliminado del
  flujo); filtros `estado_filtro`, `tipo_filtro`; paginación propia.
- **Componentes UI:** `ProgramasListView`, `ProgramaStateBadges`,
  fila de contadores clicables como filtros, `ProgramaActionButtons`.

---

## Vistas Compartidas (Modificadas)

| Vista | También la usan | Extras del Administrador |
|---|---|---|
| **Editor de syllabus** (`SyllabusModal`, 9 pasos) | Docente Titular, Docente Componente autorizado, Ayudante | Puede abrirlo en **cualquier** curso; puede editar aun en estados que bloquean al docente; único junto al JC que ve Aprobar/Rechazar |
| **Vista de programa** (`docente/Programa.svelte`) | Docente, Ayudante | Se le sirve por `Admin/ProgramaController::show`; botón "Instanciar" y edición de fechas límite |
| **Detalle de Malla** | Jefe de Carrera | `routePrefix = '/admin'` → breadcrumb hacia `/dashboard` y CRUD completo de asignaciones |
| **Equipo de curso** (`/admin/cursos/{c}/team`) | Docente Titular (bajo `/docente`) | El admin entra a cualquier curso; el DT sólo al suyo (`CursoPolicy::manageTeam`) |
| **Ajustes de cuenta** (`/settings/*`) | Todos | Sin diferencias |

---

## Interacciones y Estados

| Interacción | Implementación |
|---|---|
| Confirmación destructiva | `ConfirmationModal.svelte` / `ConfirmDialog.svelte` en borrados y en **desactivar usuario** (reactivar también confirma: *"Reactivar no destruye nada y se…"*, `Usuarios.svelte:387`) |
| Validación de formularios | FormRequests server-side (`StoreCursoRequest`, `UpdateCursoRequest`); errores vuelven por `errors` de Inertia y se pintan con `InputError` |
| Manejo de error genérico | ⚠️ `Usuarios.svelte:143` usa `alert(...)` nativo con JSON crudo — el mockup debe reemplazarlo por `ErrorAlert`/`AlertError` |
| Mensajes de éxito | `flash.success` como banner; el redirect siempre vuelve al índice del recurso |
| Rechazo de syllabus | Requiere **razón textual obligatoria**; se guarda en `ProgramaHistorial` con `accion='RECHAZO'` y se muestra después al docente |
| Paginación | Componente `PaginationControls`; `per_page` configurable por request |
| Cabecera de página | `PageHeader.svelte` unificado (título + acción primaria) |
| Estado vacío | Los listados usan `PlaceholderPattern`; el roster tiene modo A (selector) como "vacío útil" |
