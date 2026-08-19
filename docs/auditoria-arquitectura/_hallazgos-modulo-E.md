# Módulo E — Programa / Syllabus (JSONB)

**Alcance auditado:** `Admin\ProgramaController` (1133 LOC, 20 métodos), `Administrativo\ProgramaController`
(351 LOC), `Ayudante\ProgramaController` (485 LOC), `Student\ProgramaController` (67 LOC),
`ProgramaPolicy`, `ProgramaService`, `StudentSyllabusPresenter`, rendering del syllabus en el frontend.

**Particularidad del módulo:** cuatro roles operan sobre el mismo recurso y las rutas se cruzan — las
rutas de `docente` apuntan a `Admin\ProgramaController` (`routes/web.php:421-424`) y las de `ayudante`
también (`:488`).

---

## 🔴 Crítico

### E-1 · `approve`/`reject` ignoran el programa que reciben
**Archivo:** `app/Policies/ProgramaPolicy.php:265-286`

```php
public function approve(Usuario $user, Programa $model): bool
{
    return $user->rolesAsignados()
        ->where('esta_activo', true)
        ->where('fue_eliminado', false)
        ->whereIn('nombre', ['Administrador', 'SuperAdmin', 'Super Admin', 'Admin', 'Jefe de Carrera'])
        ->exists();
}
```

La policy recibe `Programa $model` y **no lo consulta nunca**. No filtra por `id_contexto`, ni por
carrera, ni por `id_curso`. `reject()` es idéntica.

**Consecuencia:** un "Jefe de Carrera" de Medicina aprueba o rechaza el syllabus de cualquier carrera de
la universidad. Lo mismo cualquier "Administrador" acotado a un contexto.

Es especialmente grave aquí porque el syllabus aprobado es el documento académico oficial que ve el
estudiante, y `approve()` sella el estado `APROBADO` con `revisado_por = $user->id_usuario`: la firma
queda a nombre de quien no tenía competencia sobre esa carrera.

El resto de la policy (`view`, `update`, `delete`, `create`) **sí** verifica pertenencia real vía
`isAssignedDocente()`. El agujero está acotado a las dos operaciones de aprobación.

---

## 🟠 Alto

### E-2 · El control por secciones exige los 9 permisos incluso para el syllabus básico
**Archivo:** `Admin/ProgramaController.php:761-770`, invocado desde `store():260`

```php
private function validatePermissionsForAllSecciones($user, Curso $curso): void
{
    $secciones = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
    foreach ($secciones as $seccion) {
        $this->validatePermissionForSeccion($user, $seccion, $curso->id_contexto);
    }
}
```

`store()` la llama siempre, sea el syllabus BASICO (5 secciones) o COMPLETO (9). Un docente con permisos
sobre sólo las secciones que le corresponden — que es justo el propósito de
`CursoPermisosController::syllabusSync` y de la matriz `PermisosSyllabusMatriz.svelte` — recibe un 403 y
no puede guardar nada.

**Efecto de segundo orden:** el modelo de delegación granular es inutilizable en la práctica, lo que
empuja a conceder `CURSOS_PROGRAMAS_MODIFICAR_ALL` a todo el mundo y colapsa la granularidad que el
sistema fue diseñado para tener.

### E-3 · La regla padre `secciones` deja pasar el JSONB completo sin validar
**Archivo:** `Admin/ProgramaController.php:254-257`

```php
$validated = $request->validate([
    'secciones' => 'required|array',      // ← regla sobre la clave padre
    ...$validationRules,                   // ← reglas sobre secciones.I.contenido.*
]);
```

Cuando existe una regla sobre la clave padre, `validated()` devuelve **el subárbol completo** para esa
clave, no sólo las rutas con regla propia. `$validated['secciones']` conserva cualquier clave adicional
que envíe el cliente, y ese array va directo a `ProgramaService::generateProgramaWithSyllabus()` → columna
JSONB `data_syllabus`.

Resultado: escritura arbitraria de estructura y volumen en el JSONB. Sin límite de tamaño ni de
profundidad, es también un vector de agotamiento de almacenamiento.

**Fix:** eliminar la regla `'secciones' => 'required|array'` (las reglas hijas ya obligan a que exista) o
reconstruir explícitamente el array a partir de las claves validadas.

### E-4 · Asimetría de controles: el ayudante tiene el camino más laxo
**Archivo:** `Ayudante/ProgramaController.php:117-160`

El rol de menor privilegio del sistema escribe el syllabus con **menos verificaciones** que el docente
titular:

- **No pasa** por `validatePermissionsForAllSecciones()` — cero validación por sección.
- Su escritura se autoriza con un permiso de **lectura**: `CURSOS_PROGRAMAS_VER_TODOS` (`:148`), y el
  propio mensaje de error lo delata: `'No tienes permiso para editar el programa de este curso'`.

Misma clase que B-5 (permiso de lectura habilitando escrituras), agravada por la inversión de privilegio.

### E-5 · El listado carga el JSONB íntegro de cada programa para mostrar un porcentaje
**Archivo:** `Admin/ProgramaController.php:483-520`

