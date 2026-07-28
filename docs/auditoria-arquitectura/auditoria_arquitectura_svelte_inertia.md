# 🏗️ Reporte de Auditoría Arquitectónica: Svelte + Inertia

> **Proyecto:** UTAMED · **Rama auditada:** `admin` · **Fecha:** 28 de julio de 2026
> **Stack:** Laravel 12 (PHP 8.2) · Inertia 2 · Svelte 5 (runes) · Tailwind 4 · Vite 7 · PostgreSQL · Fortify + 2FA
> **Alcance:** 9 módulos de negocio · ~13.7k LOC de controladores · 465 `.svelte` · 292 `.ts` · **86 hallazgos**

---

## 1. Resumen Ejecutivo

El sistema tiene una **arquitectura frontend sana y un backend con autorización aplicada de forma desigual**. Esa asimetría es la conclusión central del informe y condiciona todo el plan de acción.

El frontend está, con diferencia, en mejor estado. Sobre 757 archivos: cero `console.log`, un único `{@html}` (el QR de 2FA generado en servidor), cero `innerHTML`, solo dos archivos que usan `svelte/store` —el resto se apoya en props de Inertia y runes, que es exactamente el patrón correcto— y uso disciplinado de recargas parciales `router.reload({ only: [...] })` contra props `Inertia::lazy`. La protección CSRF está intacta en todo el proyecto: no existe una sola exención.

El backend es otra historia. **El problema no es desconocimiento técnico: es aplicación desigual de patrones que el propio equipo ya domina.** Cada control ausente en los módulos críticos está correctamente implementado en otro punto del mismo repositorio, a veces en el mismo archivo y a sesenta líneas de distancia. Esto es una buena noticia para la remediación: no hay que diseñar soluciones, hay que propagar las existentes.

### Los cinco riesgos que exigen acción inmediata

| # | Riesgo | Impacto |
|---|---|---|
| **1** | **Escalada a SuperAdmin.** `UsuarioController::syncPermissions` escribe roles directamente en el contexto global esquivando `RoleAssignmentBuilder`, cuyo guard exige SuperAdmin. El middleware `IsAdmin` admite el rol "Administrador" otorgado en *cualquier* contexto. | Un administrador departamental se concede SuperAdmin global |
| **2** | **Fuga de credenciales en cada respuesta.** No existe `$hidden` en ninguno de los 44 modelos; `share()` global y varios endpoints devuelven modelos `Usuario` completos, con `passhash` y `token_recuerdame_sesion`. | Hash bcrypt y token de sesión en el DOM, el historial y cualquier proxy |
| **3** | **IDOR entre cursos y carreras.** Faltan comprobaciones de pertenencia en notas, descarga de entregas, aprobación de syllabus y actividades de estudiante. | Alteración de notas ajenas, descarga de cualquier entrega, firma de syllabus de otras carreras |
| **4** | **Motor de permisos con un bug de corrección y sin caché.** `checkSpecialPermission` decide GRANT/DENY por el orden de filas de PostgreSQL; `getContextsFromPermission` lanza `TypeError` siempre. Todo el cacheado son comentarios `TODO`. | Un DENY explícito puede ignorarse; ~13 consultas por comprobación de permiso |
| **5** | **Controles de subida declarados pero inexistentes.** El antivirus es un stub vacío que aprueba todo incluso con el flag activado; el nombre del archivo en disco lo controla el cliente. | Falsa sensación de protección; extensión arbitraria en almacenamiento |

### Salud por módulo

| Módulo | Hallazgos | Estado |
|---|---|---|
| A · Núcleo transversal | 13 | 🔴 Crítico — contamina todos los demás |
| B · Docente: actividades y notas | 16 | 🔴 Crítico |
| C · Estudiante: agenda y archivos | 13 | 🔴 Crítico |
| D · Admin: usuarios y permisos | 15 | 🔴 Crítico — el más grave |
| E · Programa/Syllabus JSONB | 11 | 🟠 Alto — buena higiene, un agujero concreto |
| F · Estructura académica | 12 | 🟠 Alto |
| G+H+I · Asistencia, Jefe de Carrera, Ayudante | 6 | 🟢 Aceptable — código de referencia interno |

---

## 2. Hallazgos Críticos (Ciberseguridad y Base de Datos)

### Fugas de Datos / Overfetching

**A-1 · El hash de contraseña viaja al navegador en cada respuesta Inertia**
`app/Http/Middleware/HandleInertiaRequests.php:214-217`

```php
'auth' => [
    'user'       => $user,        // ← modelo Eloquent completo
    'docente'    => $docente,
    'estudiante' => $estudiante,
```

`app/Models/Base/Usuario/BaseUsuario.php:30-42` declara `passhash` y `token_recuerdame_sesion` en `$fillable`, y **no existe `$hidden` en ningún modelo del proyecto** (`grep -rn '\$hidden' app/Models/` → 0 resultados). `toArray()` serializa todas las columnas: el hash bcrypt y el token de "recordarme" quedan en el atributo `data-page` del HTML inicial y en cada JSON de navegación.

