# Jefe de Carrera

> **Nombre en BD:** `Jefe de Carrera`
> **Puerta de entrada:** `UsuarioRolAsignacion` activa cuyo **contexto es de
> categoría `carrera`** (`JefaturaCarreraResolver`, `Carrera::jefesDeCarreraActivos`)
> **Middleware:** `is_docente` (vive bajo `/docente/jefe-carrera/*`) +
> trait `ResolvesJefaturaCarrera` en cada controlador
> **Layout:** `AdminLayout.svelte` (aunque la ruta cuelgue de `/docente`)

---

## Descripción y Objetivo del Rol

Es el **director académico de una carrera**. No administra el sistema ni imparte
desde este rol: **observa, controla y sella**. Su alcance está recortado por
código a *su* carrera, y ese recorte es el eje de todo su diseño.

Precedente que justifica la restricción (comentado en `ProgramaPolicy:282`):
antes bastaba tener el rol en cualquier contexto, así que *"un Jefe de Carrera
de Medicina aprobaba el syllabus de cualquier carrera y la firma `revisado_por`
quedaba a nombre de quien no tenía competencia sobre ella"*. Hoy
`puedeResolverPrograma()` verifica que el curso del programa cuelgue de un plan
de su carrera.

**Prerequisito de rol:** es un **docente** (el prefijo `/docente` exige perfil
`usuario.docente`). En la práctica siempre tiene además cursos propios, por lo
que el sidebar le muestra **dos pestañas: `Docente` y `Jefe de Carrera`**.

---

## Flujos de Usuario (User Flows)

### F1. Entrada y bifurcación de rol

```
Login → /dashboard → (es docente) → /docente/dashboard
  → El dashboard docente trae el bloque `jefatura`:
       { has_access: true, id_contexto, carrera: { id_carrera, nombre } }
  → Tarjeta "Jefatura de Carrera — {nombre}" → /docente/jefe-carrera/dashboard
  (o directamente por la pestaña "Jefe de Carrera" del sidebar)
```
`Docente/DashboardController.php:56` y `RoleSidebar.svelte:472`.

### F2. Ciclo de control del syllabus (flujo crítico del rol)

```
/docente/jefe-carrera/dashboard
  → alerta "Syllabus pendientes de revisión" (accion_url)
  → /docente/jefe-carrera/seguimiento?estado=EN_REVISION
  → fila del curso → "Previsualizar" (icono ojo)
      → GET /docente/jefe-carrera/programas/{programaId}/preview   [JSON]
      → slide-over con el documento del syllabus renderizado
      → sticky footer ejecutivo:
          ├─ "Aprobar"           → POST .../programas/{id}/aprobar
          └─ "Solicitar cambios" → POST .../programas/{id}/rechazar  (razón obligatoria)
                                    → estado vuelve a BORRADOR
                                    → log "Programa devuelto a revisión por Jefe de Carrera"
```
`jefe-carrera/Seguimiento.svelte` cabecera: *"Pantalla 3 (embed): Slide-over de
previsualización de syllabus con sticky footer ejecutivo (Aprobar / Solicitar
Cambios)"*. Un programa de otra carrera responde **403 "El programa no pertenece
a tu carrera"**.

### F3. Observabilidad de la carrera

```
Dashboard → tarjeta de métrica → /docente/jefe-carrera/metricas
  → 4 bloques: Asistencia · Avance de evaluación · Alumnos en riesgo · Carga docente
  → cada fila enlaza al curso concreto
```

### F4. Mantenimiento de la malla (acotado a su carrera)

```
/docente/jefe-carrera/carrera     (ficha + planes)
/docente/jefe-carrera/planes      (CRUD de planes)  → POST/PUT/DELETE
     → "Editar malla" → /docente/jefe-carrera/planes/{plan}/asignaturas
                        (mismo editor visual del admin)
/docente/jefe-carrera/asignaturas (CRUD de asignaturas)
```
Todo escrito fuerza la carrera del jefe: *"Forzar la carrera del jefe (se ignora
cualquier `id_carrera` entrante)"* (`JefeCarrera/PlanController::store`).

