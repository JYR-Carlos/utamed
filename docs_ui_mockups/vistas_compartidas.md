# Vistas Compartidas vs. Exclusivas

Análisis deducido de **rutas** (`routes/web.php`), **middlewares**, **policies**
y **props de Inertia**: qué pantallas son un único artefacto de UI que cambia
según quién mira, y cuáles pertenecen a un solo rol.

---

## 1. Los cinco mecanismos de compartición que usa el código

Antes de la matriz, conviene entender **cómo** comparte este sistema, porque
cada mecanismo exige una técnica de mockup distinta.

### M1 — Misma página Inertia, distinto controlador
Dos controladores bajo prefijos distintos hacen `Inertia::render` del **mismo
componente Svelte**.

| Componente | Servido desde | Y también desde |
|---|---|---|
| `admin/Planes` | `Admin\PlanController` (`/admin/planes`) | `Docente\JefeCarrera\PlanController` (`/docente/jefe-carrera/planes`) |
| `admin/DetalleMalla` | `Admin\AsignacionPlanController` | `Docente\JefeCarrera\AsignacionPlanController` |
| `admin/Asignaturas` | `Admin\AsignaturaController` | `Docente\JefeCarrera\AsignaturaController` |
| `docente/Programa` | `Administrativo\ProgramaController` (docente) | `Admin\ProgramaController` y `Ayudante\ProgramaController` |
| `Dashboard` | ruta `/dashboard` (admin) | `Ayudante\DashboardController` ⚠️ |

→ **Mockup:** un solo diseño, con variantes anotadas.

### M2 — Prop de variación explícita
El backend manda un dato cuyo único fin es reconfigurar la UI:

| Prop | Valores | Efecto |
|---|---|---|
| `routePrefix` | `/admin` \| `/docente/jefe-carrera` | Reescribe breadcrumbs y todos los enlaces (`isJefe = routePrefix !== '/admin'`) |
| `layoutType` | `'ayudante'` \| (docente por defecto) | Cambia el layout que envuelve `docente/Programa` |
| `backUrl` | `/ayudante/cursos/{id}` … | Destino del botón "volver" |
| `base_ruta` | prefijo de mensajería del rol | Los formularios de envío apuntan a la ruta del rol correcto |
| `userPermissions[]` | slugs con `esta_permitido` | Decide qué acciones se renderizan |
| `es_titular_curso` | bool | Bifurca la vista de curso completa |

→ **Mockup:** documentar el prop, no duplicar la pantalla.

### M3 — Trait de backend con contrato común
`Concerns\GestionaMensajeriaStaff` define un contrato (`curso`, `componentes`,
`componente_activo`, `base_ruta`, `panel`) y cada rol sólo implementa
`resolverComponentesVisibles()`, `vistaMensajeria()` y `baseRutaMensajeria()`.
Docente y Ayudante consumen literalmente los mismos datos.

→ **Mockup:** una bandeja, tres alcances de datos.

### M4 — Componente importado entre áreas
`docente/Activities/Index.svelte:37` hace
`import RubricaView from '../../student/Activities/Agenda/Rubrica.svelte'`:
la vista de rúbrica **del alumno** es la que ve el docente cuando no puede editar.

→ **Mockup:** diseñar una vez, reutilizar entre áreas.

### M5 — Condicional por permiso dentro de la misma página
Patrón dominante: `{#if can('actividades:crear')}`. **La acción no permitida no
se renderiza; no se deshabilita.**

→ **Mockup:** dibujar la *versión reducida* de la pantalla, no botones grises.

---

## 2. Matriz maestra — vista × rol

Leyenda: **●** acceso completo · **◐** acceso recortado (ver notas) ·
**○** sólo lectura · **—** sin acceso

