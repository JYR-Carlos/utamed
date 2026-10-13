# Backlog de Trabajo: Dashboard de Alumno y Evaluación en Actividades Formativas

* **Fecha**: 2026-10-09
* **Rama Base**: `fix/copia-curso-modal-y-codigo`
* **Metodología**: Triada estricta `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`
* **Calibración**: Escala Fibonacci de 5 niveles (1, 2, 3, 5 y 8 pts)
* **Estrategia de Selección de Evaluación Formativa**: Sistema A (última evaluación cronológica automática, con arquitectura desacoplada y 100% migrable a Sistema B con flag/pin si se requiere en el futuro)

---

## 1. Observaciones Técnicas y Diagnóstico

### [Dashboard Estudiante] Actividades Cerradas Listadas como Pendientes
* **Elemento / Contexto**: `/estudiante/dashboard` (sección «Próximas a vencer») y `/estudiante/cursos/{id}` (sección «Próximas entregas»).
* **Problema reportado**: El dashboard del alumno muestra actividades cerradas (concluidas o vencidas hace mucho tiempo) dentro del bloque de pendientes o próximas entregas.
* **Evidencia en código**:
  * En `app/Services/Student/ResumenActividadesEstudiante.php` (`proximasAVencer`):
    * La consulta base solo aplica `whereDate('fecha_limite', '<=', $tope->toDateString())` y no verifica si el estado calculado de la actividad o del grupo es `CERRADA`.
    * No discrimina actividades donde el estudiante o grupo ya completó la entrega (`Agenda::tipo_mensaje === ENTREGA_DE_ARCHIVO`) ni actividades ya evaluadas.
    * Si una actividad venció hace semanas pero tiene holgura configurada (`nro_dias_adicionales_para_bloqueo`), el cálculo `$plazo = Carbon::parse($actividad->fecha_limite)->endOfDay()->addDays($holgura)` sitúa incorrectamente el plazo dentro del rango `between($ahora, $tope)`, volviendo a mostrarla como por vencer.
    * `componentesDelEstudiante` no filtra por período académico activo (`semestre_real`, `agno_real`), pudiendo incorporar asignaturas cerradas si la matrícula quedó en `INSCRITO`.
  * En `resources/js/pages/student/Courses/Show.svelte` (`proximasEntregas`):
    * El filtro reactivo evalúa `a.con_entrega && a.fecha_limite && parseFechaSoloDia(a.fecha_limite) >= hoy`, omitiendo verificar si el estado ya es `CERRADA` (`a.estado !== 'CERRADA'`) o si ya existe entrega confirmada.
* **Diagnóstico de centralización**: La lógica de estados no debe dispersarse en consultas SQL y closures ad-hoc dentro de controladores ni servicios; debe centralizarse en los modelos `App\Models\Agenda\Actividad` y `App\Models\Agenda\ActividadAsignadaGrupo` mediante métodos semánticos reutilizables (`estaCerrada()`, `estaPendienteEntrega()`).

### [Actividades Formativas] Desacople Total de Rúbricas y Modelo de Datos
* **Elemento / Contexto**:
  * Docente: `/docente/cursos/{id}/actividades/{act}` (`resources/js/pages/docente/Activities/Index.svelte`, botón «Crear Rúbrica»).
  * Alumno: `/estudiante/cursos/{id}/actividades/{act}` (`resources/js/pages/student/Activities/cards/ActivityHeaderCard.svelte` y modal en `Index.svelte`).
  * Backend: `app/Http/Controllers/Docente/DocenteActivityController.php` (`storeEvaluacion`) y tabla `agenda.evaluacion`.
* **Problema reportado**: Las actividades formativas no poseen rúbrica. Actualmente la interfaz muestra opciones para crear/ver rúbrica y el backend exige `id_rubrica` de forma obligatoria.
* **Evidencia en código**:
  * En `ActivityHeaderCard.svelte`, la columna de rúbrica se renderiza siempre (mostrando «No hay rúbrica para esta actividad» cuando no existe); en formativas debe eliminarse dicha columna para que la ficha de información utilice el ancho completo.
  * En `docente/Activities/Index.svelte:712`, la condición `{#if actividad.es_titular || rubrica}` habilita el botón «Crear Rúbrica» sin verificar si la actividad es formativa.
  * En `DocenteActivityController::storeEvaluacion:1936`, la regla de validación impone `'id_rubrica' => 'required|integer|exists:rubrica,id_rubrica'`.
  * En la base de datos PostgreSQL, la tabla `agenda.evaluacion` tiene restricciones `NOT NULL` en las columnas `id_rubrica`, `puntaje_obtenido` y `resultado`.
