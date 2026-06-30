# Auditoría de buenas prácticas — `resources/js/pages/docente/`

**Fecha:** 2026-06-23
**Alcance:** `resources/js/pages/docente/**` (24 archivos, ~11.5k líneas) y sus recursos compartidos.
**Estado:** Borrador para acción. Pensado para que otra IA / desarrollador tome cada hallazgo como tarea independiente.

## Cómo usar este documento

Cada hallazgo tiene:
- **ID** estable (ej. `D-01`) para referenciarlo en commits/PRs.
- **Severidad**: 🔴 Alta · 🟠 Media · 🟢 Baja.
- **Evidencia** con `archivo:línea`.
- **Acción propuesta** concreta y autocontenida.

Antes de tocar cualquier endpoint, leer el ADR del proyecto: [`docs/0001-uso-de-inertia-sobre-rest.md`](../0001-uso-de-inertia-sobre-rest.md) y las notas de [`docs/sobre_svelte+inertiajs/`](../sobre_svelte+inertiajs/).

---

## Resumen ejecutivo

La carpeta es funcional y en varios archivos tiene **buena documentación de cabecera** (`Mensajes.svelte`, `Calificaciones.svelte`, `CursoDetalle.svelte`). Los problemas no son de "no funciona", sino de **mantenibilidad**:

1. **Código muerto**: `ActividadEvaluacion.svelte` (1153 líneas) no lo renderiza ningún controlador.
2. **Lógica de negocio duplicada y ya divergente**: la fórmula de nota chilena existe en 2 archivos con implementaciones distintas.
3. **Violación de una convención documentada (ADR-0001)**: uso de `fetch()` crudo donde el proyecto exige Inertia.
4. **Helpers reinventados** que ya existen en `resources/js/utils/formatters.ts`.
5. **Componentes monolíticos** (>1000 líneas) que mezclan varias responsabilidades.

Prioridad sugerida: **D-01 → D-02 → D-03 → D-04** (bajo esfuerzo / alto impacto) antes de los refactors estructurales (D-05+).

---

## 🔴 Hallazgos de severidad alta

### D-01 — Eliminar componente huérfano `ActividadEvaluacion.svelte`
**Severidad:** 🔴 · **Esfuerzo:** Bajo

`ActividadEvaluacion.svelte` (1153 líneas) **no es renderizado por ningún controlador**. El componente vivo para evaluar grupos de una actividad es `Activities/Index.svelte`, renderizado en `DocenteActivityController.php:438`.

**Evidencia:**
- Búsqueda de `Inertia::render('docente/ActividadEvaluacion'...)` → 0 resultados.
- Única referencia: un comentario obsoleto en `app/Http/Controllers/Docente/DocenteActivityController.php:1518` (`Usado en el panel de mensajes de la vista ActividadEvaluacion`).
- `ActividadEvaluacion.svelte` y `Activities/Index.svelte` resuelven el **mismo problema** (CRUD de grupos, notas, décimas, entregas, feedback) con UIs distintas.

**Acción:**
1. Confirmar con `git log --oneline -- resources/js/pages/docente/ActividadEvaluacion.svelte` que está congelado.
2. Eliminar `ActividadEvaluacion.svelte`.
3. Corregir/actualizar el comentario en `DocenteActivityController.php:1518` para que apunte a `Activities/Index.svelte`.
4. **Antes de borrar**, verificar si tiene alguna idea de UX superior a `Index.svelte` (p.ej. el `confirmDialog` accesible de las líneas 1108-1152, ver D-09) y rescatarla.

---

### D-02 — Lógica de negocio duplicada y divergente: `calcularNota()`
**Severidad:** 🔴 · **Esfuerzo:** Bajo

La **fórmula de conversión puntaje→nota chilena (1.0 / 4.0 al 60% / 7.0)** está copiada en dos archivos, y **ya divergieron**:

| Archivo | Firma | Exigencia |
|---|---|---|
| `Activities/MatrizEvaluacion.svelte:71` | `calcularNota(puntaje, total)` | `0.6` **hardcodeado** |
| `Activities/Agenda/AgendaDocente.svelte:100` | `calcularNota(puntaje, total, exigencia = 60)` | parametrizable |

