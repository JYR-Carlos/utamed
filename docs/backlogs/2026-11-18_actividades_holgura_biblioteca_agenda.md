# Planificación: Unificación de Actividades, Holgura por Fecha, Biblioteca Docente y Retroalimentación en Agenda

Fecha: 2026-11-18
Rama base: `main`

---

## 1. Observaciones

### 1.1. Gestión de Cursos (Docente) — Unificación de estados de actividades con gestión de actividades
* **Elemento / Contexto**: Vista de detalle de curso docente (`/docente/cursos/{curso}`, `resources/js/pages/docente/CursoDetalle.svelte`) y vista dedicada de actividades (`/docente/cursos/{curso}/actividades`, `resources/js/pages/docente/Actividades.svelte`).
* **Problema reportado**: En la gestión del curso como docente, la pestaña de actividades muestra los estados de las actividades separado de la pantalla de "Gestionar actividades"; se solicita unificar todo en la misma vista de actividades dentro de la gestión del curso.
* **Evidencia en código**:
  * `resources/js/pages/docente/CursoDetalle.svelte` (líneas 663-724): la pestaña `mainTab === 'actividades'` renderiza únicamente `<ActividadesPorEstado>` y un botón "Gestionar actividades" que redirige mediante `router.visit('/docente/cursos/${curso.id_curso}/actividades')`.
  * `resources/js/pages/docente/Actividades.svelte` (líneas 799-834): la vista dedicada ya contiene el selector `vista === 'lista' | 'estados'` (`<ActividadesTabla>` y `<ActividadesPorEstado>`), filtros, creación/edición (`<ActividadForm>`), eliminación (`<ActividadEliminarDialog>`), toggle de visibilidad y copia de actividades (`<CopiarActividadesModal>`).
  * `app/Http/Controllers/Docente/DocenteCursoController.php` (líneas 295-331): `CursoDetalle` recibe un payload simplificado de `actividades` sin `nro_dias_adicionales_para_bloqueo`, `unidades`, ni permisos granulares de creación/edición/eliminación que sí carga `DocenteActivityController::index`.
* **Nota de corrección**: El usuario indicó estados "(planificado, activa, terminada)", pero en el código (`resources/js/utils/actividadEstado.ts` y `resources/js/pages/docente/components/ActividadesPorEstado.svelte`) el tercer estado corresponde a `cerrada` ("Cerradas"), derivado de `agenda.estado_actividad_asignada`.

### 1.2. Actividades y Evaluación — Cálculo de días de holgura seleccionando la fecha (grupal e individual)
* **Elemento / Contexto**: Formulario de actividad (`resources/js/modules/resources/actividad/components/actividadForm.svelte`) y gestión de holgura personal en evaluación (`resources/js/pages/docente/Activities/components/GrupoCard.svelte` y `EstudiantesTabla.svelte`).
* **Problema reportado**: Actualmente el docente debe ingresar manualmente un número entero de días de holgura (`nro_dias_adicionales_para_bloqueo` y `nro_dias_adicionales_para_bloqueo_personal`). Se solicita calcular los días de holgura automáticamente a partir de seleccionar una fecha límite extendida en calendario, tanto a nivel de actividad como a nivel grupal/individual.
* **Evidencia en código**:
  * `resources/js/modules/resources/actividad/components/actividadForm.svelte` (líneas 287-301): input numérico `nro_dias_adicionales_para_bloqueo` (`min="0"`).
  * `resources/js/pages/docente/Activities/components/GrupoCard.svelte` (líneas 190-229): input numérico `holguraTemp` en días para `nro_dias_adicionales_para_bloqueo_personal` enviado por `DocenteActivityController::updateGrupo` (líneas 1443-1461).
  * `resources/js/pages/docente/Activities/components/EstudiantesTabla.svelte` (líneas 189-205, 341-347): edición de `nro_dias_adicionales_para_bloqueo_personal` por estudiante en actividades individuales.
  * `app/Http/Controllers/Docente/DocenteActivityController.php` (líneas 354, 420, 1450): valida `nro_dias_adicionales_para_bloqueo` y `nro_dias_adicionales_para_bloqueo_personal` como `nullable|integer|min:0`.