* **Solución de esquema**:
  * En lugar de crear tablas o columnas adicionales, se modifican las restricciones de `agenda.evaluacion` mediante migración segura:
    * `ALTER TABLE agenda.evaluacion ALTER COLUMN id_rubrica DROP NOT NULL;`
    * `ALTER TABLE agenda.evaluacion ALTER COLUMN puntaje_obtenido DROP NOT NULL;`
    * `ALTER TABLE agenda.evaluacion ALTER COLUMN resultado DROP NOT NULL;`
    * Se añade un check constraint parcial para garantizar que las sumativas mantengan rúbrica obligatoria:
      `CHECK ((id_rubrica IS NOT NULL AND puntaje_obtenido IS NOT NULL) OR (id_rubrica IS NULL))`

### [Evaluación Formativa] Repurpose del Tipo de Mensaje en Agenda
* **Elemento / Contexto**: Modal de agenda del docente en `/docente/cursos/{id}/actividades/{act}` (`resources/js/pages/docente/Activities/Agenda/AgendaDocente.svelte`).
* **Problema reportado & Requerimiento**:
  * Se mantiene el botón de tipo «Evaluación», pero en formativas no despliega rúbrica ni solicita nota numérica chilena (1,0 a 7,0).
  * Al seleccionar «Evaluación», el formulario se expande desplegando la consulta: *«¿Qué opina del trabajo?»*.
  * Presenta tres alternativas de apreciación cualitativa con colores de estado:
    * **Bueno** (color verde / emerald)
    * **Regular** (color naranja claro / amber)
    * **Malo** (color rojo / rose)
  * El docente escribe un mensaje de evaluación final de longitud amplia («puede ser largo»).
  * Al enviar, se registra la interacción como tipo `Evaluación` con `evaluacion_obtenida` ('Bueno' | 'Regular' | 'Malo') y el cuerpo del mensaje en `agenda.agenda`.
* **Evidencia en código**:
  * En `AgendaDocente.svelte:218-228`, la función `cambiarTipo('Evaluación')` abre el slideover de rúbrica (`mostrarSlideoverEvaluacion = true`) o muestra alertas de ausencia de rúbrica, y en `AgendaDocente.svelte:358-372` espera `evaluacionCualitativa` calculada desde la escala de una rúbrica inexistente.

### [Visualización Estudiante] Repurpose de la Tarjeta de Nota en Actividad Principal
* **Elemento / Contexto**: Columna lateral en `/estudiante/cursos/{id}/actividades/{act}` (`resources/js/pages/student/Activities/cards/ActivityGradeCard.svelte` y `Index.svelte`).
* **Problema reportado & Requerimiento**:
* Para sumativas, `ActivityGradeCard.svelte` muestra la calificación numérica grande (1,0 - 7,0) y badge Aprobada/Reprobada.
* Para formativas, `ultima_nota` es `null`, lo que provocaba que la tarjeta quedara oculta (`Index.svelte:298`).
* Se requiere repurposear la tarjeta: para actividades formativas que hayan recibido evaluación, debe renderizar la información importante de la evaluación emitida:
  * Badge de apreciación cualitativa: Bueno (verde), Regular (naranja claro), Malo (rojo).
  * El mensaje de evaluación provisto por el docente.
  * **Criterio de Selección (Sistema A)**: Si existen múltiples evaluaciones en el historial del grupo, el sistema selecciona automáticamente la última emitida cronológicamente. La interfaz del estudiante consume este contrato de forma transparente, permitiendo migrar en el futuro a selección manual/pin (Sistema B) sin modificar los componentes de visualización.
  * Metadatos: fecha de emisión y docente evaluador.
  * Sin mostrar escalas numéricas ni botón de rúbrica.

---

## 2. Interpretación e Investigación (La Vista Amplia: Lo que «No Se Vio»)