Si un día se cambia la exigencia (ej. 50%) en una pantalla, la otra calculará una nota distinta para la misma entrega. Es un bug silencioso esperando a ocurrir, y afecta **notas reales de estudiantes**.

**Acción:**
1. Crear `resources/js/lib/notas.ts` (o `utils/notas.ts`) con una única `calcularNotaChilena(puntaje, total, exigencia = 60)` documentada.
2. Reemplazar ambas definiciones por el import.
3. Añadir un test unitario mínimo (casos: 0%, 60%, 100%, total=0) — es la pieza con mayor consecuencia del módulo.
4. Verificar que el backend usa la **misma** fórmula al persistir; si no, documentar cuál es la fuente de verdad.

---

### D-03 — Uso de `fetch()` crudo viola el ADR-0001 (Inertia sobre REST)
**Severidad:** 🔴 · **Esfuerzo:** Medio

El [ADR-0001](../0001-uso-de-inertia-sobre-rest.md) es explícito: *"Svelte se comunicará exclusivamente usando los helpers `@inertiajs/svelte`… los desarrolladores deben desaprender el uso de `fetch`/`axios`"*. Sin embargo hay `fetch()` directo:

- `Activities/Index.svelte:281` (`verEntregas`) — GET de entregas vía fetch.
- `Activities/Index.svelte` ya documenta en líneas 369-372 una migración parcial de `fetch()` → `router.post()`, pero **dejó los GET sin migrar**: inconsistencia a medias.
- `components/AsistenciaPanel.svelte` — 2 llamadas `fetch()`.
- `ActividadEvaluacion.svelte` — 11 ocurrencias (se va con D-01, pero confirma el patrón).
- Síntoma asociado: helpers `csrfToken()` manuales en `ActividadEvaluacion.svelte:407` y `MensajesCurso.svelte:130` (Inertia gestiona el CSRF automáticamente).

**Acción:**
1. Definir y **documentar** la regla real del equipo. Dos opciones:
   - **(a) Estricta:** migrar los GET a `router.reload({ only: [...] })` con props parciales (lo que ya hace `Index.svelte:cargarInteracciones`).
   - **(b) Excepción acotada:** permitir `fetch` **solo** para GET de datos auxiliares cargados de forma diferida (lazy), y prohibirlo en mutaciones. Si se elige esto, **añadir la excepción al ADR-0001** para que deje de ser una violación.
2. Eliminar los helpers `csrfToken()` manuales una vez migrado.
3. Unificar el manejo de errores: hoy conviven `try/catch` (fetch) y callbacks `onError` (router).

---

## 🟠 Hallazgos de severidad media

### D-04 — Helpers de formato reinventados (ya existen en `utils/formatters.ts`)
**Severidad:** 🟠 · **Esfuerzo:** Bajo-Medio

`resources/js/utils/formatters.ts` ya exporta `formatDate`, `formatDateTime`, `getEstadoColorClass`, `truncate`, etc. Aun así, la carpeta `docente/` los **re-implementa localmente y con criterios inconsistentes** (locale `es-CL` vs `es-ES`, formatos distintos):

**`formatDate` / `formatFecha` / `formatDeadline` duplicados en ≥8 archivos:**
`Calificaciones.svelte:107`, `CursoDetalle.svelte:209`, `Programa.svelte:154,197`, `Activities/Index.svelte:322`, `ActividadEvaluacion.svelte:139`, `components/AsistenciaPanel.svelte:224`, `components/ActividadesPorEstado.svelte:53`, `components/EstudianteDetalleModal.svelte:140`.

**`initials(name)` duplicado en 4 archivos:**
`CursoDetalle.svelte:219`, `EstudiantesGestion.svelte:137`, `components/EstudianteDetalleModal.svelte:131`, `MensajesCurso.svelte:121`.

**Otros:** `formatBytes` (`Index:311`, `ActividadEvaluacion:392`), `downloadUrl` (`Index:318`, `ActividadEvaluacion:399`), `getEstadoColor` local (`Index:421`) vs `getEstadoColorClass` en formatters.