---

## Vistas Exclusivas del Rol

### V1. Dashboard Ejecutivo — `/docente/jefe-carrera/dashboard` → `jefe-carrera/Dashboard.svelte`
- **Objetivo:** *"Métricas KPI en Bento Box + Panel de Alertas"* (cabecera del
  archivo). Es la única vista del sistema con lenguaje de dirección.
- **Datos consumidos:**
  - `carrera`: `{ nombre, semestre: 'Primero'|'Segundo', ano }` (período vigente
    resuelto de los cursos, no del calendario).
  - `stats`: `syllabus_entregados`, `syllabus_total`, `cursos_activos`,
    `cursos_tendencia`, `estudiantes_matriculados` (distintos `id_estudiante`
    en `curso.inscripcion_curso` de los cursos del período).
  - `resumen_estados`: `{ no_iniciado, en_revision, aprobado }`.
  - `alertas[]`: `{ id, tipo: 'critica'|'advertencia'|'info', titulo, count,
    accion_label, accion_url }`.
  - `metricas_resumen`: `{ asistencia_promedio, avance_evaluacion,
    alumnos_en_riesgo, carga_docente }`.
- **Componentes UI clave inferidos:**
  - **Bento grid** de KPIs de tamaños desiguales (no una fila uniforme).
  - Progreso "syllabus entregados / total" como barra o donut.
  - **Panel de alertas** con tres severidades cromáticas y CTA por alerta
    (`accion_label` + `accion_url` vienen del backend: el botón es dato, no
    decisión de diseño).
  - Iconografía ya elegida en el código: `AlertTriangle`, `Bell`, `BookOpen`,
    `ClipboardList`, `CheckCircle2`, `BarChart2`, `GraduationCap`,
    `CalendarCheck`, `ListChecks`, `UserX`, `Users`, `Send`, `ArrowUpRight`.

### V2. Seguimiento Operativo — `/docente/jefe-carrera/seguimiento` → `jefe-carrera/Seguimiento.svelte`
- **Objetivo:** *"Data grid con filtros contextuales y badges de estado"*.
- **Datos consumidos:** `cursos` paginados (con `estado_syllabus` derivado),
  `semestres_disponibles` = `['Primero','Segundo']`, `agnos_disponibles`,
  `filters { q, semestre, agno, estado }`, `pagination { current_page,
  last_page, total }`, `carrera.nombre`.
- **Estados a pintar:** `NO_INICIADO · BORRADOR · EN_REVISION · APROBADO ·
  RECHAZADO` (vocabulario de UI, derivado del `estado` real del programa).
- **Componentes UI clave inferidos:** buscador con botón limpiar (`Search`/`X`),
  barra de filtros (`Filter`), tabla con badge de estado por fila, menú
  contextual por fila (`MoreVertical`), acciones `Eye` (previsualizar),
  `Check` (aprobar), `XCircle` (rechazar), `MessageSquare` (comentario),
  paginación. **Slide-over de previsualización** con `ArrowLeft` para volver.

### V3. Métricas de Rendimiento — `/docente/jefe-carrera/metricas` → `jefe-carrera/Metricas.svelte`
- **Objetivo:** *"observabilidad agregada de la carrera"*.
- **Datos consumidos (4 series, todas ya filtradas por carrera):**
  1. `asistencia_por_curso[]`: `{ id_curso, asignatura, cod, docente,
     porcentaje, sesiones }`.
  2. `avance_por_curso[]`: actividades calificadas / total.
  3. `alumnos_en_riesgo`: `{ total, por_curso[] { id_curso, asignatura, cod,
     en_riesgo, total } }` — **criterio explícito: promedio parcial < 4,0**.
  4. `carga_docente[]`: cursos y estudiantes por docente titular.
- **Componentes UI clave inferidos:** cuatro tarjetas-sección con tabla o barras
  horizontales ordenadas; en "alumnos en riesgo" sólo se listan cursos con
  `en_riesgo > 0` (el backend ya filtra) → **hace falta un estado vacío
  explícito** (`Inbox` está importado justamente para eso).