| Vista / Módulo | SAdmin | Admin | Jefe Carrera | Doc. Titular | Doc. Componente | Ayudante | Estudiante |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Login / recuperación | ● | ● | ● | ● | ● | ● | ● |
| `/settings/*` (perfil, contraseña, apariencia) | ● | ● | ● | ● | ● | ● | ● |
| `SinRol` | — | — | — | — | — | — | — |
| Dashboard administrativo | ● | ● | — | — | — | ◐ ⚠️ | — |
| Facultades / Departamentos / Carreras | ● | ● | — | — | — | — | — |
| Planes de Estudio | ● | ● | ◐ | — | — | — | — |
| Detalle de Malla | ● | ● | ◐ | — | — | — | — |
| Asignaturas | ● | ● | ◐ | — | — | — | — |
| Cursos Ofertados (`/admin/cursos`) | ● | ● | — | — | — | — | — |
| Usuarios + wizard RBAC | ● | ◐ | — | — | — | — | — |
| Inscripciones / Roster | ● | ● | — | — | — | — | — |
| Syllabus (control) / Programas (cola) | ● | ● | ◐ | — | — | — | — |
| Documento de syllabus | ● | ● | ○+resolver | ● | ◐ por módulo | ◐ editar | ○ |
| Dashboard docente | — | — | ● | ● | ◐ ⚠️ | — | — |
| Jefe de Carrera (dashboard/seguimiento/métricas/carrera) | — | — | ● | — | — | — | — |
| Listado de cursos docente | — | — | ● | ● | ● | — | — |
| Detalle de curso (`CursoDetalle`) | — | — | ● | ● | ◐ | — | — |
| Equipo del curso (`team`) | ● | ● | — | ● | — | — | — |
| Delegación de permisos del curso | ● | ● | — | ● | — | — | — |
| Permisos de syllabus por módulo | ● | ● | — | ● | — | — | — |
| Permisos de componente colegiado | ● | ● | — | ● | ◐ titular comp. | — | — |
| Actividades del curso | — | — | ● | ● | ◐ | — | ○ |
| Evaluación de actividad | — | — | ● | ● | ◐ | — | — |
| Editor de rúbrica | — | — | ● | ● | — | — | — |
| Vista de rúbrica | — | — | ● | ● | ● | — | ● |
| Asistencia | — | — | ● | ● | ◐ | — | — |
| Calificaciones (centro) | — | — | ● | ● | ◐ | — | — |
| Calendario docente | — | — | ● | ● | ● | — | — |
| Mensajería de curso (staff) | — | — | ● | ● | ◐ | ◐ | — |
| Mensajería de curso (alumno) | — | — | — | — | — | — | ● |
| Mensajes de agenda (actividad) | — | — | ● | ● | ◐ | — | ● |
| Cursos del ayudante | — | — | — | — | — | ● | — |
| Dashboard / cursos / actividad del estudiante | — | — | — | — | — | — | ● |

> Las columnas SAdmin y Admin coinciden salvo en el wizard RBAC (el Admin no
> puede conceder roles no delegables) y en el alcance de las policies.

---

## 3. Vistas compartidas, una por una

### VC1 — Documento de Syllabus (`docente/Programa.svelte` + `SyllabusModal`)
**La vista más compartida del sistema: 6 roles la tocan.**

- **Objetivo:** redactar, revisar, resolver o leer el programa del curso.
- **Datos comunes:** `programa { id_programa, id_curso, version_programa, estado,
  secciones[], creado_por, fecha_creacion, razon_rechazo, fecha_rechazo }`,
  `curso` con asignatura/carrera/créditos/horas, `mode`, `userPermissions[]`,
  `layoutType`, `backUrl`.
- **Estructura de contenido (idéntica para todos):** secciones I a IX, o la
  variante BÁSICO (I, II, VI + Resumen). Etiquetas exactas en
  `SyllabusModal.svelte:185`.