### 1.3. Agenda — Agregar tipo de mensaje para registro de holgura otorgada
* **Elemento / Contexto**: Enum de tipos de mensaje en PostgreSQL/Laravel (`app/Enums/DB/TipoMensaje.php`) y renderizado del hilo de agenda (`resources/js/pages/student/Activities/Agenda/AgendaHilo.svelte`, `AgendaHiloAlumno.svelte`, `app/Services/Docente/ConversacionDocenteService.php`).
* **Problema reportado**: Se solicita agregar un tipo específico en la agenda para registrar y distinguir cuándo se otorgó más tiempo (holgura) desde la agenda.
* **Evidencia en código**:
  * `app/Enums/DB/TipoMensaje.php` (líneas 11-21): enum `TipoMensaje` con 8 casos (`MENSAJE_AL_PROFESOR`, `FEEDBACK`, `ENTREGA_DE_ARCHIVO`, `CANCELACIÓN_DE_ENTREGA`, `EVALUACIÓN`, `CIERRE_DE_ACTIVIDAD`, `SOLICITUD_DE_APELACIÓN`, `RESOLUCIÓN_DE_APELACIÓN`), generado desde el ENUM `agenda.en_tipo_mensaje` en PostgreSQL.
  * `database/migrations/09_add_apelacion_to_en_tipo_mensaje.php` (líneas 20-21): patrón existente para extender el ENUM mediante `ALTER TYPE agenda.en_tipo_mensaje ADD VALUE IF NOT EXISTS ...`.
  * `app/Services/Docente/ConversacionDocenteService.php` (líneas 24-40): lista blanca de `tipo_mensaje` visibles en el hilo.
* **Nota de corrección**: El usuario indicó `TIPO_AGENDA.holgura`, pero en el código el tipo de registro de `agenda.agenda` corresponde al enum `App\Enums\DB\TipoMensaje` (columna `tipo_mensaje` de tipo `agenda.en_tipo_mensaje`).

### 1.4. Gestión de Cursos (Docente) — Pestaña dedicada de Biblioteca (Bibliografía) aparte del Syllabus
* **Elemento / Contexto**: Ficha de curso docente (`resources/js/pages/docente/CursoDetalle.svelte`) en contraste con la ficha de curso estudiante (`resources/js/pages/student/Courses/Show.svelte`).
* **Problema reportado**: Se solicita añadir una pestaña dedicada de Biblioteca (bibliografía) en la gestión del curso docente, separada del editor de Syllabus.
* **Evidencia en código**:
  * `resources/js/pages/docente/CursoDetalle.svelte` (líneas 482-529): solo define 3 pestañas (`grupo`, `actividades`, `asistencia`). La bibliografía únicamente es accesible abriendo el wizard completo de Syllabus (`resources/js/pages/docente/SyllabusWizard.svelte`, sección VIII, líneas 197-215, 761-788) o mediante `BibliografiaModal.svelte`.
  * `resources/js/pages/student/Courses/Show.svelte` (líneas 313-321) y `resources/js/pages/student/Courses/components/CursoBiblioteca.svelte`: el portal de estudiante ya cuenta con una pestaña dedicada `Biblioteca` que agrupa y lista la bibliografía del programa.
  * `app/Http/Controllers/BibliografiaController.php`: ya expone endpoints para visualizar/descargar archivos y gestionar bibliografía asociada al programa.

### 1.5. Evaluación de Grupos / Agenda — Retroalimentación automática en agenda al ajustar décimas de un integrante
* **Elemento / Contexto**: Ajuste de décimas de integrantes (`app/Http/Controllers/Docente/DocenteActivityController.php::updateIntegrante`, `resources/js/pages/docente/Activities/Index.svelte`, `resources/js/pages/docente/Activities/components/GrupoCard.svelte`).
* **Problema reportado**: Cuando el docente sube o baja décimas (`diferencia_decimas`) a un integrante de un grupo, actualmente solo se actualiza la base de datos sin dejar constancia automática en la agenda. Se solicita activar automáticamente un registro de retroalimentación en la agenda al modificar las décimas.
* **Evidencia en código**:
  * `app/Http/Controllers/Docente/DocenteActivityController.php` (líneas 1371-1406): `updateIntegrante` valida `diferencia_decimas`, calcula `nota_individual`, actualiza `agenda.integrante_grupo` y retorna `redirect()->back()->with('success', $mensaje)` sin insertar ningún registro en `agenda.agenda`.
  * `resources/js/pages/docente/Activities/components/GrupoCard.svelte` (líneas 269-286, 346-363) y `resources/js/pages/docente/Activities/Index.svelte` (líneas 384-404): cada clic en los botones `-` / `+` de décimas dispara inmediatamente una petición `router.patch` a `updateIntegrante` en pasos de `0.1`.
  * `app/Http/Controllers/Docente/DocenteActivityController.php` (líneas 2233-2239): referencia de inserción en `agenda.agenda` con `tipo_mensaje => TipoMensaje::FEEDBACK->value`.

