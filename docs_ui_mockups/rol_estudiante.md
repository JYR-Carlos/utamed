# Estudiante

> **Nombre en BD:** `Estudiante`
> **Puerta de entrada:** el rol se asigna **automáticamente al inscribirlo en un
> curso** (`InscripcionCursoService::asignarRol`, `EstudianteService`), en el
> contexto de ese curso; se revoca al eliminar la inscripción
> **Middleware:** `is_estudiante` (`hasRole('Estudiante')`) + comprobación de
> perfil `usuario.estudiante` en los controladores
> **Layout:** `StudentLayout.svelte` + `RoleSidebar` (sección `Estudiante`)

---

## Descripción y Objetivo del Rol

Es el **destinatario final** del sistema y el rol con más volumen de usuarios.
Su experiencia es de **consumo y entrega**: consulta el syllabus, sigue las
actividades de sus cursos, entrega archivos, recibe notas y retroalimentación, y
conversa con el equipo docente por dos canales distintos.

No tiene ninguna capacidad de gestión: no crea, no evalúa, no ve a otros grupos.
Todo lo que ve está filtrado por su `id_estudiante` y por sus
`InscripcionCurso` con `estado_inscripcion = 'INSCRITO'`.

Dos particularidades que condicionan el diseño:

1. **Casi todo es grupal aunque sea individual.** El contenedor de trabajo es
   siempre `ActividadAsignadaGrupo`; para actividades individuales el sistema
   crea un "grupo de uno" automáticamente al inscribir al estudiante. La UI debe
   ocultar el vocabulario de grupo cuando `es_grupal = false`.
2. **El estado de una actividad es personal.** `calcularEstadoGrupo($actividad)`
   aplica la holgura base **más** la holgura personal del grupo
   (`nro_dias_adicionales_para_bloqueo_personal`): dos alumnos pueden ver la
   misma actividad como `ACTIVA` y `CERRADA` respectivamente.

---

## Flujos de Usuario (User Flows)

### F1. Entrada

```
Login → /dashboard → hasRole('Estudiante') → redirect /estudiante/dashboard
  → student/Dashboard.svelte
```
⚠️ Si el usuario es además docente, el dashboard prioriza **Docente**; si es
además ayudante, prioriza **Estudiante**. El conmutador de pestañas del sidebar
resuelve el resto.

### F2. Del curso a la entrega (flujo crítico, extremo a extremo)

```
/estudiante/dashboard  (tarjetas de curso con profesor y período)
  → /estudiante/cursos                      (listado completo)
  → /estudiante/cursos/{curso}              (Show: syllabus embebido + actividades)
  → /estudiante/cursos/{curso}/actividad/{actividad}
       → student/Activities/Index.svelte
       ├─ Enunciado   → GET .../actividades/{a}/enunciado/descargar
       ├─ Rúbrica     → modal de sólo lectura
       └─ Agenda      → hilo de la actividad
             ├─ mensaje  → POST /estudiante/grupos-asignados/{aag}/agenda
             ├─ adjunto  → POST /estudiante/agendas/{registroAgenda}/archivos
             └─ ENTREGA  → POST /estudiante/grupos-asignados/{aag}/entregas
  → estado pasa a "entregado" → aparece ActivitySubmittedCard
  → tras evaluación → ActivityGradeCard con ultima_nota
```

### F3. Conversación con el equipo docente (canal de curso, distinto de la agenda)

```
/estudiante/cursos/{curso}/mensajeria → student/Mensajeria.svelte
  → pestañas = SUS componentes en ese curso (Cátedra / Laboratorio…)
  → leer avisos de difusión del equipo docente
  → escribir: POST .../componentes/{componente}/mensaje
              { mensaje (≤2000), tema (≤150), id_usuario_receptor? }
```
Regla de negocio explícita: *"Elige a qué docente dirige el mensaje, pero lo ven
todos los docentes del componente, así que puede responderle cualquiera de ellos
sin abrir un hilo aparte"*. El selector de destinatario es **una preferencia, no
una restricción de visibilidad** — el mockup no debe sugerir privacidad.

### F4. Consulta del syllabus

```
/estudiante/cursos/{curso}/programa → student/Courses/Syllabus.svelte
  (armado por StudentSyllabusPresenter: sólo la versión vigente y aprobada)
```

---

## Vistas Exclusivas del Rol

