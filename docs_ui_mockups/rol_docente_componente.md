# Docente Componente

> **Nombres en BD:** `Docente Componente`, `Docente Componente Colegiado`,
> `Docente Titular Restringido` (variantes; ver §Variantes)
> **Puerta de entrada:** fila en `curso.docente_componente` para ese componente
> **Middleware:** `is_docente` (mismo prefijo `/docente` que el DT)
> **Policy:** `CursoPolicy::viewPrograma()` — *"Docentes asignados a componentes:
> acceso según tipo de componente"*
> **Layout:** `DocenteLayout.svelte` + `RoleSidebar` (sección `Docente`)

---

## Descripción y Objetivo del Rol

Imparte **un componente concreto** (Cátedra, Laboratorio o Taller) de un curso
cuyo titular es otra persona. Comparte el prefijo `/docente` y casi todas las
pantallas del Docente Titular, pero **todo lo que ve está recortado a los
componentes que imparte** y **todo lo que puede hacer depende de los permisos
que el DT le haya delegado** en el contexto de ese curso.

La diferencia se materializa en un solo prop presente en casi toda vista de
curso: `userPermissions` (array de `{ id_permiso, slug, esta_permitido }`
resuelto para `curso.id_contexto`). Para el DT llega **vacío** porque no lo
necesita; para el Docente Componente **es la fuente de verdad de la UI**.

**Objetivo funcional:** gestionar su grupo — lista de estudiantes, asistencia,
actividades y notas del componente — y colaborar en el syllabus si tiene los
módulos delegados.

### Variantes del rol

| Variante | Qué la distingue en el código |
|---|---|
| `Docente Componente` | Caso base descrito en este documento |
| `Docente Componente Colegiado` | Un componente con **varios** docentes (`ComponenteController:691`). Su titular reparte notas y asistencia vía `componentes/docentesColegiados:*` y `ColegiadoPermisosModal` |
| `Docente Titular Restringido` | Aparece sólo en la lista de `IsDocente` y en el dashboard. **No tiene ruta, policy ni permiso propio**: hoy se comporta como Docente Componente. ⚠️ Diseñar como variante visual del DT sin acciones de gobierno (equipo, delegación), no como rol nuevo |

---

## Flujos de Usuario (User Flows)

### F1. Entrada y navegación

```
Login → /dashboard → hasAnyRole(Docente*) → /docente/dashboard
  → sidebar: sus cursos agrupados por Año · Semestre
     (período vigente desplegado, resto bajo "Histórico" con buscador)
  → cada curso ofrece 2 accesos directos: "Programa" y "Actividades"
```
`RoleSidebar.svelte` agrupa por `agno_real`/`semestre_real`, marca el período
vigente como el más reciente con cursos y colapsa lo demás.

### F2. Mi grupo (flujo cotidiano)

```
/docente/cursos → tarjeta → /docente/cursos/{curso}
  → docente/CursoDetalle.svelte, 3 pestañas:
     [ Mi Grupo ] [ Actividades ] [ Asistencia ]
  → Mi Grupo: tabla de estudiantes de SUS componentes
     → fila → EstudianteDetalleModal (nota del componente, datos, mensajes)
```
Deep-link soportado: `?tab=asistencia` y `?tab=actividades`, honrados sólo si la
pestaña es accesible en ese curso (`CursoDetalle.svelte:153`).

### F3. Asistencia del componente

```
/docente/asistencia → docente/Asistencia.svelte
  → sólo aparecen los componentes con `imparte: true`
  → /docente/cursos/{c}/componentes/{comp}/asistencia
     → lista de sesiones (dia · hora_inicio · hora_fin · presentes/total)
     → nueva sesión: fecha + rango horario + pase de lista
     POST (crear) · PUT (editar) · DELETE (eliminar)
```
Requiere `componentes/asistencia:registrar` / `:editar` / `:eliminar` delegados,
salvo que sea titular del componente.

### F4. Actividades y evaluación (según permisos)

```
/docente/cursos/{curso}/actividades
  ├─ con `actividades:ver`     → ve el listado
  ├─ con `actividades:crear`   → botón "Nueva actividad"
  ├─ con `actividades:editar`  → lápiz por fila
  └─ sin permiso               → la acción no se renderiza (no se deshabilita)

/docente/cursos/{c}/actividades/{a}/evaluacion
  ├─ con `actividades:evaluar`            → MatrizEvaluacion editable
  ├─ con `actividades:dar_feedback`       → AgendaDocente (responder al grupo)
  ├─ con `actividades:descargar_entregas` → EntregasModal con descarga
  ├─ con `actividades/grupos:crear|editar`→ NuevoGrupoModal / edición de grupos
  └─ rúbrica: **sólo lectura** (el editor es del titular)
```

### F5. Colaboración en el syllabus

```
/docente/cursos/{curso}/programa
  → el documento completo se ve siempre (viewPrograma)
  → cada sección I–IX es editable sólo si tiene
    `cursos/programas/modificar:modulo_N` en el contexto del curso
  → sin `cursos/programas:agregar` no puede crear el programa (sólo el DT/Admin)
```
`docente/Programa.svelte:107` comprueba los slugs de módulo para decidir si
muestra el modo edición.

### F6. Mensajería de su componente

```
/docente/cursos/{curso}/mensajeria
  → pestañas = SÓLO los componentes que imparte (no todo el curso)
  → "Nuevo aviso"  → POST .../componentes/{comp}/difusion   {tema, mensaje}
  → canal alumno   → POST .../componentes/{comp}/alumnos/{a}/mensaje
```

---

