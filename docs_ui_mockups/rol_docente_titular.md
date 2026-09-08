# Docente Titular (DT)

> **Nombre en BD:** `Docente Titular` — pero la autoridad real la da el dato
> **`curso.id_docente_titular === $user->docente->id_docente`**
> **Middleware:** `is_docente` (rol Docente\* **Y** fila `usuario.docente`)
> **Policy:** `CursoPolicy::manageTeam()` / `viewPrograma()`
> **Layout:** `DocenteLayout.svelte` + `RoleSidebar` (sección `Docente`)

---

## Descripción y Objetivo del Rol

Es el **dueño de un curso**. El sistema no le concede privilegios por su nombre
de rol sino por ser el titular *actual* de ese curso; si lo reemplazan, pierde
el acceso en el acto y el intento queda registrado en el canal `seguridad` como
posible IDOR (`CursoPolicy::manageTeam`, comentario *"AUDIT LOG: … log potential
IDOR attempt"*).

Su privilegio central es que **no necesita permisos explícitos**: en el contexto
de su curso `userPermissions` viene vacío a propósito —
*"El titular tiene acceso completo — no necesita permisos explícitos"*
(`DocenteCursoController::show`). Todos los demás miembros del equipo trabajan
con la matriz de permisos que **él mismo** les delega.

**Objetivo funcional:** armar el equipo, repartir competencias, redactar y
enviar el syllabus, planificar actividades y rúbricas, evaluar, tomar asistencia
y comunicarse con el curso.

---

## Flujos de Usuario (User Flows)

### F1. Entrada

```
Login → /dashboard → hasAnyRole(Docente*) → redirect /docente/dashboard
  → docente/Dashboard.svelte
     stats.total_cursos · cursos[] (con tiene_programa) · mensajeria.no_leidos
     · jefatura.has_access (si además es Jefe de Carrera)
```

### F2. Syllabus, de cero a enviado (flujo largo)

```
/docente/cursos → tarjeta del curso → "Programa"
  → GET /docente/cursos/{curso}/programa        (Administrativo\ProgramaController::show)
  ├─ sin programa → estado vacío + CTA "Crear syllabus"
  └─ con programa → ProgramaDocument + acciones según estado

  Redacción → SyllabusModal (wizard)
     Tipo BÁSICO   : 4 pasos  → I. Identificación · II. Presentación · VI. Unidades · Resumen
     Tipo COMPLETO : 9 pasos  → I…IX
  → POST /docente/cursos/{curso}/programa            (guarda data_syllabus JSONB)
  → PUT  .../programa/completar-basico   BORRADOR → BASICO_COMPLETO
  → PUT  .../programa/enviar             BORRADOR|BASICO_COMPLETO → COMPLETO
  → (espera resolución de Admin o Jefe de Carrera)
  ├─ APROBADO → el documento queda bloqueado para edición
  └─ rechazado → vuelve a BORRADOR y la vista muestra razon_rechazo + fecha_rechazo
```
Pasos del wizard (`SyllabusModal.svelte:185`): I. Identificación 📚 · II. Presentación 📝 ·
III. Estándares 📋 · IV. Competencias 🎯 · V. Evaluación Diagnóstica 📊 ·
VI. Unidades 📖 · VII. Planificación 📅 · VIII. Recursos 📚 · IX. Aspectos Admin. ⚙️
(+ paso 10 "Resumen ✓" sólo en la variante BÁSICO).

### F3. Armado del equipo docente

```
/docente/cursos/{curso}/docentes  (docente/DocentesCurso.svelte)
  → GET  /docente/cursos/{curso}/team
  → GET  /docente/cursos/{curso}/team/search-assistants   (autocomplete)
  → POST /docente/cursos/{curso}/team                      (añadir miembro)
  → DELETE /docente/cursos/{curso}/team/{usuario}
  → PUT  /docente/cursos/{curso}/componentes/{comp}/titular  (nombrar titular de componente)
```
⚠️ Techo duro: sólo puede otorgar los roles `ayudante` y `estudiante`
(`Rol::whereIn('nombre',['ayudante','estudiante'])`). No puede crear docentes.

### F4. Delegación de permisos (flujo distintivo del DT)

```
/docente/cursos/{curso}/delegacion-permisos → docente/DelegacionPermisosCurso.svelte
  → matriz miembros × permisos, agrupada en 7 bloques
  → click en celda → POST .../delegacion-permisos/toggle {id_usuario, slug, otorgar}
  → flash.success "Permiso actualizado"
```
Y dos matrices más finas:
```
/docente/cursos/{curso}/permisos-syllabus                     (9 módulos del syllabus)
/docente/cursos/{curso}/componentes/{comp}/permisos           (notas y asistencia, si es titular del componente)
```
⚠️ **HUECO:** las páginas `docente/PermisosSyllabus` y `docente/PermisosComponente`
se renderizan desde `CursoPermisosController` pero **no existen como archivo
Svelte**. Sí existen los componentes `PermisosSyllabusMatriz.svelte`,
`ColegiadoPermisosModal.svelte`, `SyllabusPermisosModal.svelte` y
`ComponentePermisosModal.svelte` — el diseño debe decidir si son páginas o modales.

### F5. Actividades, grupos y evaluación

```
/docente/cursos/{curso}/actividades      → docente/Actividades.svelte  (listado + kanban)
  → "Nueva actividad" → modal (componente, unidad, tipo, fecha límite, grupal, holgura, visible)
  → POST /docente/cursos/{curso}/actividades
  → toggle visibilidad → PATCH .../actividades/{a}/visibilidad
  → subir enunciado    → POST .../actividades/{a}/enunciado   (PDF/imagen, límites de config/filetypes)

/docente/cursos/{curso}/actividades/{a}/evaluacion → docente/Activities/Index.svelte
  ├─ GrupoCard por grupo (nota, estado, integrantes)
  ├─ NuevoGrupoModal            → POST .../grupos-create
  ├─ ReutilizarGruposModal      → GET .../grupos-origen/{origen} + POST .../grupos-copy
  ├─ EntregasModal (lazy)       → GET .../grupos/{g}/entregas  + descargar
  ├─ RubricaEditor (sólo DT)    → POST /docente/cursos/{curso}/rubrica
  ├─ MatrizEvaluacion           → POST .../grupos/{g}/evaluacion
  ├─ AgendaDocente (hilo)       → POST /docente/cursos/{curso}/grupos/{g}/feedback
  └─ nota individual            → PUT .../integrantes/{asignado}
                                  POST .../grupos/{g}/recalcular-notas
```

### F6. Asistencia

```
/docente/asistencia  → docente/Asistencia.svelte (centro: todos sus cursos/componentes)
  → /docente/cursos/{c}/componentes/{comp}/asistencia
     POST (crear sesión) · PUT (editar) · DELETE (eliminar sesión)
```
Modelo: la **sesión es implícita**, agrupada por `(dia, hora_inicio, hora_fin)`
sobre filas de `curso.asistencia` ligadas a `inscripcion_componente`.

### F7. Calificaciones y calendario

```
/docente/calificaciones → centro de notas: cursos › componentes › actividades
/docente/calendario     → eventos = fecha_limite de cada actividad de sus cursos
```

### F8. Comunicación (dos canales)

```
Nivel curso:      /docente/cursos/{curso}/mensajeria     → docente/Mensajeria.svelte
                  POST .../componentes/{comp}/difusion            (aviso a todo el componente)
                  POST .../componentes/{comp}/alumnos/{a}/mensaje (canal 1-a-1)
Nivel actividad:  /docente/mensajes                       → docente/Mensajes.svelte
                  /docente/cursos/{curso}/mensajes        → docente/MensajesCurso.svelte
                  POST /docente/mensajes/cursos/{c}/actividades/{a}/enviar
```

---

## Vistas Exclusivas del Rol

### V1. Delegación de Permisos del Curso — `docente/DelegacionPermisosCurso.svelte`
- **Objetivo:** repartir competencias del curso entre el equipo, sin pasar por
  el administrador.
- **Datos consumidos:** `curso {id_curso, cod_curso, nombre}`; `miembros[]`
  (equipo **excluyendo al propio DT**) cada uno con su mapa `permisos` por slug;
  `grupos[]` (matriz delegable); `id_contexto`.
- **Matriz delegable exacta (`DELEGABLE_MATRIX`), 7 bloques:**

  | Bloque | Permisos |
  |---|---|
  | Curso | `cursos:ver`, `cursos:editar`, `cursos:eliminar` |
  | Inscripciones | `cursos/inscripciones:ver` |
  | Unidades | `cursos/unidades:` ver, crear, crear_plantilla, editar, eliminar |
  | Programas | `cursos/programas:` ver_todos, agregar, eliminar + `modificar:modulo_1..9` |
  | Componentes | `componentes:ver`, `componentes:editar` |
  | Actividades | `actividades:` ver, crear, crear_plantilla, editar, eliminar, evaluar, dar_feedback, descargar_entregas, enviar_recordatorios, subir_entregas |
  | Grupos de Actividad | `actividades/grupos:` ver, crear, editar, eliminar |

- **Componentes UI clave inferidos:** matriz **personas (filas) × permisos
  (columnas)** con secciones colapsables por bloque; celda = switch; guardado
  optimista por celda (un POST por toggle, no un "Guardar" global); avatar +
  nombre + rol del miembro en la columna fija.

### V2. Permisos de Syllabus por módulo — `PermisosSyllabusMatriz.svelte`
- **Objetivo:** decidir **qué sección del syllabus puede tocar cada docente**.
- **Datos consumidos:** `docentes[]` (del curso, sin el DT) con su mapa de
  permisos; `slugs_disponibles[]`; `id_contexto_curso`.
- **Mapeo de etiquetas ya definido** (`SyllabusPermisosModal.svelte:42`):
  `modulo_1 → Sección I` … `modulo_9 → Sección IX`.
- **Componentes UI:** matriz 9 columnas (Secciones I–IX) × N docentes, con
  cabecera rotada o abreviada; los nombres largos se resumen a "Modificar
  Secciones" en la vista compacta.

### V3. Permisos de Componente Colegiado — `ColegiadoPermisosModal.svelte`
- **Objetivo:** cuando un componente tiene varios docentes, su titular reparte
  **notas y asistencia**.
- **Autorización:** `authorizeEsTitularComponente($componente)` — no basta ser
  DT del curso.
- **Datos consumidos:** `componente`, `docentes[]` del componente, `slugs_disponibles`
  (familia `componentes/asistencia:*` y notas), `id_contexto_componente`.

### V4. Editor de Rúbrica — `docente/Activities/RubricaEditor.svelte`
- **Objetivo:** construir el instrumento de evaluación de una actividad.
- **Sólo el titular lo abre en modo edición:** `actividad.es_titular ?
  showRubricaEditor = true : toggleRubricaModal()` (`Activities/Index.svelte:544`)
  — los demás ven la **vista de sólo lectura** (`student/Activities/Agenda/Rubrica.svelte`,
  reutilizada literalmente por el docente).
- **Datos consumidos:** `Rubrica` (`estado`: `POSTULADA`/`CERRADA`), criterios y
  niveles; `rubrica_id`.
- **Componentes UI:** editor de criterios (filas) × niveles (columnas) con
  puntajes; guardado a `POST /docente/cursos/{curso}/rubrica`.

### V5. Equipo docente del curso — `docente/DocentesCurso.svelte`
- **Datos consumidos:** por componente, `docenteComponentes` ordenados con
  `es_titular` primero, con `id_docente_componente`, `id_usuario`, nombre completo.
- **Componentes UI:** lista por componente, buscador de asistentes,
  botón "quitar" con confirmación, marca de titular.

### V6. Vista de curso, perspectiva del titular — `docente/CursoDetalle.svelte`
Ver `vistas_compartidas.md` (VC2): el DT recibe además `todos_componentes` con
todos los docentes y matrículas, no sólo los suyos.

---

## Vistas Compartidas (Modificadas)

| Vista | Compartida con | Qué habilita **de más** el DT |
|---|---|---|
| `docente/CursoDetalle` | Docente Componente | Bloque "Todos los componentes" con docentes y totales; accesos a equipo, delegación y syllabus |
| `docente/Actividades` | Docente Componente con `actividades:crear/editar` | Crear/editar/eliminar en **cualquier** componente del curso, no sólo el suyo |
| `docente/Activities/Index` (evaluación) | Docente Componente con `actividades:evaluar` | Botón **Editar rúbrica**; `es_titular:true` en el prop `actividad` |
| `docente/Asistencia` | Docente Componente | Ve **todos** los componentes del curso (`esTitularCurso \|\| imparte`) |
| `docente/Calificaciones` | Docente Componente | Idem: filtro `es_titular_curso` abre todo el curso |
| `docente/Programa` (syllabus) | Docente Componente autorizado, Ayudante, Admin | Botones `completar-basico`, `enviar`, `destroy`; sin necesidad de permisos por módulo |
| `/docente/cursos/{c}/team` | Administrador | Restringido a **sus** cursos (`manageTeam`) |
| Mensajería de curso | Docente Componente, Ayudante | Difusión a **todos** los componentes que ve; los demás sólo a los suyos |

---

## Interacciones y Estados

| Interacción | Implementación |
|---|---|
| Pérdida de titularidad | Acceso denegado inmediato + entrada en canal `seguridad` con IP y user-agent |
| Confirmaciones | `ConfirmDialog.svelte` en eliminar actividad, quitar miembro, borrar grupo, eliminar sesión de asistencia |
| Toggle de permiso | POST inmediato por celda; respuesta `back()->with('success','Permiso actualizado')` |
| Guarda anti-escalada | `assertIsMiembroCurso()` — no puede delegarse permisos a sí mismo ni a alguien ajeno al equipo |
| Validación de enunciado | Extensiones y tamaños llegan como prop `reglas_enunciado` (`extensiones_pdf`, `extensiones_img`, `max_mb_pdf`, `max_mb_imagen`): el mensaje de error debe usar esos valores, no constantes de diseño |
| Nota individual | Se calcula como **nota grupal + décimas** (snapshot); el recálculo es **manual** vía botón "Recalcular notas" |
| Estados de actividad | `PLANIFICADA` / `ACTIVA` / `CERRADA`, con color por estado (`getEstadoColor`) |
| Visibilidad de actividad | Switch `visible` — mientras esté apagada, el alumno no la ve |
| Carga diferida | `interaccionesGrupo` y las entregas llegan por `Inertia::lazy` al abrir el modal: hace falta **estado de carga** dentro del modal |
| Mensajería leída | Abrir la pestaña **marca como leído**; los badges se calculan después del panel para que no queden desfasados |