Lo que oculta el problema: el tipo TypeScript `resources/js/types/index.d.ts:79-93` declara solo campos benignos, de modo que desde el frontend nada delata la fuga.

**D-3 · Endpoint dedicado que devuelve el hash por RUT**
`app/Http/Controllers/Admin/UsuarioController.php:885-897`

```php
public function buscarPorRut(Request $request)
{
    $usuario = Usuario::where('rut', $request->query('rut'))->first();
    return response()->json($usuario);        // modelo completo
}
```

Consumido en producción por `resources/js/pages/admin/Usuarios.svelte:201`. A diferencia de A-1, esto es **enumerable y dirigido**: el RUT chileno es predecible y validable.

**D-5 · El listado de usuarios entrega 15 hashes por página**
`UsuarioController.php:81, 129, 592-608` — `Estudiante::with(['usuario', …])` y `response()->json($usuario)` en `show()`.

**D-6 · `per_page` sin tope superior**
`UsuarioController:125`, `CursoController:67`, `DepartamentoController:50`, `FacultadController:45`, `PlanController:50`, `InscripcionCursoController:62`. Combinado con D-5, `?per_page=1000000` vuelca la base de usuarios completa en una petición.

**A-5 · El `share()` global viaja íntegro en cada navegación**
`app/Services/UserCoursesService.php` — los cursos, permisos y flags de programa se calculan y envían en **todas** las respuestas GET, sin `Inertia::optional()`, aunque la página no los necesite y aunque sea una recarga parcial.

**E-5 · El listado de syllabus carga el JSONB completo para mostrar un porcentaje**
`Admin/ProgramaController.php:483-520` — `Programa::query()->with([...])` trae los modelos íntegros, incluida `data_syllabus` (cientos de KB por programa), de los 15 registros de la página, cuando el `map()` solo emite 12 escalares.

**B-11 · Modelos completos frente a mapeo explícito, en el mismo archivo**
`DocenteActivityController.php:122-128` usa `array_merge($curso->toArray(), …)` y envía `$actividades`, `$componentes` y `$unidades` sin `select()`; `showEvaluacion` (`:461-483`) del mismo controlador mapea campo por campo. Dos criterios opuestos conviviendo.

**Otros:** RUT de estudiantes en los JSON de entregas (`B-10`, `:943, :991`) · email de todos los compañeros de grupo (`C-11`, `ActivityController:85`) · `fn() => TipoComponente::all()` como respuesta HTTP (`A-12`, `routes/web.php:103`) · modelos `asignatura` y `carrera` completos al estudiante (`E-10`).

---

### Vulnerabilidades (XSS, CSRF, Auth)

#### 🟢 Lo que está protegido — y conviene no tocar

**CSRF: intacto.** Cero `validateCsrfTokens(except:)` y cero `$except` en todo el proyecto. Todas las mutaciones pasan por el grupo `web` e Inertia adjunta `X-XSRF-TOKEN` automáticamente. Los pocos `fetch()` nativos son GET, llevan `credentials: 'same-origin'` y están documentados como tales; las mutaciones usan `router.post` con comentario explícito sobre CSRF (`docente/Activities/Index.svelte:382`).

**XSS: superficie prácticamente nula.** El syllabus —texto libre escrito por docentes, guardado en JSONB y renderizado a estudiantes— es el vector canónico de XSS almacenado. El proyecto tiene **0 `{@html}` en esas vistas** (el único del repositorio es el QR de 2FA generado por Fortify), **0 `innerHTML`** y **0 `outerHTML`** en los 757 archivos frontend. Svelte auto-escapa toda interpolación. El riesgo está cerrado por construcción.

**Rate limiting** de login (5/min en producción, por rut+ip) y de 2FA correctamente configurados (`FortifyServiceProvider.php:150-161`).

#### 🔴 Escalada de privilegios

**D-1 + D-2 · Cadena a SuperAdmin**

El sistema tiene dos caminos para asignar roles. Uno está endurecido y el otro no.

```php
// Camino protegido — RoleAssignmentBuilder::validateActorAuthorization() :207-226
if ($this->validator->isSuperAdmin($this->actor)) return;
throw new DontHavePermissionException(... 'Solo los administradores pueden asignar roles.');

// Camino sin proteger — UsuarioController::syncPermissions :1002-1022
$idContexto = app(GlobalContextService::class)->getContextId();   // ← contexto GLOBAL
foreach ($validated['roles'] as $rolId) {
    UsuarioRolAsignacion::updateOrCreate([...]);                   // escritura directa
}
```

Sin `authorize()`, sin comprobación de SuperAdmin y con los roles validados como simple `'roles' => 'array'` (sin `exists:rol,id_rol`).

El amplificador es `IsAdmin.php:41-43`:

