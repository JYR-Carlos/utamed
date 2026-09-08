# Ayudante

> **Nombre en BD:** `Ayudante`
> **Puerta de entrada:** `UsuarioRolAsignacion` activa en el **contexto de un
> curso** (`id_contexto === curso.id_contexto`)
> **Middleware:** `is_ayudante` — sólo comprueba `hasRole('Ayudante')`, **no**
> exige perfil docente ni estudiante
> **Layout:** `AyudanteLayout.svelte` + `RoleSidebar` (sección `Ayudantía`)

---

## Descripción y Objetivo del Rol

Es **apoyo académico acotado a uno o varios cursos concretos**. Normalmente es
un estudiante avanzado: el dashboard del alumno calcula un flag `isAyudante` y
el sidebar activa la pestaña `Ayudantía` junto a `Estudiante`
(`Student/DashboardController.php:85`).

Su alcance es el más estrecho de los roles de staff y está definido por
**exclusión** en el código:

- **No puede crear syllabus.** `ProgramaPolicy::create()` corta explícitamente:
  *"**Ayudante NO puede crear programas** (solo puede editar si autorizado)"*
  (línea 221) — antes incluso de mirar permisos.
- **No puede editar un syllabus `APROBADO`** (`Ayudante/ProgramaController:106,164`).
- **No gestiona equipo, ni delega permisos, ni evalúa, ni toma asistencia**: no
  existen esas rutas bajo `/ayudante`.
- El rol se lo concede el **Docente Titular** del curso (es uno de los dos únicos
  roles delegables: `['ayudante','estudiante']`).

**Objetivo funcional:** colaborar en la redacción del syllabus del curso y
atender la mensajería del componente en nombre del equipo docente.

---

## Flujos de Usuario (User Flows)

### F1. Entrada

```
Login → /dashboard
  → NO es docente · NO es estudiante · hasRole('Ayudante')
  → redirect /ayudante/dashboard
```
⚠️ Si además es estudiante, el dashboard lo manda **primero** a
`/estudiante/dashboard` (prioridad Estudiante > Ayudante en `routes/web.php:62`).
Llega a su área de ayudantía por la **pestaña `Ayudantía`** del sidebar.

### F2. Colaboración en el syllabus (flujo principal)

```
/ayudante/cursos → ayudante/Courses/Index.svelte
  → tarjeta de curso → /ayudante/cursos/{curso}  (Show)
  → "Ver programa"    → GET /ayudante/cursos/{curso}/programa
  → "Editar programa" → GET /ayudante/cursos/{curso}/programa/editar
       ├─ estado APROBADO → bloqueado (no se ofrece la acción)
       └─ editable → SyllabusModal (wizard reutilizado)
                   → POST /ayudante/cursos/{curso}/programa   (update)
  → "Crear"           → GET /ayudante/cursos/{curso}/programa/create
       ⚠️ la ruta existe, pero la policy prohíbe crear: sólo tiene sentido como
          continuación de un programa ya instanciado por el DT/Admin
```
El sidebar del ayudante expone **dos enlaces por curso**: `…/programa` y
`…/programa/create` (`RoleSidebar.svelte:700,714`).

Endpoints JSON que alimentan el wizard:
`GET /ayudante/cursos/{c}/componentes` · `GET /ayudante/cursos/{c}/actividades/json` ·
`GET /ayudante/cursos/{c}/programa/json`.

### F3. Mensajería del curso

```
/ayudante/cursos/{curso}/mensajeria → ayudante/Mensajeria.svelte
  → pestañas = componentes donde tiene el rol Ayudante en ESE curso
  → "Nuevo aviso" → POST .../componentes/{comp}/difusion         {tema, mensaje}
  → canal alumno  → POST .../componentes/{comp}/alumnos/{a}/mensaje
```
Comentario del código: *"misma bandeja que el docente, acotada al curso desde el
que se entra y sólo si el usuario tiene ahí el rol Ayudante"*.

---

## Vistas Exclusivas del Rol

### V1. Mis Cursos de Ayudantía — `/ayudante/cursos` → `ayudante/Courses/Index.svelte`
- **Objetivo:** entrada única a los cursos donde ayuda.
- **Datos consumidos por curso:** `id_curso`, `nombre`, `cod_curso`,
  `asignatura_nombre`, `carrera_nombre`, `fecha_inicio`, `fecha_fin`,
  `semestre_real`, `agno_real`, `letra_grupo`, `total_estudiantes`
  (⚠️ hoy siempre `0`, valor fijo en el controlador) y **`userPermissions[]`**
  con `{ id_permiso, slug, esta_permitido, puede_delegar }`.