## Vistas Exclusivas del Rol

> Este rol **no tiene rutas propias**: su exclusividad son *bloques de UI* que
> el Docente Titular no ve porque para él no aplican.

### V1. Panel "Mi Grupo" — pestaña de `docente/CursoDetalle.svelte`
- **Objetivo:** su lista de clase, independiente del resto del curso.
- **Datos consumidos:** `mis_componentes[]` = `{ id_componente, tipo_componente,
  es_titular, total_docentes, total_estudiantes }`; `mis_estudiantes[]` =
  `{ id_inscripcion_componente, id_componente, tipo_componente, nota_componente,
  estudiante { id_estudiante, nombre, username } }`.
- **Componentes UI clave inferidos:** `ComponentePills` (chips para conmutar
  entre sus componentes), `EstudiantesTable` (con contador en el tab),
  `EstudianteDetalleModal`, contador de estudiantes junto al nombre de la pestaña.

### V2. Sub-pestañas de componente colegiado
- **Objetivo:** cuando comparte componente con otros docentes, alternar entre la
  nómina y el registro de asistencia sin salir del panel.
- **Componentes UI:** grupo de 2 sub-pestañas `Estudiantes | Asistencia`
  (`colegiadoTab` en `CursoDetalle.svelte:163`), con fondo arena `#F5F1EA` y
  pastilla activa blanca.

### V3. `AsistenciaPanel.svelte` embebido
- **Objetivo:** pase de lista sin abandonar el detalle del curso.
- **Datos consumidos:** sesiones agrupadas por `(dia, hora_inicio, hora_fin)` con
  `total`, `presentes` y `registros[] { id_inscripcion_componente, esta_presente }`.
- **Componentes UI:** cabecera de sesión con contador `presentes/total`,
  lista de alumnos con toggle presente/ausente, acción "Nueva sesión".

### V4. `ActividadesPorEstado.svelte` embebido
- **Objetivo:** kanban ligero de las actividades del curso.
- **Datos consumidos:** `actividades[]` con `nombre`, `fecha_limite`,
  `tipo_actividad`, `tipo_entrega`, `es_grupal`, `max_integrantes`, `visible`,
  `componente.tipo_componente.nombre`.
- **Componentes UI:** columnas por estado, tarjeta con badge de componente,
  marca de "oculta" cuando `visible = false`.

---

## Vistas Compartidas (Modificadas)

Todas las pantallas son las del Docente Titular. Diferencias **exactas**:

| Vista | Docente Titular | Docente Componente |
|---|---|---|
| `docente/CursoDetalle` | `es_titular_curso: true`; recibe **`todos_componentes`** (todos los docentes y matrículas del curso) y la fila de KPIs "N componentes · N docentes · N estudiantes" | `es_titular_curso: false`; `todos_componentes` llega **vacío**; sólo `mis_componentes` + `mis_estudiantes` |
| `docente/Asistencia` (centro) | Todos los componentes de todos sus cursos | Filtro `esTitularCurso \|\| $c->docenteComponentes->isNotEmpty()`; cada componente trae `imparte` y `es_titular_componente` para marcar cuáles son suyos |
| `docente/Calificaciones` (centro) | Todos los componentes | Sólo componentes con actividades donde imparte |
| `docente/Actividades` | Botones siempre visibles | Botones **condicionados a slug**; `curso.userPermissions` no vacío |
| `docente/Activities/Index` | `actividad.es_titular: true` → **RubricaEditor** | `es_titular: false` → **RubricaView** (sólo lectura) |
| `docente/Programa` | Edición total + enviar/completar/eliminar | Edición **por sección**, según `modificar:modulo_1..9` |
| Equipo / Delegación de permisos | Acceso completo | **Sin acceso** (`manageTeam` sólo autoriza al titular actual) |
| `/docente/cursos/{c}/componentes/{comp}/permisos` | Si es titular del componente | Sólo si es **titular de ese componente** (`authorizeEsTitularComponente`) |
| Mensajería de curso | Pestañas de todos los componentes | Pestañas de los suyos |
| `docente/Calendario` | Eventos de todos sus cursos | Idem, limitado a sus componentes |
| Dashboard docente | `cursos[]` = donde es `id_docente_titular` | ⚠️ El dashboard lista sólo cursos donde es **titular** (`Curso::where('id_docente_titular', …)`): un docente componente puro ve **0 cursos** ahí y debe usar el sidebar o `/docente/cursos` |

---

## Interacciones y Estados

| Interacción | Implementación |
|---|---|
| Acción no permitida | El componente **no se renderiza** (patrón `{#if can(...)}`), no se muestra deshabilitado. Para mockups: no dibujar botones grises, dibujar la versión reducida de la pantalla |
| Acceso a curso ajeno | `viewPrograma` deniega → redirect a `/dashboard` con `flash.error` |
| Gestión de equipo sin ser titular | `manageTeam` deniega **y registra** `ACCESO_DENEGADO_MANAGEAM_TEAM` en el canal `seguridad` |
| Deep-link a pestaña no accesible | `?tab=asistencia` se ignora si `mis_componentes` está vacío; cae a "Mi Grupo" |
| Contadores en pestañas | Badge numérico junto al título de cada pestaña (estudiantes, actividades) |
| Estados de nota | `nota_componente` puede ser `null` → celda vacía distinguible de un 0 |
| Mensajería | Envío valida `tema` (≤150) y `mensaje` (≤2000); éxito → `flash.success` *"Aviso enviado a todo el componente."* |
| Componente sin permiso de asistencia | El bloque de asistencia no aparece aunque el componente sí |