| Rol | Qué añade / quita |
|---|---|
| **Super Admin / Admin** | Todas las secciones editables; **Aprobar / Rechazar**; **Instanciar**; edición de fechas límite; acceso a cualquier curso |
| **Jefe de Carrera** | Lectura + **Aprobar / Solicitar cambios**, sólo si el curso es de su carrera. Presentado como **slide-over con sticky footer ejecutivo**, no como página |
| **Docente Titular** | Todas las secciones editables + **Completar básico**, **Enviar a revisión**, **Eliminar**. Sin acciones de aprobación |
| **Docente Componente** | Edición **sección a sección** según `cursos/programas/modificar:modulo_1..9`; sin acciones de estado |
| **Ayudante** | Edición si está autorizado y el estado **no** es `APROBADO`; `layoutType='ayudante'`, `backUrl` a su curso; **no puede crear** |
| **Estudiante** | Sólo lectura de la versión vigente (`StudentSyllabusPresenter`); no ve estados internos ni razones de rechazo |

- **Componentes UI compartidos:** `ProgramaDocument`, `ProgramaDetailView`,
  `ProgramaStateBadges`, `CompletenessProgressBar`, `ProgramaWizardSteps`,
  `SyllabusTypeSelector`, `ProgramaActionButtons` (**el único que varía por rol**).
- **Regla de diseño:** un solo documento; la barra de acciones es el punto de
  variación. No rediseñar el cuerpo por rol.

---

### VC2 — Detalle del Curso (`docente/CursoDetalle.svelte`)
- **Objetivo:** centro de operaciones del curso para el equipo docente.
- **Estructura fija:** 3 pestañas `Mi Grupo | Actividades | Asistencia`, con
  contador numérico en cada una; deep-link vía `?tab=`.
- **Punto de bifurcación:** `es_titular_curso`.

| | Docente Titular | Docente Componente |
|---|---|---|
| KPIs de cabecera | `todos_componentes.length` · total docentes · `curso.total_estudiantes` | No se pintan (datos vacíos) |
| Bloque "Todos los componentes" | Presente, con docentes y matrículas | Ausente |
| `mis_componentes` / `mis_estudiantes` | Presentes | Presentes (es su único contenido) |
| `userPermissions` | **vacío** (acceso total implícito) | Poblado; gobierna cada acción |
| Sub-pestañas colegiado | Si es titular del componente | Si es titular del componente |
| Accesos a equipo / delegación / permisos | Sí | No |

---

### VC3 — Bandeja de Mensajería de curso (`BandejaStaff.svelte`)
Tres páginas, un mismo contrato (M3):
`docente/Mensajeria` · `ayudante/Mensajeria` · `student/Mensajeria`.

- **Contrato de datos:** `curso`, `componentes[] {id_componente, tipo, no_leidos}`,
  `componente_activo`, `panel`, y para staff `base_ruta`.
- **Estructura fija:** pestañas por componente con badge → panel con difusiones
  y canales de alumno → compositor.

| Rol | Componentes visibles | Puede difundir | Escribe a |
|---|---|---|---|
| Docente Titular | Todos los que ve en el curso | Sí | Cualquier alumno del componente |
| Docente Componente | Los que imparte | Sí | Alumnos de sus componentes |
| Ayudante | Aquellos donde tiene rol Ayudante en ese curso | Sí | Alumnos de esos componentes |
| Estudiante | Aquellos donde está inscrito | **No** | Al equipo docente (con destinatario preferido) |

- **Detalle de comportamiento común:** abrir la pestaña **marca como leído**; por
  eso el backend resuelve el panel *antes* de contar los badges, *"así la pestaña
  recién abierta aparece ya sin badge en vez de quedarse con el número de la
  visita anterior"*.

---

### VC4 — Planes de Estudio y Detalle de Malla (Admin ↔ Jefe de Carrera)
Mismo componente, variación por `routePrefix` (M1 + M2).

| | Administrador | Jefe de Carrera |
|---|---|---|
| Selector de carrera | Todas | Bloqueado a la suya (colección de 1) |
| `id_carrera` al guardar | Elegible | Forzado en servidor |
| Búsqueda en Planes | Por nombre de plan | Por asignatura (nombre o código) dentro del plan |
| Dato extra | — | `creditos_sct_totales` por plan |
| Breadcrumb | `Dashboard → /dashboard` | `Dashboard → /docente/jefe-carrera/dashboard` |
| Guarda de servidor | `authorize()` | `assertPlanDeCarrera($plan)` |

