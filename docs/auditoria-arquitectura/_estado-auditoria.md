# Estado de la auditoría — Svelte + Inertia (UTAMED)

> Documento de continuidad. Permite retomar la auditoría en una sesión nueva sin volver a
> explorar el proyecto. **Rama auditada:** `admin`.

---

## Stack

Laravel 12 (PHP 8.2) · Inertia 2 · Svelte 5 (runes) · shadcn-svelte/bits-ui · Tailwind 4 · Vite 7
PostgreSQL (44 modelos en conexión `pgsql`; 1 en `oracle` para vistas de intranet vía `yajra/laravel-oci8`)
Fortify + 2FA · Wayfinder · 465 `.svelte`, 292 `.ts`, ~13.7k LOC de controladores, 503 líneas de rutas.

**Configuración clave:** `bootstrap/app.php` · `app/Http/Middleware/HandleInertiaRequests.php` (el
`share()` global es el punto más sensible del sistema) · `vite.config.js` · `resources/js/app.ts` ·
`config/inertia.php` · `config/filetypes.php`.

---

## Fases

- **Fase 1 — Mapeo y estrategia:** ✅ completada y aprobada.
- **Fase 2 — Análisis modular:** ✅ completada. 9 de 9 módulos, 86 hallazgos.
- **Fase 3 — Reporte:** ✅ completada → [`auditoria_arquitectura_svelte_inertia.md`](./auditoria_arquitectura_svelte_inertia.md)

### Formato acordado para la Fase 3

Archivo destino: `docs/auditoria-arquitectura/auditoria_arquitectura_svelte_inertia.md`

Estructura **exacta** solicitada por el usuario:

```
# 🏗️ Reporte de Auditoría Arquitectónica: Svelte + Inertia
## 1. Resumen Ejecutivo
## 2. Hallazgos Críticos (Ciberseguridad y Base de Datos)
   - Fugas de Datos / Overfetching
   - Vulnerabilidades (XSS, CSRF, Auth)
   - Consultas N+1 y Rendimiento DB
## 3. Oportunidades de Refactorización en Frontend (Svelte)
   - Manejo de Estado (Stores vs Props)
   - Code-Splitting y Bundling
## 4. Plan de Acción Recomendado
```

Más un **anexo por módulo** (decisión del usuario) con la tabla completa de hallazgos y las secciones de
"verificado correcto", consolidado desde los `_hallazgos-modulo-*.md`.

---

## Progreso por módulo

| Módulo | Alcance | Estado | Archivo | Hallazgos |
|---|---|---|---|---|
| **A** | Núcleo transversal: auth, `share()` global, middlewares, policies, `PermissionValidator`, `UserCoursesService`, composables | ✅ | `_hallazgos-modulo-A.md` | 13 |
| **B** | Docente: actividades, grupos, evaluación, entregas | ✅ | `_hallazgos-modulo-B.md` | 16 |
| **C** | Estudiante: agenda y subida de archivos | ✅ | `_hallazgos-modulo-C.md` | 13 |
| **D** | Admin: usuarios y Assignment Wizard | ✅ | `_hallazgos-modulo-D.md` | 15 |
| **E** | Programa/Syllabus JSONB (`Admin\ProgramaController` 1133 LOC, compartido por 4 roles) | ✅ | `_hallazgos-modulo-E.md` | 11 |
| **F** | Admin: estructura académica y cursos, ~3.3k LOC en 10 controladores | ✅ | `_hallazgos-modulo-F.md` | 12 |
| **G+H+I** | Asistencia/calendario/mensajería · Jefe de Carrera · Ayudante | ✅ | `_hallazgos-modulo-GHI.md` | 6 |

**Total: 86 hallazgos.**

---

## Los 6 hallazgos que encabezan el reporte