---

## 2. Interpretación e Investigación

### Diagnóstico de causa raíz ("Lo que no se vio")

1. **Fragmentación de la vista de actividades del docente**:
   La existencia de dos pantallas separadas (`CursoDetalle.svelte` con pestaña `actividades` de solo lectura/kanban y `Actividades.svelte` con CRUD completo y vista lista/estados) responde a una evolución incremental del portal docente donde la gestión CRUD se construyó como página aislada y luego se incrustó un resumen Kanban en `CursoDetalle`. Como consecuencia, el docente pierde el contexto de las demás pestañas del curso (Estudiantes, Asistencia) cada vez que necesita crear, editar, ocultar o copiar una actividad. Adicionalmente, `DocenteCursoController::show` y `DocenteActivityController::index` duplican la serialización de actividades con distintos niveles de detalle.

2. **Modelo de holgura basado en enteros vs. modelo mental basado en fechas**:
   En PostgreSQL (`agenda.actividad.nro_dias_adicionales_para_bloqueo` y `agenda.actividad_asignada_grupo.nro_dias_adicionales_para_bloqueo_personal`) la holgura se almacena como cantidad entera de días adicionales sobre `fecha_limite`. Sin embargo, el docente razona en fechas calendario ("dar plazo hasta el viernes 20"). Traducir la fecha seleccionada en un date-picker a días de diferencia (`diffDays` en zona horaria `America/Santiago` mediante `cierreEnChile`) permite ofrecer la UX deseada sin alterar el esquema de base de datos ni romper los cálculos existentes en `ActividadAsignadaGrupo::calcularEstadoGrupo`, `ResumenActividadesEstudiante` y `Student\AgendaController`. Para holgura personal (grupal o individual), la fecha base debe considerar `fecha_limite + nro_dias_adicionales_para_bloqueo` (holgura general) para que el delta personal no se solape con la holgura base de la actividad.

3. **Trazabilidad incompleta en el hilo de Agenda (`agenda.agenda`)**:
   La tabla `agenda.agenda` actúa como bitácora unificada de la actividad (mensajes, entregas, cancelaciones, evaluaciones, cierres y apelaciones), filtrada explícitamente mediante listas blancas en `ConversacionDocenteService` y el enum PostgreSQL `agenda.en_tipo_mensaje` (`App\Enums\DB\TipoMensaje`). Hoy las acciones administrativas del docente sobre plazos (holgura personal) y ajustes individuales de nota (`diferencia_decimas`) ocurren de forma silenciosa en otras tablas (`actividad_asignada_grupo` e `integrante_grupo`), dejando al estudiante sin registro ni notificación en su hilo de agenda.
   * **Riesgo operativo en ajuste de décimas**: Dado que `GrupoCard.svelte` dispara una petición `PATCH` por cada clic de `0.1`, insertar un mensaje de feedback en cada petición generaría múltiples mensajes consecutivos ("spam") si el docente ajusta varias décimas seguidas. El diseño de la implementación debe contemplar consolidación (ej. debounce/confirmación o control de edición antes de persistir) para emitir un único evento coherente en `agenda.agenda`.