```php
$isAdmin = $user->hasRole('SuperAdmin') || $user->hasRole('Administrador') || $user->isSuperAdmin();
```

`Usuario::hasRole()` invoca `getAllRoles(null)` — **sin filtro de contexto** (`Usuario.php:263-280`). Un "Administrador" acotado a una facultad pasa el middleware y llega a `sync-permissions`.

**El dato que resume el módulo:** `grep -n "authorize\|Gate::\|policy("` sobre `UsuarioController` y `AssignmentWizardController` devuelve **0 resultados**, mientras `UsuarioPolicy`, `UsuarioRolAsignacionPolicy` y `UsuarioPermisoEspecialPolicy` existen sin usar.

**F-1 · Siete de diez controladores admin sin ninguna policy**

| Controlador | `authorize()` | Métodos | Policy existente |
|---|---|---|---|
| Departamento, Carrera, Plan, Asignatura, AsignacionPlan, Curso, Componente | **0** | 51 | Las 7 existen ✔ |
| Facultad / CourseTeam / InscripcionCurso | 5 / 7 / 10 | 25 | ✔ |

**F-2 · Componentes manipulables por ID global.** Seis rutas de escritura (`routes/web.php:227-238`) vinculadas a `{componente}` sin `{curso}` en la URL, sin `authorize()` y sin comprobación de pertenencia.

**F-3 · "SuperAdmin" definido como "no tiene perfil docente"** — `CourseTeamController.php:669-672`:

```php
// If it's a super admin, return all
if (!$user->docente) {
    return \App\Models\Usuario\Permiso::all();
}
```

**D-4 · `changePassword` sin autorización ni política de contraseña.** `min:6`, sin `Password::defaults()`, sin confirmar la clave del administrador, sin invalidar las sesiones del usuario afectado y sin registro de auditoría. Alcanza a cualquier usuario, incluido un SuperAdmin.

#### 🔴 IDOR entre cursos y carreras

**B-1 · Notas y descarga de entregas sin comprobar actividad↔curso.** El controlador define el helper correcto en `DocenteActivityController.php:49` y lo invoca en 9 métodos. **Tres no lo llaman**, y son los sensibles:

| Método | Línea | grupo↔actividad | actividad↔curso |
|---|---|---|---|
| `updateIntegrante` | 601 | ✅ | ❌ |
| `recalcularNotasIndividuales` | 632 | ✅ | ❌ |
| `descargarEntrega` | 1003 | ✅ | ❌ |

```
PUT /docente/cursos/{MI_CURSO}/actividades/{ACT_AJENA}/grupos/{G}/integrantes/{I}
GET /docente/cursos/{MI_CURSO}/actividades/{ACT_AJENA}/grupos/{G}/entregas/{AGENDA}/descargar
```

**C-1 · Un GET auto-inscribe al estudiante en cursos ajenos.** `Student/ActivityController.php:31-64` verifica inscripción en `$curso` y `visible`, pero no que la actividad pertenezca al curso. Y en `:62`:

```php
if (!$actividad->es_grupal) {
    (new GrupoIndividualService())->asegurarGrupo($actividad, $estudiante->id_estudiante);
}
```

`asegurarGrupo` **crea** el `IntegranteGrupo`. Como `AgendaController` autoriza por pertenencia a `IntegranteGrupo` (`:57-59`, `:90-98`), el estudiante queda habilitado para enviar mensajes y subir entregas en un curso donde no está inscrito. Una lectura fabrica la autorización de dos escrituras.

**E-1 + GHI-1 · Aprobación de syllabus sin ámbito.** `ProgramaPolicy.php:265-286` recibe `Programa $model` y **nunca lo consulta**:

```php
public function approve(Usuario $user, Programa $model): bool
{
    return $user->rolesAsignados()
        ->whereIn('nombre', ['Administrador', 'SuperAdmin', 'Super Admin', 'Admin', 'Jefe de Carrera'])
        ->exists();
}
```

La ruta explotable está en `JefeCarreraController.php:394-424`, que resuelve la jefatura y la descarta:

```php
$jefaturaCheck = $this->jefaturaOrRedirect();   // ① resuelve…
$programa = Programa::findOrFail($programaId);  // ② ID arbitrario
$this->authorize('approve', $programa);         // ③ policy que ignora $programa
// ④ …carrera_id nunca se usa para acotar
```

**En el mismo controlador**, `programaPreview` (`:337-341`) sí lo acota con `whereHas('asignacionPlan.plan', …id_carrera)`. La previsualización está protegida; la firma del documento oficial no.

**B-2 / B-3 / B-4 · `exists:` usado como control de pertenencia.** `storeRubrica` (`:497-524`) escribe la rúbrica de cualquier actividad; `storeEvaluacion` (`:1172, :1235-1238`) cierra rúbricas ajenas; `store`/`update` (`:154-155, :218-219`) aceptan `id_componente` e `id_unidad` de otros cursos, contaminando el árbol de contextos vía el trigger `tr_actividad_pre_insert`.