| ID | Descripción | Ubicación |
|---|---|---|
| **D-1 + D-2** | Escalada a SuperAdmin: `syncPermissions` esquiva `RoleAssignmentBuilder::validateActorAuthorization()` y `IsAdmin` acepta el rol "Administrador" de cualquier contexto | `UsuarioController:971-1128`, `IsAdmin.php:41` |
| **A-1 / D-3 / D-5** | `passhash` y `token_recuerdame_sesion` en cada respuesta Inertia; endpoint `buscarPorRut` los devuelve dirigidos por RUT | `HandleInertiaRequests:214`, `UsuarioController:885` |
| **C-1** | Un GET auto-inscribe al estudiante en actividades de cursos ajenos y habilita entregas allí | `Student/ActivityController:31-64` |
| **B-1** | IDOR en notas y descarga de entregas: falta `assertActividadDeCurso` en 3 métodos | `DocenteActivityController:601, 632, 1003` |
| **A-2 / A-3** | `checkSpecialPermission` decide GRANT/DENY por orden de filas; `getContextsFromPermission` lanza TypeError siempre | `PermissionValidator:511-533, 154` |
| **C-2** | El antivirus es un stub vacío que aprueba todo aunque se habilite el flag | `AbstractArchiveService:339-359` |

---

## Patrones transversales detectados

1. **Guard falsy que desactiva el control.** `if ($relacion) { comprobar }` en vez de
   `if (!coincide) { abort }`: cuando la relación es nula el control desaparece en silencio.
   Apariciones: **B-6, C-1, C-7**.
2. **Dos puertas para la misma operación, una endurecida y otra no.** El control correcto existe pero no
   cubre todas las rutas de entrada. Apariciones: **D-1** (builder vs escritura directa), **B-1/B-2**
   (`assertActividadDeCurso` aplicado en 9 de 12 métodos).
3. **Policies existentes y sin usar.** 22 archivos en `app/Policies/`; cero invocaciones en
   `UsuarioController` y `AssignmentWizardController`.
4. **Ausencia de `$hidden` en los 44 modelos**, con `share()` y varios endpoints devolviendo modelos
   Eloquent completos.
5. **Sin caché en el motor de permisos**: todo el cacheado son comentarios `TODO` en
   `PermissionValidator`.

---

## Nota metodológica sobre el frontend

El frontend está claramente mejor que el backend y el reporte debe reflejarlo: 0 `console.log` en 757
archivos, un único `{@html}` (SVG de QR generado en servidor), solo 2 archivos con `svelte/store`,
0 usos de `$:` legacy en las páginas de docente/estudiante/admin, y uso correcto de
`router.reload({only:[...]})` con props `Inertia::lazy`. Los defectos de frontend son puntuales:
`usePermissions` no reactivo (A-8), doble round-trip en mutaciones (B-9), `manualChunks` con reglas
muertas y SSR declarado pero nunca construido (A-10).

---

## Estado de la remediación

### Fase 0 · Contención inmediata — ✅ completada (2026-07-28, sin commitear)

| # | Acción | Dónde quedó |
|---|---|---|
| 0.1 | `$hidden` en `Usuario` | `BaseUsuario.php:48-51` |
| 0.2 | Guard de `syncPermissions` | `UsuarioController::assertPuedeSincronizarPermisos()` + `logIntentoEscalada()` |
| 0.3 | `IsAdmin` contextual | `IsAdmin.php` reescrito: rol exigido en el contexto **global** |
| 0.4 | `assertActividadDeCurso` | Aplicado en los **6** métodos que faltaban (el informe listaba 3) |
| 0.5 | Ámbito por carrera en aprobar/rechazar | `JefeCarreraController::programaEsDeCarrera()`, usado en 3 puntos |
| 0.6 | Guard actividad↔curso del estudiante | `Student\ActivityController::show`, antes de `asegurarGrupo` |
| 0.7 | `getContextsFromPermission` | `PermissionValidator:157` — `array_merge` en vez de spread |
| 0.8 | Tope de `per_page` | Trait `Concerns\LimitsPageSize` en **12** call sites (el informe contaba 6) |

### Correcciones adicionales de esta tanda

- **`checkSpecialPermission` (A-2) resuelto**, no sólo contenido: elegía por orden de filas y además
  el árbol de trabajo lo tenía a medio refactorizar (`$bestResult` nunca asignado y `getPriority()`
  llamado con 1 de 2 argumentos → `ArgumentCountError`). Ahora elige la menor prioridad y **en empate
  gana el DENY**. Constante nueva `PRIORITY_NO_MATCH`.