4. **Acoplamiento entre Bibliografía y Syllabus**:
   La bibliografía del curso vive sincronizada entre `curso.bibliografia` y la sección VIII del JSONB `administrativo.programa.data_syllabus`. Mientras el portal estudiante (`CursoBiblioteca.svelte`) ya desacopló la lectura de bibliografía en una pestaña propia, el portal docente mantiene la bibliografía encapsulada dentro del wizard de 9 pasos (`SyllabusWizard.svelte`), obligando a navegar fuera de `CursoDetalle` para consultarla o editarla.

*(Nota de alcance: No se generan tarjetas adicionales para refactorizaciones macro como unificar todos los presentadores de `DocenteCursoController` y `DocenteActivityController`, ni para rediseñar el esquema de `nro_dias_adicionales_para_bloqueo` en BD; las tarjetas de la Fase 3 se acotan estrictamente a los 5 requerimientos solicitados.)*

---

## 3. Planificación (Releases y Tablero Kanban)

### Releases Temáticas y Ramas Git Sugeridas

#### Release 1 (R1): Unificación de Gestión de Actividades en Curso Docente
```bash
git checkout main
git pull origin main
git checkout -b feature/unificar-actividades-curso-docente
```

#### Release 2 (R2): Holgura por Fecha y Trazabilidad en Agenda
```bash
git checkout main
git pull origin main
git checkout -b feature/holgura-fecha-y-tipo-agenda
```

#### Release 3 (R3): Retroalimentación Automática de Décimas y Biblioteca Docente
```bash
git checkout main
git pull origin main
git checkout -b feature/feedback-decimas-y-biblioteca-docente
```

---

### Tablero Kanban

* ### `FEAT-07` [3 pts] [R1] [ALTA] Unificar estados y gestión de actividades dentro de la vista Actividades (sin alterar controlador de curso)
  * **Ruta**: `/docente/cursos/{curso}/actividades` (y limpieza de vista separada en `/docente/cursos/{curso}`)
  * **Archivos**:
    * `resources/js/pages/docente/Actividades.svelte`
    * `resources/js/pages/docente/CursoDetalle.svelte`
    * `resources/js/components/docente/ActividadesTabla.svelte`
    * `resources/js/pages/docente/components/ActividadesPorEstado.svelte`
  * **Problema & Causa**: En `CursoDetalle.svelte`, la pestaña `actividades` renderiza `<ActividadesPorEstado>` (Planificadas, Activas, Cerradas) de forma aislada respecto a la gestión CRUD en `/docente/cursos/{curso}/actividades` (`Actividades.svelte`). Se define consolidar toda la experiencia de estados y gestión dentro de `Actividades.svelte` sin modificar el payload ni las consultas de `DocenteCursoController::show`.
  * **Criterios de Aceptación (DoD)**:
    * [ ] `DocenteCursoController::show` permanece intacto: no se agregan nuevas consultas ni props (`unidades`, permisos de actividades, etc.) al controlador del curso.
    * [ ] Toda la visualización por estados (`Planificadas`, `Activas`, `Cerradas` vía `<ActividadesPorEstado>`) y las acciones de gestión (`Lista`, filtros, crear, editar, eliminar, cambiar visibilidad, copiar actividades y ver enunciado) quedan unificadas en `Actividades.svelte` (`/docente/cursos/{curso}/actividades`).
    * [ ] En `CursoDetalle.svelte`, la sección/pestaña de actividades se mantiene en el código como componente desconectado, pero queda oculta provisoriamente (sin mostrarse en la interfaz), dejando un acceso directo claro hacia la vista unificada de `Actividades` mientras se define si se elimina definitivamente.
    * [ ] Desde la vista de `Actividades`, el docente puede alternar entre estados y gestión/lista sin perder filtros ni estado reactivo tras operaciones CRUD o toggles de visibilidad.

