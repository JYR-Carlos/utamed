# Módulo F — Admin / Estructura académica y cursos

**Alcance auditado:** `FacultadController` (133), `DepartamentoController` (139), `CarreraController` (189),
`PlanController` (152), `AsignaturaController` (187), `AsignacionPlanController` (201),
`CursoController` (489), `ComponenteController` (663), `CourseTeamController` (723),
`InscripcionCursoController` (454) — ~3.3k LOC.

---

## 🔴 Crítico

### F-1 · Siete de diez controladores no invocan ninguna policy
**Recuento sobre `app/Http/Controllers/Admin/`:**

| Controlador | `authorize()` | Métodos públicos | Policy existente |
|---|---|---|---|
| `DepartamentoController` | **0** | 6 | `DepartamentoPolicy` ✔ |
| `CarreraController` | **0** | 7 | `CarreraPolicy` ✔ |
| `PlanController` | **0** | 6 | `PlanPolicy` ✔ |
| `AsignaturaController` | **0** | 5 | `AsignaturaPolicy` ✔ |
| `AsignacionPlanController` | **0** | 5 | `AsignacionPlanPolicy` ✔ |
| `CursoController` | **0** | 13 | `CursoPolicy` ✔ |
| `ComponenteController` | **0** | 9 | `ComponentePolicy` ✔ |
| `FacultadController` | 5 | 6 | `FacultadPolicy` ✔ |
| `CourseTeamController` | 7 | 6 | `CursoPolicy` ✔ |
| `InscripcionCursoController` | 10 | 13 | `InscripcionCursoPolicy` ✔ |

Las siete policies existen, están escritas y **nunca se llaman**. El único control sobre 51 métodos que
gestionan toda la estructura académica es el middleware `is_admin` — que según **D-2** admite el rol
"Administrador" otorgado en cualquier contexto.

Un administrador acotado a una facultad puede crear, editar y eliminar carreras, planes, asignaturas,
mallas, cursos y componentes de **toda la universidad**.

### F-2 · Los componentes se manipulan por ID global, sin curso ni autorización
**Archivo:** `routes/web.php:227-238`

```php
Route::put('cursos/componentes/{componente}', [AdminSeccionController::class, 'update']);
Route::delete('cursos/componentes/{componente}', [AdminSeccionController::class, 'destroy']);
Route::post('cursos/componentes/{componente}/docentes', [AdminSeccionController::class, 'addDocente']);
Route::delete('cursos/componentes/{componente}/docentes/{docenteComponente}', [...'removeDocente']);
Route::put('cursos/componentes/{componente}/titular', [AdminSeccionController::class, 'setTitular']);
Route::put('cursos/componentes/{componente}/genera-acta', [...'toggleGeneraActa']);
```

Seis rutas de escritura vinculadas **directamente al `{componente}`**, sin `{curso}` en la URL, sin
`authorize()` en el controlador y sin comprobación de pertenencia. No existe ámbito alguno: basta el ID
del componente.

El contraste está en el mismo archivo: `setTitularByDt` (`ComponenteController:375-392`), la variante
usada por la ruta de docente, **sí** hace las dos comprobaciones correctas —titular estricto y
componente↔curso—. El patrón correcto está escrito justo al lado del que no lo aplica.

### F-3 · "SuperAdmin" definido como "no tiene perfil docente"
**Archivo:** `CourseTeamController.php:669-672`

```php
// If it's a super admin, return all
if (!$user->docente) {
    return \App\Models\Usuario\Permiso::all();
}
```

El comentario dice super admin; la condición comprueba la ausencia de un registro `docente`. Cualquier
usuario sin perfil docente que supere `manageTeam` obtiene **todos los permisos del sistema** como
delegables, y a partir de ahí puede concederlos en el contexto del curso.

Encadenado con D-2, un administrador departamental delega permisos arbitrarios.

---

## 🟠 Alto

### F-4 · Inyección de fórmulas CSV en la exportación de inscripciones
**Archivo:** `InscripcionCursoController.php:409-440`

```php
$callback = function () use ($inscripciones) {
    $file = fopen('php://output', 'w');
    foreach ($inscripciones as $inscripcion) {
        fputcsv($file, [ …, $inscripcion->curso->nombre ?? '', …, $estudiante… ]);
    }
};
```

`fputcsv()` escapa comillas y separadores, pero **no neutraliza fórmulas**. Un valor que empiece por
`=`, `+`, `-` o `@` se ejecuta al abrir el archivo en Excel o LibreOffice.

La cadena de explotación está completa dentro del propio sistema: `UsuarioController::import` (D-7)
acepta nombres desde un `.xlsx` sin sanear, y esta exportación los devuelve al administrador. Un nombre
como `=cmd|'/c calc'!A1` viaja de la importación a la máquina del administrador.

Añadido: `getFiltered($filters, $idDocente, 999999)` materializa hasta un millón de filas en memoria
antes de emitir nada.

### F-5 · La ruta absoluta del servidor se filtra al margen de `APP_DEBUG`
**Archivo:** `CursoController.php:165-171`

```php
return response()->json([
    'error'       => 'Error al cargar el curso: ' . $e->getMessage(),
    'error_class' => get_class($e),
    'error_file'  => $e->getFile() . ':' . $e->getLine(),          // ← siempre
    'trace'       => config('app.debug') ? $e->getTraceAsString() : null,
], 500);
```