### V1. Dashboard del Estudiante — `/estudiante/dashboard` → `student/Dashboard.svelte`
- **Objetivo:** panorama del semestre y avisos sin leer.
- **Datos consumidos:**
  - `estudiante`: `{ id_estudiante, rut, id_usuario, nombre_carrera }`.
  - `cursos[]`: `{ id_curso, nombre, cod_curso, asignatura_nombre,
    carrera_nombre, fecha_inicio, fecha_fin, profesor }`.
    **`profesor`** = titular de *su* sección; si no hay, `'(sin docente asignado)'`.
  - `stats`: `{ total_cursos, nombre_completo }`.
  - `mensajeria`: `{ no_leidos, cursos[] { id_curso, nombre, no_leidos } }`
    — **agrupado por curso, no por componente**, porque la bandeja se entra desde
    el curso.
  - `isAyudante` (bool) · `semestreActual` (1 si `month <= 6`, si no 2).
- **Componentes UI clave inferidos:** `ProfileCard` (nombre + carrera + RUT),
  `CourseCard` en grid (código, asignatura, docente, período),
  `MensajesSinLeerCard` (lista de cursos con contador y enlace directo a su
  bandeja), y **acceso condicional al área de Ayudantía** si `isAyudante`.

### V2. Mis Cursos — `/estudiante/cursos` → `student/Courses/Index.svelte`
- **Datos consumidos:** cursos inscritos formateados por `formatCurso($curso,'Estudiante')`.
- **Componentes UI:** `cursoListAlumno` / `CourseCard`; agrupación por período.

### V3. Detalle del Curso — `/estudiante/cursos/{curso}` → `student/Courses/Show.svelte`
- **Objetivo:** una sola pantalla con **syllabus + actividades**.
- **Datos consumidos:**
  - `curso`: `{ id_curso, nombre, cod_curso, cod_asignatura, asignatura_nombre,
    carrera_nombre, letra_grupo, semestre_real, agno_real, unidades, asignatura }`
    — `asignatura` completa se envía para créditos SCT y horas
    (cátedra/taller/laboratorio), *"la ficha del encabezado los muestra junto al
    período"*.
  - `actividades[]`: `{ id_actividad, nombre, es_sumativa, con_entrega,
    es_grupal, max_integrantes, fecha_limite, visible, estado }`.
  - Todo lo que devuelve `StudentSyllabusPresenter::build($curso, $estudiante)`.
- **Componentes UI clave inferidos:** `CursoEncabezado` (ficha con créditos,
  horas, período, letra de grupo), `CursoInformacion`, lista de actividades con
  badge de estado y de tipo (Sumativa/Formativa, Grupal/Individual, Con entrega),
  y el syllabus embebido.
- **Filtro invisible:** las actividades con `visible = false` no llegan.

### V4. Actividad — `/estudiante/cursos/{c}/actividad/{a}` → `student/Activities/Index.svelte`
- **Objetivo:** la pantalla de trabajo del alumno. Es la más densa del sistema.
- **Datos consumidos (props exactos):** `id_curso`, `cod_curso`, `nombre_curso`,
  `cod_actividad`, `nombre_actividad`, `descripcion`, `dias_holgura`,
  `dias_holgura_personal`, `fecha_limite`, `es_sumativa`, `trae_archivo`,
  `entrega_obligatoria`, `ultima_nota`, `ultima_evaluacion`, `ultima_entrega`,
  `estado`, `entradas[]`, `listado_interacciones[]`, `rubrica`,
  `id_actividad_asignada_grupo`, `resto_integrantes[]`,
  `archivo_enunciado { nombre_original, mime_type, peso_bytes }`.
- **Componentes UI clave (existen como archivos):**

  | Card | Cuándo aparece |
  |---|---|
  | `ActivityHeaderCard` | Siempre — título, curso, fecha límite |
  | `ActivityStateCard` | Estado `ACTIVA`/`CERRADA` con color (verde esmeralda / gris pizarra) |
  | `ActivityPendingCard` | Hay entrega pendiente |
  | `ActivitySubmittedCard` | Ya entregó |
  | `ActivityGradeCard` | `ultima_nota != null` **y** `es_sumativa` |
  | `ActivityRubricaCard` | Existe rúbrica → abre modal de lectura |
  | `ActivityAgendaCard` | Acceso al hilo de la actividad |
  | `ActivityMembersCard` | `es_grupal` → lista `resto_integrantes` |

- **Reglas de habilitación (en el propio componente):**
  - Muestra nota/rúbrica si `estado === 'ACTIVA' || ultima_nota`.
  - Permite interactuar si `(estado === 'ACTIVA' && !excedioHolgura) || puedeApelar`.
  - → El diseño necesita un **estado "fuera de plazo pero apelable"** distinto de
    "cerrado".

### V5. Agenda de la actividad — `student/Activities/Agenda/*`
- **Objetivo:** hilo cronológico con el equipo docente, dentro de la actividad.
- **Sub-vistas:** `Agenda.svelte` (hilo + compositor), `Entrega.svelte`,
  `Enunciado.svelte`, `Rubrica.svelte`, `Informaciones.svelte`.