**Acción:**
1. Centralizar en `utils/formatters.ts` (o `utils/docente-formatters.ts`): `formatFechaLarga`, `formatFechaCorta`, `initials`, `formatBytes`. **Cuidado con el locale**: decidir `es-CL` como estándar del producto y unificar (hoy hay mezcla `es-CL`/`es-ES`).
2. Reemplazar las copias locales por imports.
3. Atención a `Programa.svelte:148` (`parseDeadlineDate`): tiene lógica **correcta y no trivial** de evitar el desfase UTC→hora local de Chile. Esa versión debería ser la canónica para fechas-solo-día; documentarlo al centralizar.

---

### D-05 — `Activities/Index.svelte` (1172 líneas) hace demasiado
**Severidad:** 🟠 · **Esfuerzo:** Alto

Es el archivo vivo más grande y **no tiene comentario de cabecera** que explique su rol (contrasta con `Mensajes.svelte`/`Calificaciones.svelte`). Mezcla en un solo componente:
- Gestión de grupos (crear/eliminar/agregar/quitar integrantes).
- Notas individuales y ajuste de décimas.
- Modal de entregas con su propio `fetch`.
- Modal "nuevo grupo".
- Orquestación de Agenda, Rúbrica y Matriz de evaluación.

**Acción (incremental, sin romper):**
1. Añadir cabecera JSDoc describiendo responsabilidad y props de Inertia.
2. Extraer subcomponentes a `Activities/components/`:
   - `GrupoCard.svelte` (tarjeta de grupo + integrantes + décimas, líneas ~522-760).
   - `NuevoGrupoModal.svelte` (líneas ~790-871).
   - `EntregasModal.svelte` (líneas ~925-1116, incluye su carga de datos).
3. Función muerta: `handleSubirArchivo` en `Index.svelte:429` es un cuerpo vacío `{}` — eliminar o implementar.
4. CSS muerto/erróneo: la animación `slide-in` (líneas 1156-1171) tiene `transform: translateY(100)` sin unidad (inválido); revisar o eliminar.

---

### D-06 — `CursoDetalle.svelte` (1076 líneas): tabla de estudiantes duplicada
**Severidad:** 🟠 · **Esfuerzo:** Medio

La rama "titular" y la rama "colegiado" repiten **casi literalmente** la tabla de estudiantes (avatar + nombre + usuario + nota):
- Vista titular: `CursoDetalle.svelte:566-659`.
- Vista colegiado: `CursoDetalle.svelte:926-998`.

Cualquier cambio de columna/estilo hay que hacerlo dos veces.

**Acción:**
1. Extraer `components/EstudiantesTable.svelte` con props (`estudiantes`, `mostrarDetalle?`, `onDetalle?`).
2. Usarlo en ambas ramas.
3. De paso, los "component pills" (selector de componente) también están duplicados ~3 veces en este archivo; extraer `ComponentePills.svelte`.

---

### D-07 — `Programa.svelte` (786 líneas) es una "página de 4 roles" con casts `as any`
**Severidad:** 🟠 · **Esfuerzo:** Medio-Alto

`Programa.svelte` resuelve la vista de programa para **docente, ayudante, estudiante y admin** vía `layoutType` y numerosas ramas. Vive en `pages/docente/` pero lo renderizan controladores de Ayudante/Administrativo/Admin (`ProgramaController` × 3). Esto explica el peso, pero genera:
- **Type-safety pobre:** `programa: any` (prop), y `(curso as any).campo` repetido en muchas líneas (`88-100`, `184-186`, `213-218`, `240-241`, `446-468`).

**Acción:**
1. Tipar `programa` y los campos `fecha_limite_entrega_basico/syllabus` en `@/types/admin.types` y eliminar los `as any`.
2. Evaluar mover el componente a una ubicación neutra (ej. `resources/js/modules/resources/programa/`) ya que **no es exclusivo de docente** — su ubicación actual es engañosa.
3. Si las ramas admin vs docente crecen más, considerar separar `ProgramaAdmin` (aprobación/fechas) de `ProgramaDocente` (edición), compartiendo `ProgramaDocument`.

---

## 🟢 Hallazgos de severidad baja (pulido)

### D-08 — Documentación de cabecera inconsistente
**Severidad:** 🟢