* ### `FEAT-08` [3 pts] [R2] [MEDIA] Calcular días de holgura seleccionando la fecha límite extendida (actividad, grupal e individual)
  * **Ruta**: `/docente/cursos/{curso}` y `/docente/cursos/{curso}/actividades/{actividad}`
  * **Archivos**:
    * `resources/js/modules/resources/actividad/components/actividadForm.svelte`
    * `resources/js/pages/docente/Activities/components/GrupoCard.svelte`
    * `resources/js/pages/docente/Activities/components/EstudiantesTabla.svelte`
    * `resources/js/utils/plazo.ts`
  * **Problema & Causa**: Actualmente tanto la holgura general (`nro_dias_adicionales_para_bloqueo` en `actividadForm.svelte`) como la holgura personal grupal/individual (`nro_dias_adicionales_para_bloqueo_personal` en `GrupoCard.svelte` y `EstudiantesTabla.svelte`) se ingresan como un número entero manual de días, obligando al docente a calcular mentalmente las fechas.
  * **Criterios de Aceptación (DoD)**:
    * [ ] `resources/js/utils/plazo.ts` centraliza helpers de calendario puros (`YYYY-MM-DD`, sin desfases UTC/DST) para sumar días a una fecha y calcular la diferencia en días enteros entre dos fechas.
    * [ ] En `actividadForm.svelte`, el input numérico de holgura se reemplaza por un `<input type="date">` (con `min={formData.fecha_limite}`) que calcula automáticamente `nro_dias_adicionales_para_bloqueo`; si no hay `fecha_limite` definida, queda deshabilitado con `0` días.
    * [ ] En `GrupoCard.svelte` (grupal) y `EstudiantesTabla.svelte` (individual), el input numérico de holgura personal se reemplaza por un `<input type="date">` cuyo mínimo y base de cálculo es la fecha límite efectiva de la actividad (`fecha_limite + nro_dias_adicionales_para_bloqueo`), calculando automáticamente `nro_dias_adicionales_para_bloqueo_personal`.
    * [ ] La fecha de holgura seleccionada no puede ser menor a la fecha límite correspondiente (`fecha_limite` en actividad; `fecha_limite + nro_dias_adicionales_para_bloqueo` en holgura personal): se restringe con `min` en el selector y se valida/clampea el cálculo para que nunca resulte en días negativos (`>= 0`), incluso ante edición manual o cambios posteriores en `fecha_limite`.
    * [ ] La UI se mantiene sobria y concisa: sin textos verbosos, cards ni pastillas innecesarias; el número de días que se guardará en BD se muestra de forma discreta junto al selector (ej. `+N días`).
    * [ ] Los endpoints backend (`store`, `update`, `updateGrupo`) siguen recibiendo y validando los enteros `>= 0` sin romper el cálculo de plazos en alumno ni docente.

* ### `FEAT-09` [5 pts] [R2] [MEDIA] Agregar TipoMensaje::HOLGURA para registrar ampliaciones de plazo desde agenda
  * **Ruta**: `/docente/cursos/{curso}/actividades/{actividad}` y `/estudiante/actividades/{actividad}`
  * **Archivos**:
    * `database/migrations/` (nueva migración `ALTER TYPE agenda.en_tipo_mensaje`)
    * `app/Enums/DB/TipoMensaje.php`
    * `app/Http/Controllers/Docente/DocenteActivityController.php`
    * `app/Services/Docente/ConversacionDocenteService.php`
    * `app/Services/Student/BitacoraCursoEstudiante.php`
    * `resources/js/pages/student/Activities/Agenda/AgendaHilo.svelte`
    * `resources/js/pages/student/Activities/Agenda/AgendaHiloAlumno.svelte`
  * **Problema & Causa**: El enum PostgreSQL `agenda.en_tipo_mensaje` (`App\Enums\DB\TipoMensaje`) no posee un caso específico para holgura, por lo que otorgar plazo adicional a un grupo o estudiante no deja un evento tipado en el hilo de `agenda.agenda`.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Migración PostgreSQL idempotente que agrega `'Holgura'` a `agenda.en_tipo_mensaje` y actualiza `App\Enums\DB\TipoMensaje` con `case HOLGURA = 'Holgura'`.
    * [ ] Al otorgar o modificar la holgura personal de un grupo/estudiante (desde agenda o desde `updateGrupo` cuando cambia `nro_dias_adicionales_para_bloqueo_personal`), se registra automáticamente una entrada en `agenda.agenda` con `tipo_mensaje = TipoMensaje::HOLGURA->value` indicando los días otorgados y la nueva fecha límite efectiva.
    * [ ] `ConversacionDocenteService::TIPOS_VISIBLES` y `BitacoraCursoEstudiante` incluyen `TipoMensaje::HOLGURA`.
    * [ ] `AgendaHilo.svelte` y `AgendaHiloAlumno.svelte` renderizan el evento `Holgura` con formato visual diferenciado en el hilo (docente y estudiante).