#### 🔴 Corrección del motor de permisos

**A-2 · El GRANT/DENY efectivo se elige por el orden de filas de la BD**
`PermissionValidator.php:511-533`

```php
foreach ($results as $result) {              // ①
    $transformedSlugs[] = $castedPermission;
}
foreach ($transformedSlugs as $slug) {       // ②
    $priority = WildcardMatcher::getPriority($permission, $slug);
    if ($priority < $bestPriority) {
        $bestPriority = $priority;
        $bestResult = $result;               // ← $result es residuo del bucle ①
    }
}
```

`$bestResult` recibe siempre la última fila devuelta por PostgreSQL, sin relación con el `$slug` evaluado. Con `cursos:*` (GRANT) y `cursos/notas:*` (DENY) en el mismo contexto, gana la fila que devuelva el motor sin `ORDER BY`. Un DENY explícito puede quedar ignorado.

**A-3 · `getContextsFromPermission()` lanza `TypeError` siempre** — `:154`:

```php
$allGranted = array_unique(...$grantedContextsUPE, ...$grantedContextsURA);
```

El spread de dos arrays de enteros invoca `array_unique(1, 5, 7, …)`. **Fix:** `array_unique(array_merge($a, $b))`.

#### 🟠 Patrón de guard defectuoso (3 apariciones)

`if ($relacion) { comprobar }` en lugar de `if (!coincide) { abort }`. Cuando la relación es nula, el control **desaparece en silencio**:

- `DocenteActivityController:204, 269` (B-6) — `update` y `destroy` continúan si la actividad no tiene componente
- `Student/ActivityController:31-64` (C-1)
- `AgendaController:101` (C-7) — la fecha límite de entrega se omite entera

#### 🟠 Subida de archivos

**C-2 · El antivirus es un stub vacío** — `AbstractArchiveService.php:339-359`:

```php
protected function scanForViruses(UploadedFile $file, string $archiveId): void
{
    if (!config('files.validation.virus_scan_enabled')) return;
    // TODO: …todo el cuerpo comentado…
}
```

`VirusDetectedException` no se lanza nunca, el `catch` de `AgendaController:148` es código muerto y el docblock afirma que el handler hace "validación, **antivirus**, compresión y almacenamiento". Peor que no tenerlo: activar el flag da una falsa sensación de protección.

