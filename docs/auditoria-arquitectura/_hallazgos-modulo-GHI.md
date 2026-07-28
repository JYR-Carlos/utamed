# Módulos G + H + I — Asistencia/Calendario/Mensajería · Jefe de Carrera · Ayudante

**Alcance auditado:**
- **G** — `AsistenciaController` (337), `CalendarioController` (119), `MensajesController` (316),
  `CursoPermisosController` (277), `DelegacionPermisosController` (259), `DocenteUnidadController` (157),
  `DashboardController` (118).
- **H** — `JefeCarreraController` (779), `JefeCarrera\{PlanController, AsignaturaController,
  AsignacionPlanController, CarreraController}` (582), `ResolvesJefaturaCarrera` (105).
- **I** — `Ayudante\{CourseController, DashboardController, ProgramaController}` (~690).

> **Nota metodológica:** el recuento de `authorize()` es engañoso en este bloque. Los controladores de
> Jefe de Carrera y Asistencia tienen 0 llamadas a `authorize()` pero aplican guards equivalentes y
> consistentes mediante traits y helpers privados. Se verificó método por método.

---

## 🔴 Crítico

### GHI-1 · El Jefe de Carrera aprueba y rechaza programas de cualquier carrera
**Archivo:** `Docente/JefeCarreraController.php:394-424` (`aprobarPrograma`) y `:431-470` (`rechazarPrograma`)

```php
public function aprobarPrograma(int $programaId): RedirectResponse
{
    $jefaturaCheck = $this->jefaturaOrRedirect();          // ① resuelve la jefatura
    if ($jefaturaCheck instanceof RedirectResponse) return $jefaturaCheck;

    $programa = Programa::findOrFail($programaId);          // ② ID arbitrario de la URL
    $this->authorize('approve', $programa);                 // ③ policy que ignora $programa (E-1)

    // ④ …y nunca se comprueba que el programa pertenezca a $jefaturaCheck['carrera_id']
    $programa->update(['estado' => 'APROBADO', 'revisado_por' => $user->id_usuario]);
}
```

`$jefaturaCheck` se resuelve, se comprueba contra el redirect y **se descarta**. El `carrera_id` nunca se
usa para acotar. Sumado a E-1 (la policy `approve` no mira el modelo), el resultado es que
`POST /docente/jefe-carrera/programas/{cualquier_id}/aprobar` funciona sobre cualquier programa de la
universidad.

**Lo que convierte esto en un hallazgo y no en un descuido aislado:** el mismo controlador, 60 líneas más
arriba, lo hace bien para la lectura:

```php
// programaPreview() :337-341
->whereHas('asignacionPlan.plan', fn($q) => $q->where('id_carrera', $jefatura['carrera_id']))
…
if (!$programa) abort(403, 'El programa no pertenece a tu carrera');
```

**La previsualización está acotada; la aprobación y el rechazo no.** Es la ruta explotable concreta de
E-1, y afecta al acto de firma del documento académico oficial (`revisado_por`).

**Fix:** reutilizar en `aprobarPrograma`/`rechazarPrograma` el mismo `whereHas` de `programaPreview`.

---

## 🟠 Alto

### GHI-2 · N+1 de permisos en el listado de cursos del ayudante
**Archivo:** `Ayudante/CourseController.php:50-52`

```php
$cursosData = $cursosInscritos->map(function ($curso) use ($user) {
    $userPermissions = $user->getAllPermissions($curso->id_contexto);   // 1 query por curso
```

Cada llamada consulta `vw_permisos_usuario`. Es la misma forma que A-5, y existe la solución en el propio
proyecto: `UserCoursesService::getAyudanteCourses()` ya resuelve exactamente esto con
`getAllPermissionsGroupedByContext()` en una sola consulta. Aquí no se reutiliza.

---

## 🟡 Medio

| # | Hallazgo | Ubicación |
|---|---|---|
| GHI-3 | **`resolveJefatura()` toma solo una jefatura.** `->latest('id_ura')->first()` — un docente que sea Jefe de Carrera de dos carreras queda acotado silenciosamente a la más reciente, sin aviso ni selector. Limitación funcional no documentada. | `ResolvesJefaturaCarrera.php:28-36` |
| GHI-4 | `Ayudante\ProgramaController` no invoca ninguna policy en sus 4 métodos públicos; se apoya en comprobaciones manuales. Funcionan (ver ✅) pero quedan fuera del sistema de autorización del framework. | `Ayudante/ProgramaController.php` |
| GHI-5 | `AsistenciaController::centro` replica la forma de `centroCalificaciones` (B-8): mapa anidado curso→componente. Aquí el eager loading está bien resuelto, pero la lógica está duplicada entre dos controladores. | `AsistenciaController.php:43-105` |
| GHI-6 | El rol se busca por nombre literal `where('nombre', 'Jefe de Carrera')` — sensible a mayúsculas y espacios, mientras `Usuario::hasRole()` normaliza. Cuarta convención distinta de comparación de roles en el proyecto (ver E-8). | `ResolvesJefaturaCarrera.php:32` |

---

## ✅ Verificado correcto

Este bloque contiene **el mejor código de autorización del proyecto**. Merece figurar en el reporte como
el patrón de referencia interno.

### `JefeCarrera\PlanController` — la implementación modelo