### 2.1. Desacople Estructural en el Ciclo de Vida y Estado de Actividades
El síntoma de actividades cerradas listadas como pendientes en el dashboard del alumno proviene de la falta de centralización del estado de completitud:
* **Persistencia estática vs. cálculo dinámico**: En el modelo de dominio original, `agenda.actividad_asignada_grupo.estado_actividad_asignada` se persistía una sola vez al sembrar o crear el grupo (`PLANIFICADA`). Aunque posteriormente se introdujo `calcularEstadoGrupo()`, los servicios de consulta como `ResumenActividadesEstudiante` no integraban este cálculo reactivo en sus filtros de agregación, descansando exclusivamente en cláusulas SQL de fecha límite plana (`whereDate('fecha_limite', '<=', $tope)`).
* **Ausencia de noción de completitud**: En la lógica del dashboard estudiantil, una actividad se consideraba "pendiente" únicamente si su fecha límite ajustada caía dentro del rango de los próximos 7 días, ignorando el estado de entrega (`ENTREGA_DE_ARCHIVO`) o el estado de calificación. Un estudiante que ya entregó su trabajo o que ya fue calificado seguía viendo la actividad en «Próximas a vencer».
* **Fuga de contexto de período**: Mientras que la lista de cursos en `Dashboard.svelte` filtra estrictamente por `periodoActual` (`semestre_real` y `agno_real`), el servicio de agregación de actividades recorre todas las inscripciones históricas con estado `INSCRITO`, lo que genera colisiones con actividades de semestres previos si los registros no fueron cerrados administrativamente.
* **Centralización en el Modelo**: La fuente única de verdad debe residir en `Actividad` y `ActividadAsignadaGrupo`. Incorporar métodos `estaCerrada()` y `estaPendienteEntrega()` permite que tanto el dashboard como el detalle de curso y las futuras secciones invoquen la misma regla unificada.

### 2.2. Suposición Forzada del Paradigma Sumativo en el Modelo de Datos
La insistencia en solicitar rúbricas y notas numéricas en actividades formativas surge de un acoplamiento histórico en la base de datos:
* **Monocultivo de Rúbricas**: La tabla `agenda.evaluacion` se diseñó bajo la premisa de que toda evaluación en el sistema emana obligatoriamente de una rúbrica analítica (`id_rubrica NOT NULL`, `puntaje_obtenido NOT NULL`). Al intentar soportar actividades formativas, se intentó forzar una escala cualitativa dentro de los metadatos JSON de la propia rúbrica, obligando a los docentes a crear rúbricas artificiales para actividades que por definición pedagógica no utilizan matriz de criterios.
* **Flexibilización sin Ruptura**: Mediante la eliminación del `NOT NULL` en `id_rubrica`, `puntaje_obtenido` y `resultado` junto al check constraint condicional, `agenda.evaluacion` acoge evaluaciones formativas puras sin crear tablas huérfanas ni duplicar entidades.
* **Estrategia Sistema A y Migrabilidad Futura**: Al adoptar el Sistema A (última evaluación cronológica), el contrato de datos frontend en `ActivityGradeCard.svelte` queda completamente definido. Si en el futuro se incorpora un selector explícito docente (Sistema B), solo se agrega una columna `es_destacada` en BD y un control en la agenda del docente, mientras que el visor del alumno y el fallback backend permanecen 100% compatibles.

---

## 3. Planificación (Releases y Tablero Kanban)

### Release 1: Corrección de Dashboard de Estudiante y Entregas Pendientes
*Rama sugerida:* `feature/student-dashboard-actividades-pendientes`
*Puntos estimados:* 5 pts

* ### `[BUG-T01]` [3 pts] [R1] [P0] Dashboard Estudiante — Centralizar estados en modelos y filtrar actividades cerradas/entregadas
  * **Ruta**: `/estudiante/dashboard`
  * **Archivos**:
    * `app/Models/Agenda/Actividad.php`
    * `app/Models/Agenda/ActividadAsignadaGrupo.php`
    * `app/Services/Student/ResumenActividadesEstudiante.php`
    * `resources/js/components/student/ProximasAVencerCard.svelte`
  * **Problema & Causa**: `proximasAVencer` listaba actividades cuyo plazo ya había expirado o cuya holgura residual las reubicaba artificialmente en la ventana semanal, y no verificaba si el grupo ya completó la entrega o si la actividad ya fue evaluada. No utilizaba métodos centralizados del modelo.
  * **Criterios de Aceptación (DoD)**:
    * [x] Se implementan los métodos `estaCerrada()` y `estaPendienteEntrega()` en `ActividadAsignadaGrupo` y `Actividad`.
    * [x] Las actividades con estado calculado `CERRADA` no aparecen en el listado de próximas a vencer.
    * [x] Las actividades que ya poseen entrega vigente confirmada (`tipo_mensaje === ENTREGA_DE_ARCHIVO`) o ya están evaluadas se excluyen de la bandeja.
    * [x] Las actividades pertenecientes a cursos de períodos académicos cerrados o anteriores quedan excluidas.
    * [x] Si no quedan actividades pendientes activas, se visualiza el estado vacío («Nada por vencer»).