**C-3 · El nombre en disco lo controla el usuario** — `AbstractArchiveService.php:567`. `nombre_archivo` solo se valida con `regex:/^[\pL\pN\s._\-]+$/u`, que bloquea `/` y `\` pero **permite cualquier extensión**. Mitigado hoy porque `local_archives` apunta fuera del docroot.

**C-4 · El límite de tamaño por tipo se evade** cruzando extensión de una categoría con MIME de otra: las reglas `extensions:`/`mimetypes:` son uniones planas, pero el closure de tamaño exige coincidencia dentro de la misma categoría y **retorna sin validar** si no encuentra ninguna (`FileRequirementBuilder.php:83-107`).

**C-8 · El allowlist se apaga por variable de entorno** — `FILES_ENABLE_MIME_VALIDATION=false` (`config/filetypes.php:50-51`).

#### 🟠 Inyección de fórmulas CSV — con la cadena completa dentro del sistema

**F-4 ·** `InscripcionCursoController.php:409-440` usa `fputcsv()`, que escapa comillas pero **no neutraliza fórmulas**. Un valor que empiece por `=`, `+`, `-` o `@` se ejecuta al abrir el archivo. La cadena se cierra sola: `UsuarioController::import` (D-7) acepta nombres desde `.xlsx` sin sanear, y esta exportación se los devuelve al administrador.

#### 🟡 Exposición de información y auditoría

- **`$e->getMessage()` devuelto al cliente** — 3 apariciones: `UsuarioController:317`, `Admin/ProgramaController:322, :470`, `CursoController:112`.
- **Ruta absoluta del servidor al margen de `APP_DEBUG`** — `CursoController.php:165-171`: el `trace` sí está condicionado, pero `error_file` y `error_class` se devuelven siempre.
- **PII en logs** — RUT en claro + IP en cada intento de login, más `username` y `passhash_length` (`FortifyServiceProvider.php:57-61, 76-80`). Canal `single`, sin rotación. El RUT es dato personal bajo Ley 19.628.
- **Auditoría falsificable** — `Auth::id() ?? 1` en 3 sitios (`UsuarioController:998`, `CourseTeamController:190, 193`): si la sesión es nula, la acción se atribuye al usuario 1.
- **Cookies de sesión sin `Secure`** — `config/session.php:172` resuelve a `null` → `false`; ni `.env` ni `.env.example` definen `SESSION_SECURE_COOKIE`, y `.env.example` se distribuye con `APP_DEBUG=true`.

---

### Consultas N+1 y Rendimiento DB

**A-4 · El motor de permisos no tiene caché: ~13 consultas por comprobación**

Todo el cacheado son comentarios `TODO` (`PermissionValidator.php:42-43, 142, 157, 486, 503, 536, 542, 560, 573`). Cada `validate()` ejecuta:

- 1 consulta `isSuperAdmin` (`:274`)
- 1 llamada a la función recursiva `fn_obtener_ids_contexto_ancestros` **por contexto** (`ContextResolver.php:201`, tampoco cacheada)
- 1 consulta UPE (`:494`) + 1 URA (`:565`) **por cada contexto de la cadena**

Un permiso sobre un Curso expande a curso→carrera→plan→global: **≈13 consultas por permiso comprobado**.

**A-5 · El sidebar cuesta 2 consultas por curso, en cada navegación**
`UserCoursesService.php`:

```php
'tiene_programa' => Programa::where('id_curso', $curso->id_curso)->exists(),                  // N+1
'permisos'       => $this->getPermissionsForContext($docente->usuario, $curso->id_contexto),  // N+1
```

Un docente con 8 cursos paga 16 consultas **solo para pintar el sidebar**, en cada GET.

**A-6 · `hasRole()` ignora el eager loading.** `Usuario.php:263` usa `$this->rolesAsignados()` (query builder) en vez de la relación ya cargada por `HandleInertiaRequests.php:164`. La ruta `/dashboard` encadena `hasAnyRole` + `hasRole`×4 → 5 consultas antes de llegar al controlador.

**B-7 · Reparación de datos ejecutándose como lectura.** `DocenteActivityController.php:406-408` invoca `GrupoIndividualService::asegurarGruposDelCurso` en **cada GET** de la pantalla de evaluación: recorre todos los inscritos y por cada uno lanza un `whereHas` más hasta dos INSERT. Un curso de 60 estudiantes = 60+ consultas por carga. `C-13` es la variante por estudiante (`ActivityController:62`).

**B-8 · N+1 anidado en el centro de calificaciones.** `DocenteActivityController.php:346` — `Actividad::where('id_componente', …)` dentro del `map()` sobre componentes, dentro del `map()` sobre cursos.

**B-9 · Doble round-trip en cada mutación.** `docente/Activities/Index.svelte:294, 304, 413, 685`:

```js
router.put(url, data, { onSuccess: () => router.reload({ only: ['grupos'] }) });
```

El controlador ya responde `redirect()->back()`, que re-renderiza con props frescos. La recarga adicional **vuelve a ejecutar `showEvaluacion()` completo**, incluido el bucle de B-7. Cada ajuste de una décima dispara dos ciclos de esa pantalla.

**E-5 · 8 `COUNT` independientes** por carga del listado de programas (`Admin/ProgramaController:526-540`), cada uno un recorrido de tabla.

**Otros:** `Programa::exists()` dentro de `formatCurso` (`C-12`, `Student/CourseController:74-77`) · `getAllPermissions()` dentro del `map()` de cursos del ayudante (`GHI-2`, `Ayudante/CourseController:50-52`) · conexión PDO forzada en el boot de **toda** petición, incluidas `/up` y assets (`A-11`, `AppServiceProvider.php:44-45`) · importación masiva sin chunking ni cola, todo en memoria y en una sola transacción (`D-7`).

> **Existe la solución dentro del proyecto.** `UserCoursesService::getAyudanteCourses()` ya resuelve el N+1 de permisos con `getAllPermissionsGroupedByContext()` en una sola consulta, y `MensajesController::index` (`:46-90`) agrega con `whereIn($cursoIds)` en vez de consultar dentro de un bucle. Son los modelos a replicar en A-5, B-8 y GHI-2.

---

## 3. Oportunidades de Refactorización en Frontend (Svelte)

### Manejo de Estado (Stores vs Props)

**El diagnóstico es favorable y conviene decirlo con claridad: no hay abuso de stores globales.** Solo **2 archivos** de todo el frontend importan `svelte/store` (`useFilteredList.ts` y `usePermissions.ts`), y ambos lo hacen para *leer* el store `page` de Inertia, no para mantener estado paralelo. Las páginas reciben props tipadas y usan `$props()`, `$state()` y `$derived()`. Hay **cero usos de `$:` legacy** en las páginas de docente, estudiante y admin. La arquitectura de estado es la correcta para Inertia: el servidor es la fuente de verdad y el cliente no la duplica.

Los defectos son puntuales:

**A-8 · `usePermissions()` no es reactivo pese a documentarse como tal**
`resources/js/lib/composables/usePermissions.ts:48-66`

```js
const userPermissions = ((): Permission[] => {
    const props = get(pageStore).props as any;   // ← se evalúa UNA vez al montar
    ...
})();
```

El JSDoc promete acceso *"de forma reactiva"*, pero `get()` toma una instantánea en la inicialización del componente. Como todos los listados navegan con `preserveState: true` (`useFilteredList.ts:57`), el componente no se re-monta y los permisos quedan congelados al cambiar de contexto.

**Fix:** `const userPermissions = $derived(...)` sobre `$page.props`.

**A-7 · El prop `auth.permissions` es global, no contextual**
`HandleInertiaRequests.php:180-184`, con el comentario del propio equipo: `// FIX: esto esta mal debe ser contextual`. Aplana los permisos de todos los roles en todos los contextos, de modo que `can('cursos:editar')` devuelve `true` en la ficha de un curso donde el usuario no tiene ese permiso si lo tiene en cualquier otro. La UI ofrece acciones que el backend rechazará.