- **Canal de log `seguridad` no existía.** `CursoPolicy:71,138`, `DelegacionPermisosController:211` y
  `CursoService:479` lo usaban; `Log::channel()` sobre un canal no definido lanza
  `InvalidArgumentException`, así que la rama de denegación de `CursoPolicy::manageTeam` reventaba con
  500 en vez de denegar. Definido en `config/logging.php` (daily, 90 días).
- Revertida una reescritura sin commitear de `HandleInertiaRequests::share()` que mapeaba campos
  inexistentes (`$user->id`, `$user->name`, `$user->docente->id`; el modelo usa `id_usuario`,
  `nombre1`, `apellido1`). El leak de A-1/D-3/D-5 ya lo cierra el `$hidden` de 0.1.
- Retirado el volcado de payload de `syncPermissions` (parte de D-11, Fase 4).

### Fase 1 · Corrección de la autorización — ✅ completada (2026-07-28, sin commitear)

| # | Acción | Dónde quedó |
|---|---|---|
| 1 | `checkSpecialPermission` + tests | Arreglado en la tanda anterior; 7 tests nuevos en `tests/Unit/PermissionValidatorTest.php` |
| 2 | `ProgramaPolicy::approve`/`reject` contextuales | `ProgramaPolicy::puedeResolverPrograma()` |
| 3 | Policies en los 9 controladores | 7 en `Admin/` + `UsuarioController` + `AssignmentWizardController` |
| 4 | Ámbito de curso en rutas de componente | `routes/web.php:227-241` + `ComponenteController::assertComponenteDeCurso()` |
| 5 | Permiso de escritura en vez de `viewPrograma` | `DocenteActivityController::assertPuedeEditarEvaluacion()` (11 sitios) + `Ayudante\ProgramaController` |
| 6 | `getDelegablePermissions` | `CourseTeamController:669` — `isSuperAdmin()` de verdad |
| 7 | Guard falsy erradicado | `AgendaController:100` (C-7); B-6 y C-1 ya estaban |
| 8 | `changePassword` endurecido | `UsuarioController:856` + `Password::defaults()` en `AppServiceProvider` |

**Decisiones de diseño que se apartan del informe:**

- **B-5 no usa `manageTeam`.** El informe lo propone como modelo, pero `manageTeam` limita al titular
  del curso y dejaría al docente de componente sin poder calificar lo que dicta. El guard nuevo acota
  al **componente del que cuelga la actividad**: admin global, titular del curso, o docente de ese
  componente. Cubre además `storeRubrica` (B-2), que recibía `id_actividad` por el cuerpo del request
  sin ninguna comprobación de curso.
- **`Password::defaults()` se definió en `AppServiceProvider`** (min 8, letras y números; más
  `uncompromised()` fuera de local). Sin eso `Password::defaults()` era un simple `min(8)`.
- **Lista de alias de rol administrativo consolidada** en `Usuario::ROLES_ADMINISTRATIVOS`, consumida
  por `IsAdmin`, `ProgramaPolicy` y `UsuarioController`. Métodos nuevos en el modelo:
  `hasAnyRoleInContext()` y `hasAnyRoleGlobally()`.
- **`resolveJefatura` extraído** del trait de controladores a `JefaturaCarreraResolver`, para que las
  policies puedan usarlo. El trait delega; sus 5 consumidores no cambian.

**Cambios de contrato que tocan el frontend:**

- Las 6 rutas de componente pasan de `cursos/componentes/{componente}` a
  `cursos/{curso}/componentes/{componente}`. Actualizados `cursoApi.ts` (6 llamadas, 4 firmas) y
  `componenteForm.svelte`. Los archivos generados por Wayfinder en `resources/js/actions/` quedan
  **desactualizados**: nadie los importa para estas rutas, pero hay que regenerarlos.
- `changePassword` ahora exige `current_password`. Actualizados `passwordChangeModal.svelte` y
  `pages/admin/Usuarios.svelte`.

### Fase 2 · Rendimiento — ✅ completada (2026-07-28, sin commitear)

