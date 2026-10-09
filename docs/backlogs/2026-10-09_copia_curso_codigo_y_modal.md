# Backlog: Copia de cursos (modal de previsualización y definición de código de curso)

Fecha: 2026-10-09
Rama sugerida: `fix/copia-curso-modal-y-codigo`
Pantallas afectadas:
- `/admin/cursos` (Cursos ofertados)
- `/docente/cursos/{curso}/actividades/{actividad}/agenda` (Agenda docente)

---

## 1. Problemas resueltos en esta rama

### 1.1 Modal de previsualización de copia no aparecía
* **Síntoma:** Al hacer clic en «Cursos ofertados > Gestionar > Copiar», la red enviaba `GET /admin/cursos/{id}/preview-copia` con respuesta 200 OK, pero en pantalla no aparecía ningún modal (el slide-over se cerraba y la pantalla quedaba sin cambios).
* **Causa raíz:** En `cursoSlideOver.svelte` (compromiso con `NAV-01`), al cerrarse el panel lateral se ejecutaba `quitarEntradaPropia()` invocando `history.back()`. En Inertia.js, el evento `popstate` resultante desencadenaba una restauración de estado con `preserveState: false`, reseteando el componente padre `Cursos.svelte` (`copyingCurso = null` y `showCopyModal = false`) y destruyendo el modal en milisegundos justo cuando `fetchPreview` estaba en curso.
* **Solución aplicada:**
  - `cursoSlideOver.svelte`: Reemplazo de `history.back()` por `history.replaceState(...)` para limpiar el hash sin disparar eventos de navegación ni forzar a Inertia a resetear el estado de la página.
  - `Cursos.svelte`: En `openCopyModal`, se cierra el slide-over antes de fijar el estado del modal, y se retira el bloque condicional externo `{#if copyingCurso}` para mantener `<CursoCopyPreviewModal>` montado de forma permanente controlado por `bind:isOpen`.

### 1.2 `ReferenceError: router is not defined` en `AgendaDocente.svelte`
* **Síntoma:** Error de JavaScript no capturado al inicializar `$effect` en `AgendaDocente.svelte:85` al intentar ejecutar `router.poll(...)`.
* **Solución aplicada:** Incorporación de `import { router } from '@inertiajs/svelte'`.

---

## 2. TODO pendiente: Definición y tratamiento de `cod_curso`

Al copiar un curso, actualmente el formulario exige de forma obligatoria el ingreso manual de `cod_curso`:
- `CursoController::copiar()` valida `'cod_curso' => 'required|integer|min:1'`.
- `cursoCopyPreviewModal.svelte` exige completar `<input id="new-cod-curso" type="number" required>`.
- `CursoService::copiar()` utiliza `cod_curso` para nombrar los contextos RBAC del curso (`createOrUpdateContext((string) $data['cod_curso'])`).

### Matriz de decisión técnica para retomar

| Escenario | Naturaleza de `cod_curso` | Comportamiento requerido | Impacto en Backend y BD | Impacto en Frontend |
|---|---|---|---|---|
| **Opción A** | **Código interno** de Utamed | **Autogenerar** automáticamente | - Calcular nuevo correlativo (ej. `max(cod_curso) + 1` o secuencia).<br>- Omitir en payload de `copiar()`. | - Eliminar campo del modal de copia.<br>- Solo solicitar `fecha_inicio`. |
| **Opción B** | **Código de Intranet** (Sira/Oracle) | **Permitir NULL** al duplicar | - Modificar columna `curso.curso.cod_curso` a `NULLable`.<br>- Validación `'cod_curso' => 'nullable|integer'`.<br>- Contexto provisional si `cod_curso` es nulo (`'Curso: (Sin código)'` o por `id_curso`). | - Campo opcional en el modal o diferido a la acción «Sincronizar con Intranet». |

### Tareas a ejecutar al retomar:
1. Confirmar con negocio si `cod_curso` proviene exclusivamente del catálogo de Intranet o si funciona como identificador interno del dominio.
2. Si es Opción A: implementar `CursoService::nextCodCurso()` y remover el input del modal.
3. Si es Opción B: crear migración para permitir `cod_curso` nulo en `curso.curso`, ajustar `createOrUpdateContext` para admitir código nulo, y hacer opcional el input en `cursoCopyPreviewModal.svelte`.