```php
public function update(Request $request, Plan $plan)
{
    $carreraId = $this->carreraIdOrAbort();        // ① ¿tiene jefatura?
    $this->assertPlanDeCarrera($plan, $carreraId); // ② ¿el recurso es suyo?
    $validated = $request->validate([...]);
    $validated['id_carrera'] = $carreraId;         // ③ fuerza el ámbito, ignora lo entrante
    $plan->update($validated);
}
```

Los tres controles que faltan en otros módulos, juntos y en el orden correcto. El comentario del código
lo dice explícito: *"Forzar la carrera del jefe (se ignora cualquier id_carrera entrante)"* y *"No
permitir mover el plan a otra carrera"*. Hasta el selector de carrera de la vista queda bloqueado
(`:44`). Es la defensa contra mass assignment bien hecha.

El patrón se repite consistentemente: `AsignaturaController` (`assertAsignaturaDeCarrera` en update y
destroy), `AsignacionPlanController` (`assertPlanDeCarrera` en los 5 métodos), `CarreraController`
(`jefaturaOrAbort`).

### `ResolvesJefaturaCarrera` — resolución contextual correcta

Resuelve la jefatura desde una `UsuarioRolAsignacion` activa, con rol *Jefe de Carrera*, sobre un contexto
de categoría `carrera`, y devuelve el `carrera_id` concreto. Es exactamente la comprobación contextual
que le falta a `IsAdmin` (D-2) y a `ProgramaPolicy::approve` (E-1). Además distingue con criterio entre
`jefaturaOrAbort()` (endpoints JSON → 403) y `jefaturaOrRedirect()` (vistas Inertia → redirect con flash),
que es el manejo correcto de la diferencia entre ambos tipos de respuesta — justo lo que E-6 hace mal.

### `AsistenciaController` — el guard con la polaridad correcta

```php
private function autorizarComponente(Curso $curso, Componente $componente): void
{
    if (!$docente) abort(403, ...);
    if ($componente->id_curso !== $curso->id_curso) abort(404, ...);   // ← if (!coincide) abort
    if (!$esTitular && !$esAsignado) abort(403, ...);
}
```

Invocado por los **5** métodos (`index`, `store`, `update`, `destroy`, y el listado). Escrito como
`if (!coincide) abort` — la polaridad correcta, la que evita el fallo silencioso de B-6/C-1/C-7.

### Bloqueo de auto-delegación (segunda aparición)

`DelegacionPermisosController:201` — `abort(422, 'No puedes delegarte permisos a ti mismo.')`, más
verificación de pertenencia al equipo docente (`:218`) y `authorize('manageTeam')` en ambos métodos. Junto
con `CourseTeamController` (F), son los dos únicos sitios del proyecto con este control — y son
precisamente los que `syncPermissions` (D-1) necesitaba.

### Otros

- `CursoPermisosController::authorizeEsTitularComponente` (`:208-222`) exige `es_titular = true` sobre el
  componente concreto — granularidad correcta, no "cualquier docente del curso".
- `MensajesController::send` (`:249-260`) exige titular estricto del curso **y** actividad↔curso.
- `MensajesController::index` (`:46-90`) es el contraejemplo de B-8: `select()` explícito y agregados
  batcheados con `whereIn($cursoIds)` en vez de consultar dentro de un bucle. Así debería reescribirse
  `centroCalificaciones`.
- `Ayudante\CourseController::show` (`:97-105`) verifica la asignación del rol ayudante **en el
  `id_contexto` exacto del curso** antes de mostrarlo.
- `CalendarioController` acota todas las consultas a los cursos donde el docente participa (titular o
  componente).
- **Auditoría vía triggers de PostgreSQL**: `aprobarPrograma`/`rechazarPrograma` propagan actor, tipo de
  acción y razón con `set_config('app.actor_id', …)` dentro de la transacción, para que los triggers
  escriban `ProgramaHistorial`. Es un diseño de auditoría sólido — y contrasta con el
  `Auth::id() ?? 1` de D-10/F-9.

---

## 🔁 Conclusión sobre los patrones transversales

Este bloque cierra la auditoría confirmando la tesis que venía apuntando desde el Módulo E: **todos los
controles que faltan en los módulos A-F están implementados correctamente en algún otro punto del mismo
proyecto.**

| Control ausente en… | …está bien implementado en |
|---|---|
| `assertActividadDeCurso` omitido (B-1) | `assertPlanDeCarrera`, `assertAsignaturaDeCarrera` (H) |
| Rol sin contexto (D-2, E-1) | `ResolvesJefaturaCarrera::resolveJefatura` (H) |
| Sin bloqueo de auto-escalada (D-1) | `DelegacionPermisosController:201`, `CourseTeamController:453` |
| Guard falsy (B-6, C-1, C-7) | `AsistenciaController::autorizarComponente` (G) |
| Mass assignment en JSONB (E-3) | `$validated['id_carrera'] = $carreraId` (H) |
| N+1 de permisos por curso (A-5, GHI-2) | `getAllPermissionsGroupedByContext()` (A) |
| Respuestas JSON/redirect mezcladas (E-6) | `jefaturaOrAbort()` vs `jefaturaOrRedirect()` (H) |
| Auditoría con fallback a usuario 1 (D-10) | `set_config('app.actor_id', …)` (H) |

El problema del proyecto no es desconocimiento técnico: es **aplicación desigual de patrones que el
equipo ya domina**. Eso cambia radicalmente la naturaleza del plan de acción — no hay que diseñar
soluciones, hay que propagar las existentes.