* ### `[BUG-T02]` [2 pts] [R1] [P0] Detalle de Curso — Excluir actividades cerradas o entregadas en sección «Próximas entregas»
  * **Ruta**: `/estudiante/cursos/{id}`
  * **Archivos**:
    * `resources/js/pages/student/Courses/Show.svelte`
    * `resources/js/pages/student/Courses/components/CursoProximasEntregas.svelte`
  * **Problema & Causa**: En `Show.svelte`, el cálculo de `proximasEntregas` sólo evalúa la fecha límite sin considerar si la actividad ya está en estado `CERRADA` o si el estudiante/grupo ya realizó la entrega correspondiente.
  * **Criterios de Aceptación (DoD)**:
    * [x] `proximasEntregas` filtra y descarta actividades con estado `CERRADA` basándose en el estado derivado del modelo.
    * [x] Las actividades con entrega confirmada se retiran del bloque de próximas entregas.
    * [x] Si no hay actividades pendientes, se muestra el estado «No tienes entregas pendientes en este curso».

### Release 2: Desacople de Rúbricas y Flujo de Evaluación Formativa Docente
*Rama sugerida:* `feature/formativas-evaluacion-docente`
*Puntos estimados:* 7 pts

* ### `[UI-T03]` [2 pts] [R2] [P0] Actividades Formativas — Ocultar botones y bloques de rúbrica en vistas docente y estudiante
  * **Ruta**: `/docente/cursos/{id}/actividades/{act}` y `/estudiante/cursos/{id}/actividades/{act}`
  * **Archivos**:
    * `resources/js/pages/docente/Activities/Index.svelte`
    * `resources/js/pages/student/Activities/cards/ActivityHeaderCard.svelte`
  * **Problema & Causa**: En actividades formativas se mantenía visible el botón para crear rúbrica en docente y el contenedor de rúbrica en la cabecera del estudiante (con aviso de "No hay rúbrica").
  * **Criterios de Aceptación (DoD)**:
    * [x] En `ActivityHeaderCard.svelte`, cuando `!es_sumativa`, no se muestra la columna derecha de rúbrica ni el aviso de ausencia de rúbrica; la tarjeta ocupa el ancho completo.
    * [x] En `docente/Activities/Index.svelte`, el botón de «Crear Rúbrica» / «Editar Rúbrica» se oculta si `actividad.es_sumativa === false`.

* ### `[FEAT-T04]` [5 pts] [R2] [P0] Agenda Docente — Desplegable cualitativo (Bueno/Regular/Malo), mensaje extenso y migración partial check
  * **Ruta**: `/docente/cursos/{id}/actividades/{act}` (Modal de Agenda)
  * **Archivos**:
    * `resources/js/pages/docente/Activities/Agenda/AgendaDocente.svelte`
    * `resources/js/pages/docente/Activities/Index.svelte`
    * `app/Http/Controllers/Docente/DocenteActivityController.php`
    * `database/migrations/11_alter_agenda_evaluacion_nullable_rubrica_check.php`
  * **Problema & Causa**: Al seleccionar «Evaluación» en actividades formativas se exigía rúbrica y nota numérica. El requerimiento exige reconvertir este botón para que expanda un selector cualitativo con tres opciones de color y un mensaje amplio de evaluación.
  * **Criterios de Aceptación (DoD)**:
    * [x] Se crea la migración que remueve `NOT NULL` de `id_rubrica`, `puntaje_obtenido` y `resultado` en `agenda.evaluacion` y añade `CHECK ((id_rubrica IS NOT NULL AND puntaje_obtenido IS NOT NULL) OR (id_rubrica IS NULL))`.
    * [x] Al presionar «Evaluación» en actividad formativa en la agenda docente, no se abre ningún slideover de rúbrica ni se solicita nota numérica 1–7.
    * [x] Se despliega la consulta «¿Qué opina del trabajo?» con 3 opciones seleccionables: Bueno (verde / emerald), Regular (naranja claro / amber) y Malo (rojo / rose).
    * [x] El campo de texto de mensaje permite redacción extensa («puede ser largo») como evaluación final.
    * [x] El botón «Enviar» se habilita cuando se selecciona una opinión y se redacta el mensaje.
    * [x] En backend, `storeEvaluacion` valida `evaluacion_obtenida` ('Bueno', 'Regular', 'Malo'), flexibiliza `id_rubrica` (nullable para formativas) y persiste el registro en `agenda.agenda` y `agenda.evaluacion`.