---

### VC5 — Vista de Rúbrica (`student/Activities/Agenda/Rubrica.svelte`)
Componente único (M4) consumido por estudiante y docente.
- **Estudiante:** siempre en lectura, desde `ActivityRubricaCard`.
- **Docente no titular:** lectura, vía `toggleRubricaModal()`.
- **Docente titular:** el botón abre `RubricaEditor` en su lugar
  (`actividad.es_titular ? showRubricaEditor = true : toggleRubricaModal()`).
- **Regla de diseño:** el editor debe ser una **capa sobre** la misma retícula
  criterios × niveles, no un formulario distinto.

---

### VC6 — Actividad: dos caras del mismo objeto
No es el mismo componente, pero **debe leerse como la misma pantalla** desde
lados opuestos.

| | `docente/Activities/Index` | `student/Activities/Index` |
|---|---|---|
| Unidad de trabajo | **Todos** los grupos (`GrupoCard`) | **Su** grupo + `resto_integrantes` |
| Entregas | `EntregasModal` (lazy) + descarga | Su propia entrega |
| Rúbrica | Editor o lectura | Lectura |
| Evaluación | `MatrizEvaluacion` (escribe nota) | `ActivityGradeCard` (lee nota) |
| Hilo | `AgendaDocente` → Feedback | `Agenda` → Consulta / Entrega de avance |
| Estado | Estado del grupo, editable | Estado personal, calculado con su holgura |

---

### VC7 — Ajustes de cuenta (`/settings/*`)
Idéntico para **todos** los roles autenticados: `Profile`, `Password`,
`Appearance`. Layout propio `settings/Layout.svelte` con navegación lateral.
⚠️ `TwoFactor.svelte` sigue en el repo pero su ruta está comentada (2FA retirado).

---

### VC8 — Autenticación
`Login`, `ForgotPassword`, `ResetPassword`, `ConfirmPassword`, `VerifyEmail`,
`Register` (condicionado a `Features::registration()`), `Welcome`.
Sin variación por rol —el rol aún no existe—, pero **sí por código de error**:
los 5 valores de `LoginErrorCode` requieren mensajes distintos, incluido el
contador de `retry_after` en `RATE_LIMIT_EXCEEDED`.

---

### VC9 — Sidebar de rol (`RoleSidebar.svelte`)
**Un único componente para los cinco layouts.** Es el elemento más transversal
del sistema y merece diseño propio.

- **Cabecera:** logo + `UTAMED` + `SISTEMA DE GESTIÓN`.
- **Conmutador de rol:** aparece **sólo si `availableSections.length > 1`**;
  pastillas en fondo `slate-100`, activa en blanco con texto `indigo-600`.
  Secciones y sus iconos ya elegidos: `Docente` (GraduationCap),
  `Jefe de Carrera` (ClipboardList), `Estudiante` (GraduationCap),
  `Ayudantía` (BookOpen), `Administración` (Building2).
- **Sección Docente:** buscador de cursos + período vigente desplegado +
  bloque "Histórico" colapsado agrupado como `Año X · Semestre Y`; cada curso es
  una tarjeta con accesos a *Programa* y *Actividades*.