Algunos archivos tienen excelente JSDoc de cabecera (`Mensajes.svelte:2-16`, `Calificaciones.svelte:2-13`, `CursoDetalle.svelte:1-9`), otros de tamaño comparable no tienen ninguna (`Activities/Index.svelte`, `Activities/Agenda/AgendaDocente.svelte`).

**Acción:** estandarizar una cabecera mínima (propósito + props de Inertia + endpoints) en todos los componentes `pages/`.

### D-09 — Patrón de confirmación inconsistente (`confirm()` nativo vs modal)
**Severidad:** 🟢

`Activities/Index.svelte:170,177` usa `window.confirm()` para acciones destructivas (eliminar grupo / quitar estudiante), mientras que `ActividadEvaluacion.svelte:1108-1152` tiene un modal de confirmación accesible (`role="alertdialog"`, `aria-modal`).

**Acción:** extraer un `ConfirmDialog.svelte` compartido (rescatando el de `ActividadEvaluacion` antes de borrarlo en D-01) y reemplazar los `confirm()` nativos.

### D-10 — Comentarios "Autor/Fecha" embebidos duplican el historial de git
**Severidad:** 🟢

Bloques tipo `// 1. Autor: Juan Y.  // 2. Fecha: 04/06/2025  // 3. ...` en `Activities/Index.svelte:21-24, 69-72, 369-372`. Git ya registra autoría y fecha; estos sellos envejecen y ensucian.

**Acción:** mantener el *qué/por qué* del comentario, eliminar el *quién/cuándo*. Confiar en `git blame`.

### D-11 — Estrategia de iconos inconsistente
**Severidad:** 🟢

El proyecto usa `lucide-svelte`, pero hay SVGs inline hardcodeados mezclados: `Activities/Index.svelte:744-757, 1107-1109` y todo `AgendaDocente.svelte` (íconos de cerrar/enviar/check en SVG crudo).

**Acción:** sustituir SVGs inline por componentes de `lucide-svelte` ya importados (`Calendar`, `Send`, `X`, `CheckCircle2`, etc.) para coherencia y menos ruido en el markup.

---

## Tabla de seguimiento

| ID | Hallazgo | Sev | Esfuerzo | Archivos clave |
|----|----------|-----|----------|----------------|
| D-01 | Borrar `ActividadEvaluacion.svelte` huérfano | 🔴 | Bajo | `ActividadEvaluacion.svelte`, `DocenteActivityController.php:1518` |
| D-02 | Unificar `calcularNota()` chilena | 🔴 | Bajo | `MatrizEvaluacion.svelte:71`, `AgendaDocente.svelte:100` |
| D-03 | `fetch()` viola ADR-0001 | 🔴 | Medio | `Index.svelte:281`, `AsistenciaPanel.svelte` |
| D-04 | Helpers de formato duplicados | 🟠 | Bajo-Medio | 8+ archivos · `utils/formatters.ts` |
| D-05 | `Index.svelte` monolítico | 🟠 | Alto | `Activities/Index.svelte` |
| D-06 | Tabla de estudiantes duplicada | 🟠 | Medio | `CursoDetalle.svelte:566,926` |
| D-07 | `Programa.svelte` 4 roles + `as any` | 🟠 | Medio-Alto | `Programa.svelte` |
| D-08 | Cabeceras JSDoc inconsistentes | 🟢 | Bajo | varios |
| D-09 | `confirm()` vs modal | 🟢 | Bajo | `Index.svelte:170` |
| D-10 | Comentarios Autor/Fecha | 🟢 | Bajo | `Index.svelte:21` |
| D-11 | SVG inline vs lucide | 🟢 | Bajo | `Index.svelte`, `AgendaDocente.svelte` |

## Notas para quien implemente

- **No hacer "big bang".** Cada ID es un PR pequeño y verificable.
- **D-02 es el de mayor riesgo real** (afecta notas); priorizar y testear.
- Tras D-01 y D-04, la carpeta baja de ~11.5k a ~10k líneas sin perder funcionalidad.
- Respetar el patrón Inertia: ante la duda, mirar cómo `Mensajes.svelte` carga props parciales con `router.reload({ only: [...] })`.