Nótese que el patrón correcto ya existe: `UserCoursesService` entrega `permisos` **por curso** dentro de `docente_courses`. La corrección consiste en consumir esa estructura en vez de la lista plana.

**B-9 · Doble petición por mutación** (detallado en §2) — un cambio de una línea por cada uno de los 4 sitios: eliminar el `router.reload` del `onSuccess` y dejar que el `redirect()->back()` refresque los props.

**Estado local obsoleto tras recarga parcial.** En `Activities/Index.svelte`, `grupoSeleccionado` conserva el objeto anterior después de `router.reload({ only: ['grupos'] })`; el modal muestra la nota vieja hasta reabrirse. El patrón `grupoSnap` (`:381`) ya resuelve bien el caso de los callbacks asíncronos; falta re-derivar la selección desde el prop actualizado.

### Code-Splitting y Bundling

**Lo que está bien:** `resources/js/app.ts` resuelve las páginas con `import.meta.glob('./pages/**/*.svelte', { eager: false })` — carga perezosa por página, que es la configuración correcta y la que más impacto tiene.

**Tres de las cinco reglas de `manualChunks` apuntan a directorios inexistentes**
`vite.config.js:30-40`

```js
if (id.includes('modules/resources'))  return 'resources';
if (id.includes('modules/admin'))      return 'admin';       // ❌ no existe
if (id.includes('modules/docente'))    return 'docente';     // ❌ no existe
if (id.includes('modules/estudiante')) return 'estudiante';  // ❌ no existe
if (id.includes('components'))         return 'components';
```

`resources/js/modules/` contiene únicamente `resources/` y `shared/`. Las tres reglas intermedias son código muerto y todo lo que debían separar cae en la última regla.

**El chunk `components` es un catch-all de 298 archivos `.svelte`.** La regla `id.includes('components')` agrupa toda la biblioteca de UI —incluidos los componentes de shadcn-svelte que solo usan una o dos páginas— en un único bundle que se descarga en la primera visita. Esto anula parcialmente el beneficio del lazy loading por página.

**Recomendación:** eliminar las tres reglas muertas y sustituir el catch-all de `components` por el chunking automático de Rollup, que reparte por grafo de dependencias real. La configuración manual solo aporta valor para las dependencias de vendor (`@inertiajs`, `tailwindcss`), que sí conviene mantener separadas.

**A-10 · SSR declarado activo pero nunca construido**
`config/inertia.php:22-27` fija `'enabled' => true` **hardcoded, sin `env()`**, apuntando a `bootstrap/ssr/ssr.js` — un directorio que **no existe**. Cada petición intenta el POST a `127.0.0.1:13714`, falla y cae al render de cliente.

Además, el código no es compatible con SSR: `useFilteredList.ts:23` usa `window.location.origin` sin guarda, a diferencia de `useAppearance.svelte.ts:6`, que sí comprueba `typeof window !== 'undefined'`. Activar SSR hoy rompería todos los listados.

**Decisión requerida:** o se construye el bundle SSR y se corrige `useFilteredList`, o se pone `'enabled' => env('INERTIA_SSR_ENABLED', false)`. El estado intermedio actual solo añade latencia.

---

## 4. Plan de Acción Recomendado

El plan aprovecha la conclusión central del informe: **cada control ausente tiene una implementación correcta en otro punto del repositorio.** La columna «referencia interna» indica de dónde copiar el patrón, lo que reduce el riesgo de la corrección y acelera la revisión.

### Fase 0 · Contención inmediata (1–2 días)

| # | Acción | Referencia interna |
|---|---|---|
| 0.1 | Añadir `protected $hidden = ['passhash', 'token_recuerdame_sesion'];` al modelo `Usuario`. **Una línea que cierra A-1, D-3 y D-5.** | — |
| 0.2 | Añadir `$this->authorize(...)` + comprobación de SuperAdmin a `syncPermissions`, o eliminar el endpoint y encauzar todo por `AssignmentWizardController`. | `RoleAssignmentBuilder::validateActorAuthorization()` |
| 0.3 | Hacer contextual `IsAdmin`: exigir el rol en el contexto global, no en cualquiera. | `ResolvesJefaturaCarrera::resolveJefatura()` |
| 0.4 | Añadir `assertActividadDeCurso($curso, $actividad)` en `updateIntegrante`, `recalcularNotasIndividuales` y `descargarEntrega`. **Tres líneas.** | El helper ya existe en `:49` |
| 0.5 | Acotar `aprobarPrograma` y `rechazarPrograma` por `carrera_id`. | `JefeCarreraController::programaPreview:337` |
| 0.6 | Verificar actividad↔curso en `Student\ActivityController::show` **antes** de invocar `asegurarGrupo`. | `AsistenciaController::autorizarComponente` |
| 0.7 | Corregir `array_unique(array_merge($a, $b))` en `PermissionValidator:154`. | — |
| 0.8 | Poner un tope a `per_page` (p. ej. `min($request->integer('per_page', 15), 100)`) en los 6 listados. | — |