### V4. Mi Carrera — `/docente/jefe-carrera/carrera` → `jefe-carrera/Carrera.svelte`
- **Objetivo:** ficha de sólo lectura de la carrera + índice de sus planes.
- **Datos consumidos:** `carrera { id_carrera, nombre, jornada, sede, modalidad,
  departamento }`; `planes[]` con `asignaturas_count` y `creditos_sct_totales`;
  `stats { planes, asignaturas, cursos }`.
- **Componentes UI clave inferidos:** cabecera de ficha institucional
  (jornada · sede · modalidad · departamento como metadatos), tres contadores,
  y lista de planes ordenada por `agno_plan` y `version_plan` descendente con
  créditos totales por plan.

---

## Vistas Compartidas (Modificadas)

### VC1. Planes de Estudio — `admin/Planes.svelte` con `routePrefix='/docente/jefe-carrera'`
| Elemento | Administrador | Jefe de Carrera |
|---|---|---|
| Selector de carrera | Todas las carreras | **Bloqueado a su carrera** (`Carrera::where('id_carrera',$carreraId)` devuelve 1 elemento) |
| Campo `id_carrera` al guardar | Elegible | **Ignorado y sobrescrito** por el backend |
| Búsqueda | Por nombre de plan | Por **nombre o código de asignatura dentro del plan** |
| Datos extra | — | `creditos_sct_totales` (suma por plan) |
| Breadcrumb / enlaces | `/admin/...` | `/docente/jefe-carrera/...` |

### VC2. Detalle de Malla — `admin/DetalleMalla.svelte` con `routePrefix`
El componente calcula `isJefe = routePrefix !== '/admin'` y con eso reescribe el
breadcrumb (`Dashboard` → `/docente/jefe-carrera/dashboard`). Funcionalidad de
edición idéntica, alcance limitado por `assertPlanDeCarrera($plan)` en servidor.

### VC3. Asignaturas — `admin/Asignaturas.svelte`
Mismo CRUD; el Jefe de Carrera lo alcanza en `/docente/jefe-carrera/asignaturas`.

### VC4. Vista de programa / syllabus
Comparte el documento renderizado con Admin y Docente, pero **su footer de
acciones sólo aparece si el programa es de su carrera**. Ver `vistas_compartidas.md`.

### VC5. Todo el módulo Docente
Como también es docente, ve `docente/Dashboard`, `docente/Cursos`,
`docente/Calendario`, etc. con los permisos de sus propios cursos. Ver
`rol_docente_titular.md` / `rol_docente_componente.md`.

---

## Interacciones y Estados

| Interacción | Implementación |
|---|---|
| Sin jefatura activa | `jefaturaOrRedirect()` → redirect con `flash.error` *"No tienes rol de Jefe de Carrera activo"*; en endpoints JSON, `abort(403, 'No tienes rol de Jefe de Carrera activo.')` |
| Programa de otra carrera | `403 'El programa no pertenece a tu carrera'` |
| Rechazo de syllabus | Razón obligatoria; el programa vuelve a `BORRADOR` y el docente ve `razon_rechazo` + `fecha_rechazo` en su vista de programa |
| Aprobación | Sella `estado=APROBADO`, `fecha_aprobacion` y la firma `revisado_por` |
| Alertas del dashboard | Construidas en servidor (`construirAlertas`) a partir de `resumen_estados` y `alumnos_en_riesgo`: la UI **no** debe inventar reglas de severidad |
| Período vigente | `resolvePeriodoVigente($carreraId)` — el más reciente con cursos; si no hay, cae al semestre por mes (`now()->month <= 6`) |
| Filtro por estado | Se aplica **después** del mapeo (es un campo derivado), por eso la paginación se recalcula en memoria: el diseño debe asumir totales que cambian al filtrar |
| Éxito en CRUD | `flash.success` con texto específico, p. ej. *"Plan creado exitosamente para el año 2026 versión 1."* |