`Programa::query()->with([...])` trae los modelos completos —incluida la columna `data_syllabus` de los
15 programas de la página— cuando el `map()` sólo emite 12 escalares más
`getCompletenessPercentage()`. Un syllabus completo son cientos de KB de JSONB.

Añadido: **8 consultas `COUNT` independientes** para el bloque de estadísticas (`:526-540`), cada una un
recorrido de tabla, en cada carga de la página.

### E-6 · Respuestas mixtas JSON/redirect en el mismo endpoint
`approve()` devuelve `response()->json([...], 422)` en los caminos de error (`:427, 433, 442`) y
`redirect()->route(...)` en el de éxito (`:462`). `store()` responde JSON siempre (`:305, 322`) aunque
sea una ruta POST de Inertia.

El cliente Inertia no interpreta un 422 con cuerpo JSON como errores de validación, así que estos fallos
no se muestran en el formulario. Mismo patrón en `Administrativo\ProgramaController` y
`Ayudante\ProgramaController`.

### E-7 · Mensajes de excepción interna devueltos al cliente
`'Error al generar el programa: ' . $e->getMessage()` (`store():322`) y
`response()->json(['error' => $e->getMessage()], 500)` (`approve():470`). Idéntico a D-8.

---

## 🟡 Medio

| # | Hallazgo | Ubicación |
|---|---|---|
| E-8 | **Dos convenciones de comparación de roles conviviendo.** Las policies usan `whereIn('nombre', ['Administrador', …])` — sensible a mayúsculas y a espacios; `Usuario::hasRole()` normaliza con `strtolower(trim())`. Un rol guardado como `administrador` satisface el middleware y no la policy. La lista incluye cuatro alias del mismo rol (`'SuperAdmin', 'Super Admin', 'Admin'`), síntoma de que el problema ya se manifestó. | `ProgramaPolicy.php:30-37, 265-286` |
| E-9 | `'total' => Programa::count()` sin `whereNull('fecha_eliminacion')`, mientras las otras 6 estadísticas sí lo filtran → el total no cuadra con la suma. | `Admin/ProgramaController.php:539` |
| E-10 | El controlador del estudiante pasa los modelos `asignatura` y `carrera` completos en vez de los campos necesarios. | `Student/ProgramaController.php:57-58` |
| E-11 | **Acoplamiento cross-rol en el enrutado**: rutas de `docente` y de `ayudante` apuntan a `Admin\ProgramaController`. La autorización efectiva depende de qué policy resuelva el controlador de otro rol, lo que hace muy difícil razonar sobre quién puede qué. | `routes/web.php:421-424, 488` |

---

## ✅ Verificado correcto

- **Cero XSS en la superficie más expuesta del sistema.** El syllabus es texto libre escrito por docentes,
  almacenado en JSONB y renderizado a estudiantes — el vector clásico de XSS almacenado. El proyecto
  tiene **0 `{@html}`** en estas vistas (el único del repo es el QR de 2FA), **0 `innerHTML`** y
  **0 `outerHTML`** en los 757 archivos frontend. Svelte auto-escapa toda interpolación. El riesgo está
  cerrado por construcción, no por casualidad.
- **Las policies se usan de verdad**: 26 llamadas a `authorize()` repartidas entre los cuatro
  controladores, frente a las **0** del Módulo D. Este módulo es el ejemplo a seguir dentro del proyecto.
- `approve()` valida la máquina de estados antes de sellar: estado `COMPLETO`, tipo de syllabus
  `COMPLETO`, y todas las secciones requeridas presentes — devolviendo la lista de las que faltan
  (`:427-443`).
- `ProgramaPolicy::view/update/delete/create` sí verifican pertenencia real al curso vía
  `isAssignedDocente()` → titular o docente de componente (`:46-63`), y `create()` niega explícitamente
  al rol Ayudante (`:219-221`).
- `Student\ProgramaController` exige inscripción activa (`estado_inscripcion = 'INSCRITO'`), delega en un
  presenter dedicado y sólo expone estados visibles para alumnos.
- `Ayudante\ProgramaController` verifica la asignación del rol **en el contexto exacto del curso**
  (`:135-145`) — el chequeo contextual correcto, el que falta en `IsAdmin` (D-2) y en
  `ProgramaPolicy::approve` (E-1) — y bloquea la edición de programas ya `APROBADO`.
- Validación exhaustiva y tipada por sección, con `in:` para los enumerados
  (`getValidationRulesForBasico/Completo`, `:848-972`).
- El filtro por tipo usa `whereJsonContains('data_syllabus->metadata->tipo_syllabus', $tipo)` — indexable
  con un índice GIN en PostgreSQL.

---

## 🔁 Patrones transversales confirmados

- **Rol comprobado sin contexto** (E-1) — tercera aparición, tras A-7 y D-2. Aquí es más llamativo porque
  la policy *recibe* el modelo contextual y decide no mirarlo.
- **Permiso de lectura autorizando escrituras** (E-4) — segunda aparición, tras B-5.
- **`$e->getMessage()` al cliente** (E-7) — segunda aparición, tras D-8.