- **Sección Administración:** 9 destinos, **un icono distinto cada uno** (nota
  del código: tres pares compartían icono y *"la barra sólo se podía leer palabra
  por palabra"*) — Usuarios `Users`, Facultades `Landmark`, Departamentos
  `Building2`, Carreras `GraduationCap`, Asignaturas `BookOpen`, Planes
  `ClipboardList`, Cursos Ofertados `CalendarRange`, Inscripciones `UserCheck`,
  Syllabus `ScrollText`.
- **Sección Jefe de Carrera:** Dashboard · Seguimiento · Métricas · Planes ·
  Asignaturas · Carrera.
- **Sección Estudiante:** Dashboard · Cursos (lista expandible).
- **Sección Ayudantía:** Dashboard · cursos con *Programa* / *Crear programa*.
- **Pie:** enlace a `/settings` + `NavUser`.
- **Detección de sección activa:** por prefijo de URL; si la ruta no mapea, abre
  la primera disponible.

---

### VC10 — Elementos transversales de interacción

| Elemento | Componente | Uso |
|---|---|---|
| Banner de éxito/error | `flash.success` / `flash.error` de Inertia | Todas las escrituras |
| Confirmación destructiva | `ConfirmDialog`, `ConfirmationModal` | Borrados, desactivar/reactivar usuario, quitar miembro |
| Error de formulario | `InputError`, `ErrorAlert`, `AlertError` | Validación server-side |
| Cabecera de página | `PageHeader` | Todo `/admin` |
| Tabla y paginación | `DataTable`, `PaginationControls` | Listados administrativos |
| Modal de formulario | `FormModal` | CRUD administrativo |
| Migas | `Breadcrumbs` (`BreadcrumbItem[]`) | Todas las páginas internas |
| Fechas | `DatePickerCL` (formato chileno) | Vigencias, fechas límite |
| Vacío | `PlaceholderPattern` | Listados sin datos |

---

## 4. Vistas exclusivas (sin equivalente en otro rol)

| Rol | Vistas propias |
|---|---|
| **Super Admin** | Ninguna pantalla; sólo **estados sin restricción** dentro de las del Admin (wizard RBAC con catálogo completo, resolver syllabus de cualquier carrera) |
| **Administrador** | Dashboard administrativo · Facultades · Departamentos · Carreras · Cursos Ofertados (wizard, copia, sincronización Intranet) · Usuarios (+ importación masiva + wizard RBAC) · Inscripciones/Roster · Syllabus (control) · Programas (cola) |
| **Jefe de Carrera** | Dashboard ejecutivo (bento + alertas) · Seguimiento operativo · Métricas de rendimiento · Mi Carrera |
| **Docente Titular** | Delegación de permisos del curso · Permisos de syllabus por módulo · Permisos de componente colegiado · Editor de rúbrica · Equipo docente del curso |
| **Docente Componente** | Ninguna ruta propia; bloques exclusivos: panel "Mi Grupo", sub-pestañas de colegiado, `AsistenciaPanel`, `ActividadesPorEstado` |
| **Ayudante** | Cursos de ayudantía (Index/Show) · su bandeja de mensajería · (dashboard propio ⚠️ pendiente) |
| **Estudiante** | Dashboard del estudiante · Mis cursos · Detalle de curso con syllabus embebido · Pantalla de actividad (8 cards) · Agenda de actividad (entrega, enunciado, informaciones) · Mensajería de alumno |

---

## 5. Reglas de diseño derivadas del análisis

1. **Una pantalla, N barras de acción.** El cuerpo de las vistas compartidas es
   invariante; lo que cambia es el conjunto de acciones. Diseñar el cuerpo una
   vez y anexar las variantes de la barra.
2. **Ausencia, no deshabilitado.** El patrón `{#if can(...)}` implica que las
   acciones no permitidas **desaparecen**. Los mockups deben mostrar layouts que
   no se rompan al perder botones (evitar rejillas rígidas).
3. **El rol no es una app.** Un usuario puede tener 3 roles y conmutar sin
   recargar: la identidad visual debe ser **una sola**, y la sección activa se
   señala en el sidebar, no con cromática distinta por rol.
4. **Contexto antes que rol.** "Ser docente" no habilita nada: habilita ser
   docente **de ese curso**. Toda pantalla de curso debe mostrar de forma
   inequívoca *en qué curso y con qué papel* está actuando el usuario.
5. **Dos mensajerías, dos lugares.** La de agenda vive **dentro** de la
   actividad; la de curso vive en el sidebar y el dashboard. No unificarlas en la
   UI: el código las separa deliberadamente y los contadores son independientes.
6. **Los estados de plazo son personales.** Fecha límite + holgura base + holgura
   personal. Mostrar siempre la fecha efectiva del usuario, y contemplar el
   estado intermedio "fuera de plazo, aún apelable".
7. **Sin tema oscuro.** Decisión vigente del equipo; no producir variantes dark.