| # | Acción | Dónde quedó |
|---|---|---|
| 1 | Caché del motor de permisos | `PermissionCache` + invalidación por eventos de modelo |
| 2 | Caché de `getAncestorContextsWithType` | `ContextResolver` (memoria por request + store, TTL 1 h) |
| 3 | N+1 del `share()` | `UserCoursesService` reescrito: todo en lote |
| 4 | Props costosos no evaluados de más | Closures en `share()`, **no** `Inertia::optional()` |
| 5 | `GrupoIndividualService` fuera de la lectura | Evento en `InscripcionCurso` + comando de backfill |
| 6 | `centroCalificaciones` | Una consulta de actividades para todos los componentes |
| 7 | `hasRole()` usa la relación cargada | `Usuario::getAllRoles()` resuelve en memoria si ya está cargada |
| 8 | `SET search_path` | Eliminado de `AppServiceProvider`; lo aplica el conector pgsql |
| 9 | `router.reload` y logs de traza | 4 recargas en `Activities/Index.svelte` + 8 `Log::info` |

**A-11 desbloqueó la verificación.** El `DB::connection()->getPdo()` del arranque abría conexión en
cada request y hacía fallar cualquier comando de consola. Quitándolo, `php artisan` y **la suite de
tests funcionan** en un entorno sin driver pgsql, porque los tests unitarios mockean la BD.

**Decisiones de diseño que se apartan del informe:**

- **`Inertia::optional()` habría vaciado el sidebar.** El informe lo propone para que los props
  costosos «no viajen en recargas parciales que no los piden», pero un prop `optional` se excluye
  también de las visitas normales. Lo que evita el coste sin cambiar la semántica es envolverlos en
  **closures**: Inertia filtra los props de una recarga parcial antes de resolverlos, así que un
  closure no pedido nunca se ejecuta.
- **Invalidación de caché por generación, no por tags.** El driver es `database`, que no soporta tags;
  cada usuario tiene un contador que forma parte de la clave e incrementarlo invalida todo lo suyo de
  golpe. Los eventos `saved`/`deleted` de `UsuarioRolAsignacion` y `UsuarioPermisoEspecial` cubren
  todas las vías de escritura salvo las masivas (`where()->update()`), que se invalidan a mano en
  `syncPermissions`.
- **B-7 se resolvió por partida doble:** el servicio ahora resuelve en dos consultas en vez de un
  bucle, *y* salió del camino de lectura. Al crear los grupos en la inscripción se cierra además C-13
  (un GET que escribía en la BD). Para los datos anteriores:
  `php artisan agenda:backfill-grupos-individuales [--curso=N] [--dry-run]`.
- **De propina, F-5:** `CursoController::show` devolvía `error_class` y `error_file` al cliente al
  margen de `APP_DEBUG`. Al limpiar sus logs de traza se acotó también la respuesta de error.

### Pendiente / conocido

- `Auth::id() ?? 1` sigue en `syncPermissions` (D-10, Fase 4); con el guard nuevo el fallback es
  inalcanzable.
- Las 6 rutas de creación de usuario siguen con `min:6` en vez de `Password::defaults()` (D-4 lo
  menciona; el plan sólo pedía `changePassword`).
- `auth.permissions` sigue siendo la lista plana no contextual (A-7, Fase 4).
- **21 tests fallan desde antes de esta remediación**, todos en `WildcardMatcherTest` (12) y
  `ContextTypeEnumTest` (9): referencian casos de enum que no existen
  (`Permissions::CURSOS_ACTIVIDADES_GRUPOS_CREAR`) y entradas de `ContextType` que no cuadran. No se
  han tocado. Baseline actual: **21 fallidos, 141 pasados**.
- La verificación cubre `php -l`, la suite unitaria, `php artisan route:list`, `wayfinder:generate` y
  `svelte-check` (0 errores / 25 warnings). **No hay base de datos en este entorno**, así que los tests
  Feature/Integration y cualquier prueba con datos reales siguen sin ejecutarse.

**Siguiente:** Fase 3 · Subida de archivos y datos (antivirus C-2, extensión derivada C-3, CSV F-4,
importación por cola D-7, regla padre del JSONB E-3).

---

## Para retomar en una sesión nueva

Basta con: leer este archivo y los `_hallazgos-modulo-*.md`, y continuar por la fase que corresponda
del [Plan de Acción](./auditoria_arquitectura_svelte_inertia.md#4-plan-de-acción-recomendado).