### Fase 1 · Corrección de la autorización (1–2 semanas)

1. **Arreglar `checkSpecialPermission` (A-2).** El bug de `$bestResult` afecta a toda decisión de permiso con múltiples coincidencias. Acompañar de tests unitarios sobre la matriz GRANT/DENY con wildcards — es la pieza que más se beneficia de cobertura.
2. **Hacer contextuales `ProgramaPolicy::approve` y `reject` (E-1).** Ambas reciben el `Programa` y no lo miran; deben filtrar por la carrera del actor.
3. **Invocar las policies existentes en los 9 controladores que no lo hacen** (F-1, D-1): 7 en `Admin/` más `UsuarioController` y `AssignmentWizardController`. Las policies ya están escritas.
4. **Dar ámbito de curso a las rutas de componente** (F-2): cambiar `cursos/componentes/{componente}` por `cursos/{curso}/componentes/{componente}` y validar pertenencia. `setTitularByDt` ya lo hace bien en el mismo controlador.
5. **Sustituir `viewPrograma` por un permiso de escritura** en las 11 operaciones destructivas que hoy gobierna (B-5) y en `Ayudante\ProgramaController::update` (E-4). `manageTeam` es el modelo de política estricta.
6. **Corregir `getDelegablePermissions`** (F-3): comprobar SuperAdmin de verdad, no la ausencia de perfil docente.
7. **Erradicar el patrón de guard falsy** (B-6, C-1, C-7): reescribir los tres a `if (!coincide) { abort }`.
8. **Endurecer `changePassword`** (D-4): `Password::defaults()`, confirmación de la clave del administrador, invalidación de las sesiones del usuario afectado y registro de auditoría.

### Fase 2 · Rendimiento (2–3 semanas)

1. **Caché del motor de permisos (A-4).** Los `TODO` ya indican las claves (`perm:{user}:{tipo}:{permiso}:{contexto}`). Es la intervención con mayor impacto del informe: afecta a cada petición del sistema. Invalidar en `RoleAssignmentBuilder::save()` y en `syncPermissions`.
2. **Cachear `getAncestorContextsWithType`** (`ContextResolver:201`) — la jerarquía de contextos cambia rara vez.
3. **Eliminar los N+1 del `share()` global (A-5):** una consulta batch para `tiene_programa` y `getAllPermissionsGroupedByContext()` para los permisos, que ya se usa en la rama de ayudante del mismo servicio.
4. **Envolver los props costosos del `share()` en `Inertia::optional()`**, para que no viajen en recargas parciales que no los piden.
5. **Sacar `GrupoIndividualService` del camino de lectura (B-7, C-13):** convertirlo en un evento de inscripción o un comando de mantenimiento.
6. **Reescribir `centroCalificaciones` (B-8)** con agregados batcheados. `MensajesController::index` es el modelo exacto.
7. **Corregir `hasRole()` (A-6)** para usar la relación cargada.
8. **Mover `SET search_path` a `config/database.php` (A-11)** y recuperar la conexión perezosa.
9. **Eliminar los 4 `router.reload` redundantes (B-9)** y los 8 `Log::info` de traza de `CursoController::show`.

### Fase 3 · Subida de archivos y datos (1–2 semanas)

1. **Decidir sobre el antivirus (C-2):** implementarlo, o hacer que `scanForViruses` lance `ArchiveException(CONFIGURATION_ERROR)` cuando el flag esté activo sin implementación. El estado actual es el peor de los tres.
2. **Derivar la extensión del archivo validado, ignorando la del cliente** (C-3).
3. **Corregir el closure de tamaño** para que falle cuando extensión y MIME no casen con ninguna categoría (C-4).
4. **Retirar los interruptores `FILES_ENABLE_*_VALIDATION`** (C-8) o restringirlos a entorno local.
5. **Sanear la exportación CSV** anteponiendo `'` a los valores que empiecen por `=`, `+`, `-` o `@` (F-4).
6. **Importación por cola con chunking y límite de filas** (D-7).
7. **Eliminar la regla padre `'secciones' => 'required|array'`** para cerrar la escritura arbitraria en el JSONB (E-3).

### Fase 4 · Frontend y configuración (1 semana)

