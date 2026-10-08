# Backlog: Pantalla de evaluacion (refresco post-evaluacion y orden de listas)

Fecha: 2026-10-05
Rama base: `main`
Pantalla: `/docente/cursos/{curso}/actividades/{actividad}/evaluacion`

---

## 1. Observaciones

Reporte crudo del usuario:

1. El componente de evaluar no se actualiza inmediatamente luego de evaluar grupal o individualmente.
2. Adicionalmente, al actualizar las decimas manualmente, se ordenan por numero de decimas ascendentemente, lo cual no tiene ningun sentido para la UX.

Confirmacion interactiva del usuario: (1) la nota vieja persiste hasta F5; (2) el reordenamiento se ve en la lista de grupos/tarjetas.

### 1.1 Refresco post-evaluacion

* **Elemento / Contexto**: pantalla de evaluacion; tarjetas de grupo (actividad grupal) y tabla de estudiantes (actividad individual).
* **Problema reportado**: al registrar una evaluacion, individual o grupalmente, la nota de la tarjeta/fila queda con el valor anterior hasta pulsar F5.
* **Evidencia en codigo**:
  * `resources/js/pages/docente/Activities/MatrizEvaluacion.svelte:180-215` - `router.post(.../evaluacion)`; el refresco queda delegado exclusivamente al `redirect()->back()` del controlador.
  * `resources/js/pages/docente/Activities/Index.svelte:443-463` - mismo patron desde la agenda del grupo; `onSuccess` solo dispara `cargarInteracciones()`.
  * `resources/js/pages/docente/Activities/Index.svelte:394-409` - unica recarga explicita de la pantalla: `router.reload({ only: ['interaccionesGrupo'] })`. No existe recarga de `grupos`.
  * `resources/js/pages/docente/Activities/MatrizEvaluacion.svelte:61-82` - al montar hace `history.pushState()` y al desmontar hace `history.back()` cuando `saveSuccess` es falso: cualquier desmontaje anterior a `onSuccess` restaura desde el historial la pagina previa al POST (misma clase de defecto que BUG-03, commit `470aafe`, solo parcheado en el POST de la matriz).
  * `app/Http/Controllers/Docente/DocenteActivityController.php:1879-1891` - `storeEvaluacion()` si persiste la nota; el dato en BD es nuevo, la discrepancia es de cliente.

### 1.2 Reordenamiento de la lista tras ajustar decimas

* **Elemento / Contexto**: lista de grupos/tarjetas de `Index.svelte` y listas internas (`integrantes`, `Notas individuales`).
* **Problema reportado**: al ajustar decimas manualmente las filas/tarjetas cambian de posicion; el usuario lo interpreta como orden ascendente por numero de decimas.
* **Nota de correccion**: el usuario indica que las filas "se ordenan por numero de decimas ascendentemente". Verificado en el repositorio completo (frontend y backend) que **no existe ningun `ORDER BY`/`sortBy`/`sort` por `diferencia_decimas` ni por nota**: los unicos `sort` de la pantalla ordenan la escala de la rubrica por `puntaje_minimo` descendente (`MatrizEvaluacion.svelte:137`, `AgendaDocente.svelte:142`). No hay una ordenacion explicita que corregir, sino la ausencia total de orden.
* **Evidencia en codigo**:
  * `app/Http/Controllers/Docente/DocenteActivityController.php:740-761` - `ActividadAsignadaGrupo::where('id_actividad', ...)->get()` sin `orderBy` y con `->with(['integranteGrupos...'])`.
  * `app/Models/Base/Agenda/BaseActividadAsignadaGrupo.php:57-60` - relacion `integranteGrupos()` sin `orderBy`.
  * `resources/js/pages/docente/Activities/Index.svelte:686`, `components/EstudiantesTabla.svelte:119`, `components/GrupoCard.svelte:230,331` - `{#each}` keyeados que reposicionan el DOM en cuanto cambia el orden del array de props.
  * Escrituras que alteran el orden devuelto: `DocenteActivityController.php:1879` (`storeEvaluacion`), `:1147` (`updateIntegrante`), `:1174` (`recalcularNotasIndividuales`), y `app/Models/Agenda/ActividadAsignadaGrupo.php:104-120` (`sincronizarEstado`, se ejecuta en cada carga de `showEvaluacion()`).