### Release 3: Visualización de Evaluación Formativa y Repurpose de Tarjeta de Nota en Estudiante
*Rama sugerida:* `feature/formativas-vista-estudiante`
*Puntos estimados:* 6 pts

* ### `[UI-T05]` [3 pts] [R3] [P0] Hilo de Agenda — Renderizado de mensaje de evaluación formativa como información importante
  * **Ruta**: `/estudiante/cursos/{id}/actividades/{act}` (Modal de Agenda) y `/docente/cursos/{id}/actividades/{act}`
  * **Archivos**:
    * `resources/js/pages/student/Activities/Agenda/AgendaHilo.svelte`
  * **Problema & Causa**: Los mensajes de evaluación en `AgendaHilo.svelte` asumían que toda evaluación adjunta rúbrica o nota numérica, mostrando llamadas rotas a modales de rúbrica.
  * **Criterios de Aceptación (DoD)**:
    * [x] En actividades formativas, las interacciones de tipo `Evaluación` se destacan visualmente como «Evaluación final / Información importante».
    * [x] Se muestra el badge cualitativo con su color semántico: Bueno (verde), Regular (naranja claro), Malo (rojo).
    * [x] Se renderiza el cuerpo completo del mensaje del docente sin requerir apertura de modal de rúbrica.
    * [x] Se suprime todo botón o enlace a rúbrica para este tipo de interacción formativa.

* ### `[FEAT-T06]` [3 pts] [R3] [P0] Vista Principal Actividad — Repurposear tarjeta de nota para mostrar mensaje de evaluación formativa (Sistema A)
  * **Ruta**: `/estudiante/cursos/{id}/actividades/{act}`
  * **Archivos**:
    * `resources/js/pages/student/Activities/cards/ActivityGradeCard.svelte`
    * `resources/js/pages/student/Activities/Index.svelte`
    * `app/Http/Controllers/Student/ActivityController.php`
  * **Problema & Causa**: `ActivityGradeCard.svelte` sólo soportaba notas numéricas y se ocultaba cuando `ultima_nota` era nula (`Index.svelte:298`). En formativas debe mostrar el mensaje de evaluación y la apreciación emitida bajo la regla de Sistema A (último mensaje cronológico emitido).
  * **Criterios de Aceptación (DoD)**:
    * [x] En actividades formativas que cuentan con evaluación emitida, la tarjeta de la columna lateral se renderiza activamente.
    * [x] Muestra el badge de apreciación (Bueno / Regular / Malo) con sus estilos semánticos correspondientes.
    * [x] Renderiza el mensaje de evaluación provisto por el docente bajo el Sistema A (la última evaluación emitida cronológicamente en el grupo).
    * [x] Muestra fecha de emisión y nombre del docente evaluador.
    * [x] Oculta la nota numérica (1,0 - 7,0), leyendas de aprobación y botón de rúbrica.
    * [x] La estructura de datos desacopla el render del origen de selección, dejando la tarjeta lista para Sistema B sin cambios visuales si se implementa en el futuro.

---

## 4. Métricas de Planificación

* **Total de tarjetas**: 6 tarjetas.
* **Puntos por Release**:
  * **Release 1** (Corrección de Dashboard y Entregas): 5 pts.
  * **Release 2** (Desacople de Rúbricas y Evaluación Docente): 7 pts.
  * **Release 3** (Visualización Estudiante y Repurpose de Tarjeta): 6 pts.
* **Total de puntos de historia estimados**: 18 pts.