1. `usePermissions()` a `$derived` (A-8) y consumo de permisos por curso en vez de la lista plana (A-7).
2. Limpiar `manualChunks`: eliminar las 3 reglas muertas y el catch-all de `components`.
3. Resolver el SSR (A-10): construirlo y arreglar `useFilteredList`, o desactivarlo vía `env()`.
4. Endurecer la configuración: `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE`, `APP_DEBUG=false` en `.env.example`.
5. Dejar de registrar el RUT en claro en los logs de login (A-9) y retirar los volcados de payload (`B-12`, `D-11`, `F-12`).
6. Eliminar el fallback `Auth::id() ?? 1` en los 3 sitios (D-10, F-9). `set_config('app.actor_id', …)` de `JefeCarreraController` es el modelo de auditoría correcto.

### Fase 5 · Higiene y deuda

- Corregir la ruta rota `AgendaController@storeFile` (C-5) — hoy responde 500.
- Añadir `DB::rollBack()` al `catch (\Throwable)` de `storeEntrega` (C-6).
- Revisar `validatePermissionsForAllSecciones` (E-2): exigir los 9 permisos para un syllabus básico de 5 secciones inutiliza la delegación granular y empuja a conceder `MODIFICAR_ALL` a todos.
- Unificar la comparación de roles: hoy conviven `whereIn('nombre', […])` sensible a mayúsculas, `strtolower(trim())` y `whereRaw('LOWER(nombre) = ?')`. La existencia de cuatro alias (`'SuperAdmin'`, `'Super Admin'`, `'Admin'`, `'Administrador'`) indica que el problema ya se manifestó. Crear el `RolesEnum` que el propio código pide en un `TODO` (`Usuario.php:109`).
- Retirar de la raíz del repositorio los residuos de depuración: `verify_perms.php`, `show_special_perms.php`, `test_syllabus.php`, `verify_output.txt` y el archivo llamado literalmente `pluck('nombre')`.

---

## Anexo · Detalle por módulo

El detalle completo de cada hallazgo —con fragmentos de código, línea exacta, escenario de explotación y la sección de «verificado correcto»— está en los archivos de trabajo de esta misma carpeta:

| Archivo | Módulo | Hallazgos |
|---|---|---|
| [`_hallazgos-modulo-A.md`](./_hallazgos-modulo-A.md) | Núcleo transversal | 13 |
| [`_hallazgos-modulo-B.md`](./_hallazgos-modulo-B.md) | Docente: actividades, grupos, evaluación, entregas | 16 |
| [`_hallazgos-modulo-C.md`](./_hallazgos-modulo-C.md) | Estudiante: agenda y subida de archivos | 13 |
| [`_hallazgos-modulo-D.md`](./_hallazgos-modulo-D.md) | Admin: usuarios y Assignment Wizard | 15 |
| [`_hallazgos-modulo-E.md`](./_hallazgos-modulo-E.md) | Programa / Syllabus JSONB | 11 |
| [`_hallazgos-modulo-F.md`](./_hallazgos-modulo-F.md) | Admin: estructura académica y cursos | 12 |
| [`_hallazgos-modulo-GHI.md`](./_hallazgos-modulo-GHI.md) | Asistencia · Jefe de Carrera · Ayudante | 6 |
| [`_estado-auditoria.md`](./_estado-auditoria.md) | Índice, alcance y patrones transversales | — |

### Mapa de referencias internas

Tabla de consulta rápida para la remediación: dónde está, dentro de este mismo proyecto, la implementación correcta de cada control ausente.

| Control ausente | Implementación de referencia |
|---|---|
| Comprobación de pertenencia del recurso (B-1) | `JefeCarrera\PlanController::assertPlanDeCarrera` · `DocenteActivityController::assertActividadDeCurso:49` |
| Rol comprobado con contexto (D-2, E-1) | `ResolvesJefaturaCarrera::resolveJefatura` |
| Bloqueo de auto-escalada (D-1) | `DelegacionPermisosController:201` · `CourseTeamController:453` |
| Guard con la polaridad correcta (B-6, C-1, C-7) | `AsistenciaController::autorizarComponente` |
| Defensa contra mass assignment (E-3, B-4) | `JefeCarrera\PlanController` — `$validated['id_carrera'] = $carreraId` |
| Agregados sin N+1 (A-5, B-8, GHI-2) | `MensajesController::index:46-90` · `getAllPermissionsGroupedByContext()` |
| JSON vs redirect según tipo de respuesta (E-6) | `jefaturaOrAbort()` / `jefaturaOrRedirect()` |
| Auditoría no falsificable (D-10, F-9) | `JefeCarreraController` — `set_config('app.actor_id', …)` |
| Whitelist de ordenamiento sin inyección | `UsuarioController::index` — `$estudianteSortWhitelist` |
| Política de acceso estricta | `CursoPolicy::manageTeam` — con registro en canal `seguridad` |
| Recarga parcial correcta | `Activities/Index.svelte` — `router.reload({ only: [...] })` + `Inertia::lazy` |
