# Contexto de observaciones — 2026-10-06 — módulo de actividades

> Este documento da contexto a 6 observaciones crudas sobre el módulo de actividades. No es el resumen de una sesión de trabajo: **no hubo cambios de código ni commits**. Cada punto se contrastó con el código de `main` (`3e0a0b9`) y con el backlog `docs/backlogs/2026-10-06_gestion_actividades.md` (commit `01403ab`, de otro integrante), que ya planifica estos mismos puntos. Las afirmaciones marcadas **(verificado)** se comprobaron leyendo el código o consultando la BD de desarrollo; las marcadas **(según el backlog)** vienen de ese documento y no se revisaron aparte. Lo que no se pudo confirmar se marca **[no verificado]**.

Observaciones originales:

1. Mover el mensaje de enunciado al header de la actividad.
2. Colores de las actividades cerradas, activas, etc.
3. «Cómo se evalúa» se ve raro e incompleto: es una barra de progreso en vez de tarjetas informativas.
4. Bug: al apretar «Mensajes», el breadcrumb muestra el nombre del grupo (correcto); en el curso muestra el nombre de la asignatura (incorrecto).
5. Ver si el sistema se cae con mucho flujo de subida y descarga.
6. Ver una función para crear actividades en varios cursos de la misma asignatura y el mismo docente (copiarlas y no hacer doble trabajo).

## 0. Actualización — revisión en el navegador (2026-10-06, noche)

Se recorrieron las pantallas en Chrome contra el servidor local (`127.0.0.1:8000`, Vite al día), como docente de DM069 y como un estudiante de DM069 · Grupo A. Las propuestas de arreglo están dibujadas en el canvas «Módulo de actividades — propuestas de arreglo» (Claude Design, privado): https://claude.ai/artifact/GsuB9AD3cz2qo1D5yoyM4L

Cambia o se agrega lo siguiente respecto de las secciones de abajo (verificado en el navegador):

- **Observación 3 — corrección de interpretación.** Existe una sección titulada literalmente **«Cómo se evalúa»** en la ficha del curso del estudiante (`resources/js/pages/student/Courses/components/CursoInformacion.svelte:52`). Cada componente se muestra como una barra de progreso (`h-2.5`) con su porcentaje; en pantallas anchas, la cifra queda lejos, al otro lado. El requisito de aprobación sale en una caja roja (`bg-uta-red-light`). Esa es casi seguro la pantalla de la observación; el backlog (`UI-03`) la interpretó como el tablero docente. El tablero docente igual tiene un problema real (las formativas marcan «0/26 calificadas» y, cerradas, «26 sin calificar»), que queda como punto aparte.
- **Observación 4 — en el docente.** El breadcrumb de la ficha del curso y de Actividades muestra el **código** (`Mis Cursos › 10000 › Actividades`), mientras que Mensajería muestra el nombre (`Mis Cursos › Taller de Multimedia II › Mensajería`). En el menú lateral, los dos cursos del docente se llaman igual y sólo los distingue el código. En el estudiante, el curso y Mensajería muestran el mismo texto; la letra del grupo sólo aparece en el encabezado de Mensajería («10000 · Año 2026 · Semestre 2 · Grupo A»). El enlace del curso en la vista de una actividad lleva a `/estudiante/cursos`.
- **Bug nuevo, de fechas.** En la evaluación del docente (`/docente/cursos/1/actividades/2/evaluacion`), «Fecha límite» muestra **«23 sept 2026, 09:00 p. m.»** para una actividad con `fecha_limite = 2026-09-24`: la fecha sin hora se interpreta como medianoche UTC y se muestra en hora de Chile. El tablero y la vista del estudiante muestran bien el 24.
- **Observación 2 — confirmado en el DOM:** las pastillas «CERRADA» de la evaluación docente llevan `bg-green-100 text-green-800 border-green-300`.
- **Observación 6 — el caso ya ocurrió.** El curso 2 (DM069 · B, `11100`) tiene las mismas 7 actividades que el curso 1, creadas a mano y con fechas un día antes. En el curso 1, las 7 actividades cuelgan del componente Taller y Laboratorio no tiene ninguna.
- **Observación 1:** en la BD local ninguna actividad tiene enunciado, así que el botón no se pudo ver; el análisis de esa pantalla sigue siendo sobre el código.
- **Observación 5:** no se hizo prueba de carga. El servidor local (`artisan serve`, un solo proceso) no representa a producción.