* **Evidencia empirica (base `utamed_1ra_fase`, `DB_CONNECTION=pgsql`)**:
  * `EXPLAIN` de `actividad_asignada_grupo WHERE id_actividad = ?` -> `Seq Scan` (no hay indice sobre `id_actividad`): el orden es el orden fisico de toda la tabla.
  * `EXPLAIN` de `integrante_grupo WHERE id_actividad_asignada_grupo IN (...)` -> `Bitmap Heap Scan` sobre `uq_un_estudiante_por_grupo`: tambien orden fisico.
  * Prueba en tabla temporal: `10,20,30,40,50`; tras `UPDATE` de la fila `20` -> `10,30,40,50,20`; tras `UPDATE` de la fila `10` -> `30,40,50,20,10`. En PostgreSQL cada `UPDATE` mueve la fila al final del heap: las filas recien modificadas migran al final en el orden en que se editaron y simulan un orden correlacionado con las decimas aplicadas.

---

## 2. Interpretacion e Investigacion

### 2.1 Lo que no se vio

1. **Ausencia total de orden determinista en la lectura de la pantalla de evaluacion.** Ninguna de las consultas que alimentan la vista (`actividad_asignada_grupo`, `integranteGrupos`; `estudiantesInscritos` si esta ordenada por nombre en `:800`) declara orden de negocio. En PostgreSQL un `SELECT` sin `ORDER BY` devuelve orden fisico de filas. Como la pantalla escribe sobre esas mismas tablas en cada interaccion (evaluacion, decimas, recalculo, `sincronizarEstado`), cada escritura altera el orden devuelto y los `{#each}` keyeados reposicionan tarjetas y filas. El sintoma 2 es consecuencia directa de esto; el sintoma 1 se agrava porque la fila/tarjeta evaluada salta de sitio y el cambio no se percibe donde el docente lo espera.
2. **El refresco descansa sobre una unica cadena implicita (`redirect()->back()` + props de Inertia), sin recarga explicita ni verificacion.** Solo existe `router.reload({ only: ['interaccionesGrupo'] })`. Sumado al ciclo `history.pushState()`/`history.back()` de `MatrizEvaluacion`, cualquier desmontaje fuera del camino feliz devuelve al navegador la instantanea previa al POST y la nota vieja permanece hasta F5.

Deuda observada, sin tarjeta por regla de alcance: no existe indice sobre `actividad_asignada_grupo.id_actividad`, por lo que toda lectura hace `Seq Scan` de la tabla completa y el orden se afecta con cualquier escritura de cualquier usuario; ademas `evaluacion_obtenida` (resultado cualitativo de las formativas) se persiste en `DocenteActivityController.php:1870` pero nunca se devuelve en `showEvaluacion()` ni se renderiza en `EstudiantesTabla`/`GrupoCard`, de modo que una formativa evaluada sigue mostrando "Sin calificar".

### 2.2 Clarificacion interactiva

Se pregunto al usuario (a) que observa exactamente tras evaluar y (b) en que lista ve el reordenamiento. Respuestas: "la nota vieja persiste hasta F5" y "lista de grupos/tarjetas". No quedan bifurcaciones de negocio abiertas.

---

## 3. Planificacion (Releases y Tablero Kanban)

### R1 - Estabilidad de la pantalla de evaluacion

Tema: refresco inmediato post-evaluacion y orden estable de listas.

```bash
git checkout main
git pull origin main
git checkout -b feature/evaluacion-refresco-orden-estable
```

### Tarjetas