- **Compositor — tipos de interacción del selector** (`Agenda.svelte:58`):
  `Consulta` · `Entrega de Avance` · `Duda sobre Rúbrica` · `Otro`.
  Se traducen en servidor a `TipoMensaje`: todos a `Mensaje al profesor` salvo
  `Entrega de Avance` → `Entrega de archivo`.
- **Tipos que el alumno **recibe** y debe distinguir visualmente:**
  `Feedback`, `Evaluación`, `Cierre de actividad`, `Cancelación de entrega`.
- **Componentes UI clave inferidos:** timeline con burbujas diferenciadas por
  `tipo_interaccion`, adjuntos con nombre/peso, selector de tipo + textarea +
  zona de archivo, y separación visual clara entre **mensaje** y **entrega
  formal** (son endpoints distintos: `agenda` vs. `entregas`).

### V6. Mensajería del curso — `/estudiante/cursos/{curso}/mensajeria` → `student/Mensajeria.svelte`
- **Datos consumidos:** `curso { id_curso, nombre, cod_curso, agno_real,
  semestre_real, letra_grupo }`; `componentes[] { id_componente, tipo, no_leidos }`;
  `componente_activo`; `panel` (difusiones + su conversación).
- **Componentes UI:** pestañas por componente con badge, lista de avisos del
  equipo, hilo propio, compositor con selector opcional de docente destinatario.

### V7. Syllabus del curso — `/estudiante/cursos/{curso}/programa` → `student/Courses/Syllabus.svelte`
- **Objetivo:** lectura del programa oficial vigente.
- **Datos consumidos:** salida de `StudentSyllabusPresenter` (mismo criterio de
  sección/docente que el dashboard) + ficha de asignatura.
- **Componentes UI:** documento por secciones I–IX (o las del tipo BÁSICO),
  índice lateral navegable, sin ninguna acción de edición.

---

## Vistas Compartidas (Modificadas)

| Vista | Compartida con | Cómo la ve el Estudiante |
|---|---|---|
| **Documento de syllabus** | Docente, Ayudante, Admin, Jefe de Carrera | **Sólo lectura**, y sólo la versión vigente/aprobada que arma `StudentSyllabusPresenter`. Sin estados internos (`BORRADOR`, `COMPLETO`) ni botones |
| **`Rubrica.svelte`** | Docente (lo importa **literalmente**: `import RubricaView from '../../student/Activities/Agenda/Rubrica.svelte'`) | Idéntico componente; el docente titular tiene además `RubricaEditor` |
| **Bandeja de mensajería** | Docente, Ayudante (`BandejaStaff`) | Versión de alumno: no puede difundir, sólo escribir a su canal; `esStaff: false` cambia el cálculo de no leídos |
| **`/settings/*`** | Todos | Sin diferencias |
| **Actividad** | Docente (`docente/Activities/Index`) | El docente ve **todos los grupos**; el alumno ve **su grupo** y `resto_integrantes` |

---

## Interacciones y Estados

| Interacción | Implementación |
|---|---|
| Sin rol Estudiante | `redirect('/dashboard')->with('error','No tienes permisos para acceder a esta sección')` |
| Rol sin perfil | `Student/DashboardController:28` redirige con *"No tienes acceso a esta sección"* → estado a diseñar |
| Descarga de enunciado ajeno | `abort(403,'Solo los estudiantes pueden acceder a este recurso.')` + verificación de inscripción |
| Componente no inscrito | `abort(403,'No estás inscrito en este componente.')` |
| Actividad oculta | `visible = false` → no llega al listado; no existe estado "próximamente" |
| Plazo | `fecha_limite` + `dias_holgura` + `dias_holgura_personal` → la UI debe mostrar **fecha efectiva personal**, no sólo la nominal |
| Actividad sin entrega | `entrega_obligatoria = false` cuando `tipo_entrega` es "sin entrega" → ocultar el bloque de entrega |
| Nota | `ultima_nota` es `float|null`; sólo tiene sentido si `es_sumativa` |
| Entrega | Dos pasos separados en el backend: crear registro de agenda y adjuntar archivo; la UI debe presentarlos como **una** acción atómica con progreso |
| Validación de archivo | Extensiones y tamaños de `config/filetypes` (PDF e imagen), validados en servidor |
| Lectura de mensajes | Abrir la pestaña marca como leído; los badges se recalculan después |
| Estado vacío sin cursos | `total_cursos = 0` → el dashboard necesita un vacío explícito (matrícula pendiente) |