Referentes consultados para las propuestas: «Copy To» de Canvas para copiar una tarea a otro curso, y Sharing Cart de Moodle, que copia sin datos de los estudiantes; convenciones de pastillas de estado (gris = inactivo/archivado, verde = éxito o activo, color siempre acompañado de texto o ícono); `X-Accel-Redirect`/`X-Sendfile` para que el servidor web sirva las descargas.

**Cambios de entorno hechos para la revisión (sólo BD local de desarrollo, autorizados en la sesión):** `usuario.fecha_cambio_passhash = now()` para el docente de DM069 (`15695395-4`), y una clave local de prueba para el estudiante `id_usuario = 21`, con su `fecha_cambio_passhash` al día. No se tocó el esquema ni se corrieron migraciones.

## 1. Resumen ejecutivo

- **De qué tratan:** las 6 observaciones son del módulo de actividades. Tres son de presentación (1, 2, 3), una es un bug de navegación (4), una es de resiliencia (5) y una es una funcionalidad nueva (6).
- **Estado:** los 6 puntos ya están en el backlog `2026-10-06_gestion_actividades.md`, repartidos en 6 tarjetas y 3 releases (18 pts). Ninguno está implementado (verificado: el código citado sigue igual en `main`).
- **Lo que confirma el código:**
  - El enunciado del estudiante está en el panel lateral, no en el header (1).
  - Hay **tres paletas** distintas para los estados; la vista de evaluación del docente pinta «CERRADA» en verde (2).
  - El tablero del docente usa una barra de 1,5 px de alto que sólo cuenta grupos con nota (3).
  - La subida de una entrega guarda el archivo **dentro** de una transacción abierta de BD (5).
  - No existe ninguna forma de copiar una actividad a otro curso (6).
- **Lo que el backlog afirma y no se sostiene del todo:** ver la sección 9. Las principales:
  - con los datos de desarrollo, `curso.nombre` es igual al nombre de la asignatura, así que el breadcrumb no es la causa del síntoma;
  - el criterio «8/10 entregas» no coincide con lo que mide `total_entregas`.
- **Pendiente:** reproducir el punto 4 en el navegador y tomar las decisiones abiertas de la sección 6.

## 2. Estado de publicación

No hay commits ni cambios en el árbol de trabajo asociados a estas observaciones. Este documento queda sin agregar a git.

Relación con el backlog y el tablero (**según el backlog**; no se consultó Trello):