* ### `FEAT-10` [3 pts] [R3] [MEDIA] Retroalimentación automática en agenda al subir o bajar décimas a integrante de grupo
  * **Ruta**: `/docente/cursos/{curso}/actividades/{actividad}`
  * **Archivos**:
    * `app/Http/Controllers/Docente/DocenteActivityController.php`
    * `resources/js/pages/docente/Activities/Index.svelte`
    * `resources/js/pages/docente/Activities/components/GrupoCard.svelte`
  * **Problema & Causa**: `DocenteActivityController::updateIntegrante` actualiza `diferencia_decimas` y `nota_individual` en `agenda.integrante_grupo` sin registrar retroalimentación en `agenda.agenda`. Adicionalmente, los botones `-` / `+` en `GrupoCard.svelte` disparan una petición `PATCH` por cada `0.1`, lo que provocaría múltiples mensajes consecutivos si no se consolida el envío.
  * **Criterios de Aceptación (DoD)**:
    * [ ] Al modificar `diferencia_decimas` de un integrante en `DocenteActivityController::updateIntegrante` (cuando el valor cambia respecto al previo), se inserta automáticamente un registro en `agenda.agenda` con `tipo_mensaje = TipoMensaje::FEEDBACK->value` para ese `id_actividad_asignada_grupo`, indicando el nombre del estudiante, el ajuste aplicado (ej. `+0,3` o `-0,2` décimas) y la nota individual resultante si existe nota grupal.
    * [ ] En frontend (`GrupoCard.svelte` / `Index.svelte`), el ajuste de décimas agrupa o confirma los cambios (debounce o confirmación explícita antes de persistir) para evitar emitir múltiples mensajes en agenda cuando el docente ajusta varias décimas seguidas.
    * [ ] El mensaje generado queda visible en el hilo de agenda tanto para el docente como para el grupo/estudiante.

* ### `FEAT-11` [5 pts] [R3] [BAJA] Añadir pestaña dedicada de Biblioteca (Bibliografía) aparte del Syllabus en curso docente
  * **Ruta**: `/docente/cursos/{curso}`
  * **Archivos**:
    * `app/Http/Controllers/Docente/DocenteCursoController.php`
    * `app/Http/Controllers/BibliografiaController.php`
    * `resources/js/pages/docente/CursoDetalle.svelte`
    * `resources/js/modules/resources/programa/components/BibliografiaModal.svelte`
  * **Problema & Causa**: En `CursoDetalle.svelte`, la bibliografía solo es accesible abriendo el wizard completo de Syllabus (`SyllabusWizard.svelte`, sección VIII), a diferencia del portal estudiante que ya posee una pestaña `Biblioteca` dedicada (`CursoBiblioteca.svelte`).
  * **Criterios de Aceptación (DoD)**:
    * [ ] `CursoDetalle.svelte` incorpora una nueva pestaña `Biblioteca` junto a `Estudiantes`, `Actividades` y `Asistencia`, separada del botón/wizard de `Programa / Syllabus`.
    * [ ] `DocenteCursoController::show` entrega la bibliografía del curso/programa (obligatoria y complementaria, con soporte de archivo adjunto o URL) sin requerir cargar todo el wizard.
    * [ ] La pestaña permite visualizar la bibliografía agrupada y, para docentes con permiso de edición sobre el programa/bibliografía, agregar, editar o eliminar recursos bibliográficos (reutilizando `BibliografiaModal.svelte` / `BibliografiaController`) manteniendo sincronía con la sección VIII del Syllabus cuando exista programa.
    * [ ] Cuando el curso no tiene bibliografía o programa creado, muestra un estado vacío informativo.

---

### Métricas de Planificación

* **Total de tarjetas**: 5
* **Puntos por release**:
  * **R1**: 3 pts (`FEAT-07`)
  * **R2**: 8 pts (`FEAT-08`, `FEAT-09`)
  * **R3**: 8 pts (`FEAT-10`, `FEAT-11`)
* **Total de puntos de historia estimados**: 19 pts