- **Componentes UI clave inferidos:** grid de tarjetas de curso con código,
  asignatura, período y letra de grupo; acciones condicionadas por
  `userPermissions`. **No mostrar contador de estudiantes** hasta que el backend
  lo calcule.

### V2. Detalle del Curso — `/ayudante/cursos/{curso}` → `ayudante/Courses/Show.svelte`
- **Objetivo:** hub del curso desde la ayudantía.
- **Datos consumidos:** `id_curso`; `curso { id_curso, nombre, cod_curso,
  asignatura_nombre, carrera_nombre, fecha_inicio, fecha_fin }`;
  `tiene_programa` (booleano); `userPermissions[]`.
- **Componentes UI clave inferidos:** cabecera de curso; tarjeta **Syllabus** con
  dos estados —"aún no existe programa" vs. "ver / editar"— y tarjeta
  **Mensajería** con badge de no leídos.
- **Verificación de acceso:** `403 'No tienes permiso para ver este curso'` si no
  hay asignación de rol Ayudante en el contexto de ese curso.

### V3. Bandeja de Mensajería — `/ayudante/cursos/{curso}/mensajeria` → `ayudante/Mensajeria.svelte`
- **Objetivo:** responder alumnos en nombre del equipo docente.
- **Datos consumidos:** `curso`, `componentes[]` (pestañas con `no_leidos`),
  `componente_activo`, `base_ruta` (prefijo de rutas del rol, lo entrega el
  backend), `panel` (difusiones + canales de alumno del componente activo).
- **Componentes UI:** `BandejaStaff.svelte` — pestañas por componente con badge,
  lista de conversaciones, panel de lectura, compositor con `tema` + `mensaje`,
  y conmutador Difusión / Mensaje individual.

---

## Vistas Compartidas (Modificadas)

| Vista | Compartida con | Recorte para el Ayudante |
|---|---|---|
| **`docente/Programa.svelte`** (el ayudante recibe esta misma página) | Docente, Admin | El backend inyecta `layoutType: 'ayudante'` y `backUrl: '/ayudante/cursos/{id}'`. Sin acciones de estado: **no** hay `completar-basico`, `enviar`, `aprobar`, `rechazar` ni `destroy` |
| **`SyllabusModal`** (wizard 4/9 pasos) | Docente Titular, Docente Componente, Admin | Mismo wizard; se abre en modo edición y **nunca** en modo creación |
| **Bandeja de mensajería** (`BandejaStaff`) | Docente Titular, Docente Componente | Idéntica; cambia `base_ruta` y el conjunto de componentes visibles |
| **`/settings/*`** | Todos | Sin diferencias |
| **Dashboard** | Administrador | ⚠️ **Reutiliza `Dashboard.svelte` del admin** con stats globales (usuarios, cursos, facultades, carreras) que no le competen. `Ayudante/DashboardController.php:46` deja los TODOs a la vista. **Requiere diseño nuevo**: un dashboard de ayudantía con sus cursos y sus mensajes sin leer |

---

## Interacciones y Estados

| Interacción | Implementación |
|---|---|
| Sin rol de Ayudante | `redirect('/dashboard')->with('error','No tienes permisos de Ayudante.')` |
| Curso ajeno | `abort(403, 'No tienes permiso para ver este curso')` |
| Syllabus aprobado | El controlador bloquea `create`/`edit`; la UI debe mostrar el documento en **modo lectura con sello de aprobado**, no un botón deshabilitado |
| Intento de crear programa | `ProgramaPolicy::create()` → `false` para cualquier ayudante |
| Envío de mensaje | Validación `tema` ≤150, `mensaje` ≤2000; éxito → `flash.success` |
| Lectura de mensajes | Abrir la pestaña marca como leído antes de recalcular badges |
| Doble rol Estudiante+Ayudante | Pestañas `Estudiante` / `Ayudantía` en el sidebar; el flag `isAyudante` viaja también al dashboard del alumno para ofrecer el salto |
| `puede_delegar` | Los `userPermissions` traen esta bandera, pero **no hay UI de delegación** para el ayudante → dato disponible, función inexistente |