| Observación | Tarjeta | Release | Pts | Prioridad |
|---|---|---|---|---|
| 1. Enunciado al header | `UI-02` (#81) | R2 | 2 | P1 |
| 2. Colores de estados | `UI-01` (#79) | R1 | 1 | P0 |
| 3. Barra de evaluación → tarjetas | `UI-03` (#82) | R2 | 3 | P1 |
| 4. Breadcrumb curso/asignatura | `BUG-01` (#78) | R1 | 2 | P0 |
| 5. Carga de subida/descarga | `FEAT-01` (#80) | R1 | 5 | P0 |
| 6. Actividades multi-curso | `FEAT-02` (#83) | R3 | 5 | P1 |

⚠️ Los IDs `BUG-01`, `UI-01`, `UI-02`, `UI-03`, `FEAT-01` y `FEAT-02` **ya se usaron** en el mismo tablero `arreglos-reunion-23-09-2026` para otras tarjetas, que el resumen `2026-10-06-resumen.md` (sección 8) da como movidas a «Hecho». Los números de tarjeta (#78–#83) las distinguen, pero conviene renombrarlas para que no se confundan.

## 3. Contexto por área

### Frontend (Svelte/Inertia)

| Observación | Dónde está hoy | Qué pasa |
|---|---|---|
| 1. Enunciado al header | `resources/js/pages/student/Activities/Index.svelte:296-315` (aside «Contexto de la actividad») y `cards/ActivityHeaderCard.svelte` (sólo la cápsula de rúbrica, línea 66) | El botón «Ver enunciado» abre el modal `Agenda/Enunciado.svelte`, pero vive en el panel lateral, lejos del título. En móvil queda debajo de todo el contenido. «Mensaje de enunciado» se refiere al **archivo** de enunciado (`agenda.actividad.uuid_archivo_enunciado`), no a un mensaje de la agenda (verificado). |
| 2. Colores de estados | `resources/js/pages/docente/Activities/Index.svelte:552-558` (`getEstadoColor`, que reciben `GrupoCard.svelte` y `EstudiantesTabla.svelte`); `resources/js/utils/actividadEstado.ts` (`PILL_ESTADO`, `PUNTO_ESTADO`); `resources/js/pages/student/Activities/cards/ActivityDeadlineCard.svelte:44-66`; `resources/js/pages/docente/components/ActividadesPorEstado.svelte:98-100` | Ver la tabla de paletas de abajo. |
| 3. «Cómo se evalúa» | `resources/js/pages/docente/components/ActividadesPorEstado.svelte:213-230` | Una barra `h-1.5` más el texto «`{calificados}/{total_grupos}` calificadas · prom. X». `calificados` es `COUNT(nota)` sobre `agenda.actividad_asignada_grupo` (`DocenteActivityController.php:245-255`). En las actividades **formativas** no hay nota, así que la barra se queda en 0 aunque el docente haya evaluado: eso explica que «se vea incompleto». `total_entregas` ya llega al frontend, pero sólo lo usa `ActividadEliminarDialog.svelte`. |
| 4. Breadcrumb | `resources/js/pages/student/Courses/Show.svelte:82`, `resources/js/pages/student/Mensajeria.svelte:81` y `:98-101`, `resources/js/pages/student/Activities/Index.svelte:97` | Ver el detalle debajo. |

**Paletas de estado (verificado):**

| Estado | Evaluación docente (`getEstadoColor`) | `actividadEstado.ts` (lista de actividades) | Tablero docente (punto de columna) | Estudiante (`ActivityDeadlineCard`) |
|---|---|---|---|---|
| Planificada | amarillo (`yellow-100/800`) | beige `#F5F1EA`, punto `#A8A093` | pizarra `#64748B` | — |
| Activa | azul institucional (`uta-blue`) | esmeralda `#ECFDF5`/`#047857` | esmeralda `#059669` | esmeralda («En plazo») / ámbar («Fuera de plazo») |
| Cerrada | **verde** (`green-100/800`) | pizarra `#F1F5F9`/`#475569` | pizarra `#475569` | pizarra (`slate`) |

Dos matices que el backlog no menciona:
- `getEstadoColor` no pinta el estado de la **actividad**, sino el del **grupo** (`estado_actividad_asignada`). Ese estado lo calcula `ActividadAsignadaGrupo::calcularEstadoGrupo()` **con** las holguras general y personal. `actividadEstado.ts` usa sólo `fecha_limite`. Por eso, en el mismo día, el tablero puede mostrar la actividad «Cerrada» y la vista de evaluación mostrar un grupo «Activa». Unificar los colores no basta: hay que decidir si el rótulo debe coincidir.
- En el tablero, el punto de «Planificada» (`#64748B`) es el mismo tono que `PUNTO_ESTADO.cerrada`.

**Detalle del punto 4 (breadcrumb):**
- En la vista del curso, el tercer nivel es `curso.asignatura_nombre ?? curso.nombre`. Como el backend manda `'N/A'` cuando no hay asignatura (`Student/CourseController.php:104`), el respaldo `curso.nombre` nunca se usa (verificado).
- En Mensajería, el breadcrumb usa `curso.nombre`, y además el encabezado agrega «Año · Semestre · **Grupo X**» con `curso.letra_grupo` (`Mensajeria.svelte:98-101`).
- En la BD de desarrollo, `curso.nombre` es **idéntico** al nombre de la asignatura en los cursos consultados (p. ej. «Taller de Multimedia II» / «Taller de Multimedia II»; verificado con tinker). Con esos datos, los dos breadcrumbs muestran el mismo texto. Lo que el usuario percibe como «nombre de grupo» en Mensajería es, muy probablemente, la letra del grupo del **encabezado**, que la vista del curso no muestra **[no verificado en el navegador]**.
- Bug adicional: en la vista de una actividad, el nivel del curso enlaza a `/estudiante/cursos` (la lista), no al curso (`student/Activities/Index.svelte:97`).
- Las vistas del docente (`docente/Mensajeria.svelte:27`, `docente/Activities/Index.svelte:125`) usan `curso.nombre` de forma consistente.

### Backend (Laravel)

| Observación | Dónde está hoy | Qué pasa |
|---|---|---|
| 4. Breadcrumb | `app/Http/Controllers/Student/ActivityController.php:236` | `'nombre_curso'` se envía como `asignacionPlan->asignatura->nombre ?? curso->nombre`: la vista de actividad hereda el mismo criterio que la del curso. |
| 5. Subida | `app/Http/Controllers/Student/AgendaController.php:131-151` (`storeEntrega`) | `DB::beginTransaction()` se abre **antes** de `AgendaArchiveHandler::store()`. Ese paso valida el tipo, busca virus (`VirusDetectedException`), comprime y escribe en disco. Durante todo ese tiempo, el worker de PHP retiene una conexión de PostgreSQL con una transacción abierta. Con muchas entregas al cierre del plazo, el límite de conexiones de la BD se agota antes que el disco (verificado el orden; el agotamiento es una inferencia, **[no verificado]** con una prueba de carga). |
| 5. Descarga | `Student/ActivityController.php:314` (`response()->file`), `Docente/DocenteActivityController.php:594` (`response()->download`) | Ambos sirven el archivo a través de PHP. Un worker queda ocupado durante toda la transferencia; con clientes lentos, el pool de PHP-FPM se llena. No hay `X-Sendfile`/`X-Accel-Redirect` ni subida por partes. |
| 6. Multi-curso | `Docente/DocenteActivityController::store` (crea en un único `id_componente`); `app/Services/CursoService.php:247-263` (`copiar`) | No existe copiar una actividad a otro curso (verificado: no hay `clonar`/`duplicar`/`replicar` en controladores ni servicios). Lo más cercano es `CursoService::copiar`, que copia un curso **entero** (lo usa el admin) y, al copiar actividades, deja `fecha_limite` en `null` y **no** copia el enunciado ni la rúbrica. |

### Base de datos (PostgreSQL y submódulo)

- No se proponen cambios de esquema para los puntos 1 a 5.
- Para el punto 6, `agenda.actividad` ya tiene la columna `es_plantilla` (`CursoService::copiar` la escribe en `false`). Podría servir para un enfoque de «plantillas», pero hoy no la usa ninguna pantalla **[no verificado su significado previsto]**.
- La rúbrica cuelga de la actividad (`BaseActividad::rubricas()`), así que replicarla implica duplicar filas; el enunciado es un `uuid_archivo` que se podría referenciar desde varias actividades.

### Configuración

Ninguna observación exige tocar `config/`, `.env`, dependencias ni `routes/`. El punto 5, si se adopta `X-Accel-Redirect`, requiere configurar el servidor web de producción, que no está en este repositorio.

## 4. Cambios que romperían compatibilidad (si se implementa lo planificado)

- **Props de Inertia:**
  - el punto 4 cambiaría el contenido de `nombre_curso` en `student/Activities/Index`;
  - el punto 3 probablemente agregue métricas a cada actividad del listado docente (p. ej. grupos con entrega, evaluaciones formativas).
- **Endpoints:** el punto 6 agregaría una ruta nueva (clonar) o un campo nuevo en `store` (cursos destino). Ninguna ruta existente cambiaría.
- **Comportamiento:** el punto 5 cambia el orden archivo/BD en `storeEntrega`. Si falla la inserción, el archivo debe limpiarse a mano (hoy lo deshace la transacción sólo en BD; el archivo ya escrito no se borra **[no verificado]**).
- **Columnas y tablas:** ninguna, salvo que el punto 6 adopte `es_plantilla`.

## 5. Qué hacer a continuación

1. **Reproducir el punto 4 en el navegador** con un estudiante cuyo curso tenga `letra_grupo`. Confirmar si lo que se ve en Mensajería es el breadcrumb o el encabezado. En local se entra con RUT, no con email.
2. **Corregir el backlog** con lo de la sección 9 antes de empezar `BUG-01` y `UI-03`.
3. **Resolver las decisiones abiertas** de la sección 6, sobre todo la de los puntos 2 y 6.
4. Empezar por R1, como propone el backlog: `BUG-01`, `UI-01` y `FEAT-01` son P0.

## 6. Decisiones técnicas abiertas

| Decisión | Opciones | Comentario |
|---|---|---|
| Qué rótulo mostrar en el breadcrumb del curso | `curso.nombre` / asignatura + «Grupo X» / sólo asignatura | Como `curso.nombre` = asignatura en los datos actuales, usar `curso.nombre` no cambia nada visible. Para distinguir secciones hay que agregar `letra_grupo`, igual que Mensajería. |
| Estado de grupo vs. estado de actividad | Mostrar ambos con el mismo color / mostrar sólo el de la actividad / explicar la holgura | Un grupo puede seguir «Activa» por holgura cuando la actividad ya está «Cerrada». |
| Qué cuenta como «evaluado» en el tablero | Sólo grupos con nota (hoy) / cualquier registro en `agenda.evaluacion` (`ActividadAsignadaGrupo::yaFueEvaluado()`) | Sin lo segundo, las formativas nunca avanzan. |
| Replicar al crear vs. clonar después | Selector «Replicar también en…» en el formulario / acción «Clonar a otro curso» / ambos (propuesta del backlog) | El backlog propone ambos; replicar al crear es más simple de explicar. |
| Qué es un «curso hermano» | Mismo `id_asignacion_plan` y mismo período / misma asignatura en cualquier período | El backlog dice «mismo `id_asignacion_plan`», pero no fija el período. |
| Qué se copia | Fechas, enunciado (referencia o copia), rúbrica (copia), grupos | `CursoService::copiar` hoy **no** copia fechas, enunciado ni rúbrica: conviene no reutilizarlo tal cual. |

## 7. Verificación

**Revisado en este análisis (verificado):**
- Todas las rutas de archivo y líneas citadas en las secciones 3 y 4, sobre `main` en `3e0a0b9`.
- La consulta de métricas del tablero (`DocenteActivityController.php:245-290`) y que `total_entregas` sólo se consume en `ActividadEliminarDialog.svelte`.
- El orden transacción → archivo en `storeEntrega`.
- Que no hay funcionalidad de clonar/replicar actividades.
- Nombres de curso frente a asignatura en la BD de desarrollo (tinker, 4 cursos).

**No verificado:**
- El síntoma del punto 4 en el navegador.
- Que el sistema efectivamente se caiga con carga (punto 5): no se hizo prueba de carga.
- Los números de las tarjetas de Trello: se tomaron del backlog.

## 8. Pendientes y riesgos

- **Punto 5 es el de mayor riesgo:** el cierre de plazo concentra las subidas y cada una mantiene una transacción abierta mientras se escanea y comprime el archivo. Conviene medirlo con una prueba de carga antes y después del cambio, no sólo reordenar el código.
- **Al sacar el archivo de la transacción** hay que limpiar el archivo si falla la inserción en BD; si no, quedan huérfanos en disco.
- **Punto 3:** si sólo se cambia la forma (tarjetas en vez de barra) sin cambiar el criterio de «calificado», las formativas seguirán viéndose incompletas.
- **Punto 2:** reemplazar `getEstadoColor` por `PILL_ESTADO` exige mapear `ACTIVA/CERRADA/PLANIFICADA` (mayúsculas, estado de grupo) a `activa/cerrada/planificada` (estado de actividad), que no significan lo mismo.
- **Punto 6:** depende de que los componentes y unidades del curso destino tengan equivalentes. Si el curso destino no tiene la unidad, la actividad no se puede crear (`id_unidad` es NOT NULL).
- **IDs de tarjeta repetidos** en el tablero (ver sección 2).

## 9. Discrepancias

- **Breadcrumb (`BUG-01`):** el backlog dice que `curso.nombre` «incluye la sección, ej. "Taller de Diseño Gráfico (Sección 1)"». En la BD de desarrollo no es así: `curso.nombre` es igual al nombre de la asignatura. **[no verificado]** si en producción los nombres traen la sección. El primer criterio de aceptación (`curso.nombre`) no corregiría nada visible con estos datos; el alternativo (`asignatura (Grupo X)`) sí.
- **Barra de evaluación (`UI-03`):** el criterio «Entregas realizadas vs. total de grupos (ej. `8/10 entregas`)» no se puede armar con `total_entregas`, que cuenta **filas** de entrega vigentes, no grupos con entrega. Un grupo que reemplaza su archivo suma más de una. Hace falta una métrica nueva (grupos con al menos una entrega vigente).
- **Colores (`UI-01`):** el backlog dice que en `actividadEstado.ts` y en las tarjetas del alumno está la paleta canónica. Es cierto para `cerrada` y `activa`, pero el tablero del docente usa otro punto para `planificada`, y `ActivityDeadlineCard` tiene su propia paleta con Tailwind (`emerald`, `amber`, `slate`), no las constantes de `actividadEstado.ts`. Tampoco menciona que `getEstadoColor` pinta el estado del **grupo**.
- **Enunciado (`UI-02`):** la descripción coincide con el código. Para el docente, el backlog sólo dice que el enunciado «se gestiona desde modales»; la tarjeta `UI-02` cubre sólo la vista del estudiante.
- **Línea citada:** el backlog ubica `nombre_curso` en `ActivityController.php` línea 233; está en la 236.
- **Multi-curso (`FEAT-02`):** el backlog no menciona `CursoService::copiar` ni la columna `es_plantilla`, que son el antecedente más cercano dentro del código.