* ### `[BUG-01]` [3 pts] [R1] [Alta] [Estado: Pendiente] La pantalla de evaluacion muestra la nota anterior hasta F5 y reordena las listas tras cada escritura
  * **Ruta**: /docente/cursos/{curso}/actividades/{actividad}/evaluacion
  * **Refinamiento**: Cluster 1/1 (2026-10-05) - atomicidad "mantener unica", deuda absorbida como notas; solucion tecnica aprobada "orden estable + reload explicito + quitar history.back()".
  * **Archivos**:
    * app/Http/Controllers/Docente/DocenteActivityController.php
    * app/Models/Base/Agenda/BaseActividadAsignadaGrupo.php
    * resources/js/pages/docente/Activities/Index.svelte
    * resources/js/pages/docente/Activities/MatrizEvaluacion.svelte
    * resources/js/pages/docente/Activities/components/EstudiantesTabla.svelte
    * resources/js/pages/docente/Activities/components/GrupoCard.svelte
  * **Problema & Causa**: Dos defectos encadenados en la misma pantalla.
    * Refresco: los POST de evaluacion (`MatrizEvaluacion.svelte:180`, `Index.svelte:443`) solo se apoyan en el `redirect()->back()` de `storeEvaluacion()` (`DocenteActivityController.php:1901`); no hay recarga de `grupos` (la unica es `router.reload({only:['interaccionesGrupo']})` en `Index.svelte:398`) y `MatrizEvaluacion.svelte:61-82` ejecuta `history.back()` al desmontar si `saveSuccess` no esta, restaurando la instantanea previa al POST: la nota vieja persiste hasta F5.
    * Orden: `showEvaluacion()` consulta `actividad_asignada_grupo` e hidrata `integranteGrupos` sin `ORDER BY` (`DocenteActivityController.php:740-741`, `BaseActividadAsignadaGrupo.php:57`). Con `pgsql` ambos planes son `Seq Scan`/`Bitmap Heap Scan` sobre orden fisico y cada `UPDATE` (`:1879` evaluacion, `:1147` decimas, `:1174` recalculo, `ActividadAsignadaGrupo.php:117` estado) mueve la fila al final del heap; los `{#each}` keyeados de `Index.svelte:686`, `EstudiantesTabla.svelte:119` y `GrupoCard.svelte:331` reposicionan tarjetas y filas tras cada escritura.
  * **Reglas de negocio blindadas**:
    * Orden de negocio declarado y estable: grupos e integrantes por nombre, identico en modo individual y grupal; ninguna escritura altera la posicion de una fila o tarjeta.
    * El refresco de la lista de grupos es responsabilidad del cliente: `router.reload({only:['grupos']})` tras guardar, sin depender del `redirect()->back()`.
    * El cierre de `MatrizEvaluacion` no manipula `window.history` (sin `pushState`/`back()`).
    * Solucion tecnica elegida (Paso 2): orden estable + reload explicito + eliminar history.back(). La alternativa "ordenar por nota/decimas" se descarto por no existir regla de negocio que la sustente; la alternativa "solo parchear Inertia" (commit `470aafe`) se descarto porque no garantiza el refresco.
  * **Notas absorbidas (deuda, sin tarjeta por regla de alcance)**:
    * No existe indice sobre `actividad_asignada_grupo.id_actividad`: toda lectura hace `Seq Scan` de la tabla completa y el orden se afecta con escrituras de cualquier usuario. Resuelvable aparte, no bloquea este DoD porque el `ORDER BY` explícito garantiza el orden.
    * `evaluacion_obtenida` (resultado cualitativo de formativas) se persiste en `DocenteActivityController.php:1870` pero nunca se devuelve en `showEvaluacion()` ni se renderiza en `EstudiantesTabla`/`GrupoCard`, por lo que una formativa evaluada sigue mostrando "Sin calificar".
  * **Criterios de Aceptacion (DoD)**:
    * [ ] `showEvaluacion()` y `integranteGrupos()` declaran `ORDER BY` estable (nombre), verificable en ambos modos: individual y grupal.
    * [ ] Tras evaluar (grupal o individual, sumativa o formativa) la tarjeta/fila muestra la nota o resultado nuevo sin pulsar F5, via `router.reload({only:['grupos']})` en el `onSuccess` de los dos POST (`MatrizEvaluacion.svelte:180`, `Index.svelte:443`).
    * [ ] El registro evaluado conserva su posicion en la lista: no salta de lugar tras guardar (incluye `sincronizarEstado`).
    * [ ] Tras ajustar decimas la nota final se actualiza en sitio y ninguna fila ni tarjeta cambia de posicion.
    * [ ] Cerrar `MatrizEvaluacion` no depende de `history.pushState()`/`history.back()`: el cierre tras guardar no altera el historial ni restaura props anteriores.
    * [ ] Verificacion manual: evaluar dos grupos consecutivos y ajustar decimas en tres filas sin observar cambios de orden ni de valores.

### Metricas

* Total de tarjetas: 1
* Puntos por release: R1 = 3 pts
* Total de puntos de historia: 3 pts
* Refinamiento backlog-refiner (2026-10-05): cluster 1/1 procesado, 1 tarjeta mantenida (estado: Pendiente), 0 absorbidas, deuda registrada como notas.