El `trace` sí está condicionado a `app.debug`, pero `error_file` —la ruta absoluta en el servidor— y
`error_class` se devuelven **incondicionalmente**, también en producción. Revela la estructura del
sistema de archivos y la organización interna del código.

### F-6 · La rama para docentes de `syncMemberPermissions` no puede funcionar
**Archivo:** `CourseTeamController.php:462-482`

El controlador contempla explícitamente que un docente sincronice roles `ayudante`/`estudiante`, y en esa
rama llama a:

```php
$usuario->giveRole($rol)->on($curso)->for(365)->save();
```

`RoleAssignmentBuilder::save()` → `validateActorAuthorization()` exige **SuperAdmin** y lanza
`DontHavePermissionException` en cualquier otro caso (ver D-1). La rama para docentes falla siempre.

Y el mismo controlador resuelve el problema de otra forma en `store()` (`:187-200`), donde inserta
directo con un comentario que documenta el desvío:

```php
// Direct insert — authorization already checked via authorize('manageTeam').
// Bypasses RoleAssignmentBuilder which only allows SuperAdmins.
```

Dos métodos del mismo archivo con criterios opuestos: uno funciona para docentes y el otro no.

### F-7 · `CursoController::show()` sobrecarga y ruido de depuración
**Archivo:** `CursoController.php:120-160`

`$curso->load(['inscripcionCursos.estudiante', …])` trae todas las inscripciones con sus estudiantes;
`TipoComponente::all()` sin filtro; y **ocho `Log::info` de traza** para una sola lectura
(`"iniciando carga"`, `"curso cargado exitosamente"`, `"CursoResource creado"`, …), residuo de una sesión
de depuración que quedó en el código.

---

## 🟡 Medio

| # | Hallazgo | Ubicación |
|---|---|---|
| F-8 | **Denylist en vez de allowlist para roles prohibidos**: `$rolesProhibidos = ['superadmin', 'super admin']`. E-8 ya demostró que en este sistema conviven al menos cuatro grafías del rol (`'SuperAdmin'`, `'Super Admin'`, `'Admin'`, `'Administrador'`); una variante no contemplada atraviesa el filtro. | `CourseTeamController.php:153-158` |
| F-9 | `'asignado_por' => (int) (Auth::id() ?? 1)` — tercera aparición del fallback de auditoría al usuario 1 (ver D-10). | `CourseTeamController.php:190, 193` |
| F-10 | `TipoComponente::all()` invocado en dos métodos del mismo controlador, sin caché ni `select()`. | `CursoController.php:71, 152` |
| F-11 | `'Error al crear el curso: ' . $e->getMessage()` devuelto al formulario — tercera aparición tras D-8 y E-7. | `CursoController.php:112` |
| F-12 | `AsignacionPlanController:127` registra el modelo completo en el log: `Log::info('AsignacionPlan created successfully:', $asignacion->toArray())`. | `AsignacionPlanController.php:127` |

---

## ✅ Verificado correcto

- **`CourseTeamController` es el mejor control de delegación del proyecto.** Reúne exactamente lo que le
  falta a `syncPermissions` (D-1):
  - `authorize('manageTeam', $curso)` en los 6 métodos, y `manageTeam` es estricto (solo titular actual o
    admin, con registro en el canal `seguridad`).
  - **Bloqueo de auto-modificación**: `'No puedes modificar tus propios permisos.'` (`:453`) — el control
    que corta de raíz la auto-escalada.
  - Restricción de roles según el tipo de actor: docente → solo `ayudante`; admin → todo salvo SuperAdmin
    (`:140-158`).
  - Permisos delegables acotados al contexto vía `getDelegablePermissionContextIds($idContexto)` y
    filtrados por `puede_delegar` / `puede_delegar_permiso`.
- `ComponenteController::setTitularByDt` (`:375-392`) aplica el patrón correcto: verifica titular estricto
  **y** pertenencia componente↔curso antes de delegar en `setTitular`.
- `InscripcionCursoController` (10 `authorize()`) y `FacultadController` (5) sí usan sus policies.
- **Sin inyección SQL**: el único `whereRaw` del módulo usa binding parametrizado —
  `Rol::whereRaw('LOWER(nombre) = ?', [strtolower($validated['role_name'])])` (`:162`).
- `CursoController::show()` transforma la salida con `CursoResource` y `ComponenteResource` en lugar de
  volcar modelos — el proyecto tiene 11 API Resources en `app/Http/Resources/` y aquí sí se usan.
- La restricción de solapamiento de roles se apoya en constraints de PostgreSQL, no en lógica PHP.

---

## 🔁 Patrones transversales confirmados

- **Policies escritas y no invocadas** — segunda aparición y la más masiva: 7 controladores, 51 métodos,
  7 policies sin usar (tras D-2, donde eran 2 controladores).
- **El control correcto convive con el incorrecto en el mismo archivo** — `setTitularByDt` vs los otros
  8 métodos de `ComponenteController`; `store()` vs `syncMemberPermissions()` en `CourseTeamController`.
  Tercera aparición del patrón tras B-1 y D-1.
- **`$e->getMessage()` al cliente** (F-5, F-11) — tercera aparición.
- **`Auth::id() ?? 1`** (F-9) — tercera aparición.
