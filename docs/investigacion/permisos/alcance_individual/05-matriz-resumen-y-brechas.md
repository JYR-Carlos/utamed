# 05. Matriz Maestra, Catálogo de Flujos Inalcanzables e Impacto de Helpers

> Síntesis consolidada de los Bloques I–V sobre los **37 permisos concretos** de los roles docentes (`Docente Titular`, `Docente Componente`, `Docente Componente Colegiado`) evaluados en los 4 perfiles (**A. Alumno**, **B. Ayudante**, **C. Colegiado puro**, **D. Docente externo**).

---

## 1. Matriz Maestra Consolidada (Permiso × Perfil)

Leyenda de estado:
- **OK**: Alcanzable desde UI y ejecutable en backend.
- **URL**: Backend pasa, pero **oculto en Dashboard/Sidebar/UI** (solo accesible por URL/API directa).
- **PARCIAL**: Funciona solo en contexto `componente` (no `curso`) o solo combinado con otro permiso.
- **ROTO**: Muestra UI pero falla con `403` en backend, o viceversa.
- **BLOQ-DOC**: Bloqueado por exigir ficha `usuario.docente` (`IsDocente:37` / `assertPermisoEnActividad:134` / `autorizarComponente:339`).
- **FANTASMA / MUERTO**: Bloqueado por `IsAdmin`, sin rutas en `routes/web.php`, o 0 usos en runtime.

| Grupo | Permiso | A. Alumno | B. Ayudante | C. Colegiado puro | D. Docente externo | Cuello de botella principal |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Curso** | `cursos:ver` | BLOQ-DOC | BLOQ-DOC | OK (sin sidebar) | URL (vista vacía) | `IsDocente:37`; `UserCoursesService:70`; `DashboardController:96` |
| **Curso** | `cursos:editar` | BLOQ-DOC | BLOQ-DOC | URL (solo `manageTeam`) | URL (solo `manageTeam`) | Edición curso tras `IsAdmin:40`; botones equipo tras `es_titular_curso` |
| **Curso** | `cursos:eliminar` | FANTASMA | FANTASMA | FANTASMA | FANTASMA | Única ruta en `/admin/*` (`IsAdmin:40`) |
| **Inscripciones** | `cursos/inscripciones:ver` | FANTASMA | FANTASMA | FANTASMA | FANTASMA | Únicas rutas en `/admin/*` (`IsAdmin:40`); vista `docente/Inscripciones` inexistente |
| **Unidades** | `cursos/unidades:ver` | MUERTO | MUERTO | MUERTO | MUERTO | 0 rutas en `routes/web.php` (`DocenteUnidadController` huérfano) |
| **Unidades** | `cursos/unidades:crear` | MUERTO | MUERTO | MUERTO | MUERTO | 0 rutas en `routes/web.php` |
| **Unidades** | `cursos/unidades:crear_plantilla` | MUERTO | MUERTO | MUERTO | MUERTO | 0 rutas + 0 checks en policy/controlador |
| **Unidades** | `cursos/unidades:editar` | MUERTO | MUERTO | MUERTO | MUERTO | 0 rutas en `routes/web.php` |
| **Unidades** | `cursos/unidades:eliminar` | MUERTO | MUERTO | MUERTO | MUERTO | 0 rutas en `routes/web.php` |
| **Programas** | `cursos/programas:ver_todos` | BLOQ-DOC | OK (`/ayudante`) | OK (oculto si `!tiene_programa`) | URL (si pasa `IsDocente`) | `CursoDetalle:373`; `IsDocente:28-31` omite `programas:*` |
| **Programas** | `cursos/programas:agregar` | BLOQ-DOC | ROTO (`modificar:*`) | ROTO (`seccionesEditables=[]`) | ROTO (`seccionesEditables=[]`) | `Administrativo\ProgramaController:238` exige `modulo_1..9`; `Ayudante:99` exige `*` |
| **Programas** | `cursos/programas:eliminar` | BLOQ-DOC | FANTASMA (sin ruta) | OK | URL | Sin ruta `DELETE` en `/ayudante/*` |
| **Programas** | `cursos/programas/modificar:modulo_1..9` | BLOQ-DOC | ROTO (`modificar:*`) | ROTO (**403 al guardar**) | ROTO (**403 al guardar**) | `Admin\ProgramaController::store:275` exige `agregar`; `Ayudante:99,168` exige `*` |
| **Componentes** | `componentes:ver` | BLOQ-DOC | ROTO (`403 viewAny`) | PARCIAL (`403` en `/componentes`) | URL (`403` en `/componentes`) | `BaseComponentePolicy::viewAny:26` valida solo contexto Global |
| **Componentes** | `componentes:editar` | FANTASMA | FANTASMA | FANTASMA | FANTASMA | Solo tras `IsAdmin:40`; `setTitularByDt:542` chequea `id_docente_titular` |
| **Componentes** | `componentes/inscripciones:ver` | MUERTO | MUERTO | MUERTO | MUERTO | 0 rutas, 0 checks en controladores, 0 UI |
| **Asistencia** | `componentes/asistencia:ver` | BLOQ-DOC | BLOQ-DOC | OK | URL (oculto en UI) | `CursoDetalle:538` exige `mis_componentes > 0`; `/docente/asistencia` sin link |
| **Asistencia** | `componentes/asistencia:registrar` | BLOQ-DOC | BLOQ-DOC | OK | URL | `AsistenciaPanel.svelte` no chequea permisos; `IsDocente:37` |
| **Asistencia** | `componentes/asistencia:editar` | BLOQ-DOC | BLOQ-DOC | OK | URL | `AsistenciaPanel.svelte` no chequea permisos; `IsDocente:37` |
| **Asistencia** | `componentes/asistencia:eliminar` | BLOQ-DOC | BLOQ-DOC | OK (solo vía Admin) | URL (solo vía Admin) | `puede_delegar = false` en `Docente Componente` |
| **Actividades** | `actividades:ver` | BLOQ-DOC | BLOQ-DOC | PARCIAL (`403` al abrir actividad) | URL (`0` filas en UI) | `showEvaluacion:900` exige `evaluar`; `Actividades.svelte:138` filtra `misComponentesIds` |
| **Actividades** | `actividades:crear` | BLOQ-DOC | BLOQ-DOC | PARCIAL (solo ctx `componente`) | ROTO (selector vacío) | `ContextType` excluye `curso`; `Actividades.svelte:145` filtra `c.es_mio` |
| **Actividades** | `actividades:crear_plantilla` | MUERTO | MUERTO | MUERTO | MUERTO | 0 usos en runtime |
| **Actividades** | `actividades:editar` | BLOQ-DOC | BLOQ-DOC | OK (en su comp.) | URL (`0` filas en UI) | `Actividades.svelte:138` filtra por `misComponentesIds` |
| **Actividades** | `actividades:eliminar` | BLOQ-DOC | BLOQ-DOC | PARCIAL (solo ctx `componente`) | URL (`0` filas en UI) | `ContextType` excluye `curso` |
| **Actividades** | `actividades:evaluar` | BLOQ-DOC | BLOQ-DOC | OK (en su comp.) | URL (`0` filas en `/actividades`) | `IsDocente:37`; `assertPermisoEnActividad:134`; `Actividades.svelte:138` |
| **Actividades** | `actividades:dar_feedback` | BLOQ-DOC | BLOQ-DOC | OK (con `evaluar` del grafo) | URL | `showEvaluacion:900` exige `evaluar` |
| **Actividades** | `actividades:descargar_entregas` | BLOQ-DOC | BLOQ-DOC | ROTO en UI (API directa OK) | URL (API directa) | `showEvaluacion:900` da `403` sin `evaluar` |
| **Actividades** | `actividades:subir_entregas` | MUERTO | MUERTO | MUERTO | MUERTO | 0 usos en runtime |
| **Actividades** | `actividades:enviar_recordatorios` | BLOQ-DOC | BLOQ-DOC | ROTO (**403 siempre**) | ROTO (**403 siempre**) | `MensajesController::send:137` exige `id_docente_titular` |
| **Grupos** | `actividades/grupos:ver` | BLOQ-DOC | BLOQ-DOC | ROTO (`403` en UI; fuga en JSON) | ROTO | `showEvaluacion:900` exige `evaluar`; `gruposPorActividad:1666` no lo valida |
| **Grupos** | `actividades/grupos:crear` | BLOQ-DOC | BLOQ-DOC | ROTO (`403` en UI y `grupos-copy`) | ROTO | `showEvaluacion:900`, `gruposDeActividadOrigen:1701`, `copiarGruposDeActividad:1754` |
| **Grupos** | `actividades/grupos:editar` | BLOQ-DOC | BLOQ-DOC | ROTO (`403` en UI; API directa OK) | ROTO | `showEvaluacion:900`; `GrupoCard.svelte` ignora permiso; fuga de notas en `updateIntegrante:1421` |
| **Grupos** | `actividades/grupos:eliminar` | BLOQ-DOC | BLOQ-DOC | ROTO (`403` en UI; API directa OK) | ROTO | `showEvaluacion:900`; `GrupoCard.svelte:163` ignora permiso |
| **Delegación** | `puede_delegar = true` (1 permiso) | BLOQ-DOC | BLOQ-DOC | PARCIAL (botón oculto en 33/38 slugs) | URL (botón oculto en comp.) | `DocenteCursoController:339-344` solo chequea 4 slugs y `$misComponentes` |

---

## 2. Catálogo de Permisos Asignables con Flujos Inalcanzables

### Categoría A — Permisos completamente muertos o fantasma (11 permisos)
1. **`cursos/unidades:{ver,crear,crear_plantilla,editar,eliminar}`** (5): 0 rutas en `routes/web.php`.
2. **`actividades:crear_plantilla`** y **`actividades:subir_entregas`** (2): 0 checks en backend y frontend.
3. **`componentes/inscripciones:ver`** (1): 0 rutas, 0 checks en controladores, 0 UI.
4. **`cursos:eliminar`**, **`cursos/inscripciones:ver`**, **`componentes:editar`** (3): 100% detrás de `IsAdmin:40` (ningún no-admin puede usarlos).

### Categoría B — Permisos con flujo roto o inalcanzable en UI (17 permisos)
5. **`cursos/programas/modificar:modulo_1..9`** (9): abren el wizard pero reciben **403 al guardar** en `Admin\ProgramaController::store:275` si no viene `agregar`; en `/ayudante` exigen `modificar:*`.
6. **`cursos/programas:agregar`** (1): sin `modulo_1..9` falla en `edit:238` (`seccionesEditables() === []`); además `CursoDetalle:373` oculta el botón si `!tiene_programa`.
7. **`actividades/grupos:{ver,crear,editar,eliminar}`** (4): `showEvaluacion:900` exige `ACTIVIDADES_EVALUAR` para abrir la pantalla; `grupos-copy` y `grupos-origen` dan 403 con `grupos:crear`.
8. **`actividades:descargar_entregas`** (1): `showEvaluacion:900` da 403 sin `ACTIVIDADES_EVALUAR`.
9. **`actividades:enviar_recordatorios`** (1): `MensajesController::send:137` exige `id_docente_titular`.
10. **`cursos:editar`** (1): su única acción docente (`manageTeam`) tiene todos los botones ocultos tras `curso.es_titular_curso`.

### Categoría C — Permisos bloqueados estructuralmente por perfil (Perfiles A, B y D)
- **Para A (Alumno) y B (Ayudante)**: **36 de 37 permisos** (todos excepto `cursos/programas:ver_todos` para B en `/ayudante`) chocan en `IsDocente.php:37` (`!$user->docente`), `DocenteActivityController.php:134` (`!$user->docente`) y `AsistenciaController.php:339` (`!$user->docente`).
- **Para D (Docente externo)**: todos los cursos delegados son **invisibles** en Dashboard, `/docente/cursos`, Sidebar, `/docente/asistencia` y `/docente/calificaciones`; y `Actividades.svelte:138-142` oculta todas las actividades si `misComponentesIds` está vacío.
- **Para C (Colegiado puro)**: `GET /dashboard` (`routes/web.php:71`) omite `'Docente Componente Colegiado'` (redirige a `/sin-rol`), y `RoleSidebar.svelte:48` exige `cursos:ver` **global**.

---

## 3. Catálogo de Riesgos de Seguridad y Crashes 500 Detectados

| Severidad | Ubicación | Descripción |
| :---: | :--- | :--- |
| **CRÍTICA** | `app/Http/Controllers/Admin/CourseTeamController.php:134-147` | `$isDocente = $currentUser && $currentUser->docente;` — si un no-docente (Alumno/Ayudante) con `cursos:editar` llegara a pasar `IsDocente`, cae en `else` (**modo Admin**) y puede asignar cualquier rol excepto SuperAdmin en el curso. |
| **ALTA** | `app/Http/Controllers/Admin/CourseTeamController.php:736-748` | `getDelegablePermissions()` consulta `rolesAsignados()` **sin filtrar por `$idContexto`** (fuga de delegabilidad entre cursos). |
| **ALTA** | `app/Http/Controllers/Docente/CursoPermisosController.php:75,110,208-223` | `componenteIndex/Sync` hace bypass total de RelBAC (solo chequea `docente_componente.es_titular`), y ni `syllabusSync` ni `componenteSync` tienen guard IDOR sobre `id_usuario`. |
| **MEDIA** | `app/Http/Controllers/Docente/DocenteActivityController.php:1666` | `gruposPorActividad` (`GET /grupos-list`) solo valida `viewPrograma` — fuga cross-componente de grupos y notas. |
| **MEDIA** | `app/Http/Controllers/Docente/DocenteActivityController.php:1421` | `updateIntegrante` modifica `nota_individual` / `diferencia_decimas` aceptando `ACTIVIDADES_GRUPOS_EDITAR` sin `ACTIVIDADES_EVALUAR`. |
| **MEDIA (500)** | `app/Http/Controllers/Docente/DocenteCursoController.php:147,443` y `CursoPermisosController.php:243` | Dereferencian `$user->docente->id_docente` sin null-safe (`?->`) — provocarían **HTTP 500** si se relaja `IsDocente:37` para perfiles A o B (o SuperAdmin sin ficha docente). |

---

## 4. Evaluación de Impacto: `hasAnyPermForTheRole()` y `hasAllPermsForTheRole()`

### 4.1 Dónde SÍ aportan valor real
- **`hasAnyPermForTheRole(Usuario $user, string $roleName, ?HasContext $resource = null)`**:
  - **En `IsDocente` (`IsDocente.php:28-31`)**: reemplaza la lista manual incompleta de 3 slugs (`CURSOS_VER`, `COMPONENTES_VER`, `ACTIVIDADES_VER`) y desbloquea la entrada cuando un usuario solo tiene `cursos/programas:*`, `actividades/grupos:*` o `componentes/asistencia:*`.
  - **En `CursoPolicy::viewPrograma()` (`CursoPolicy.php:74-91`)**: evita olvidar familias de permisos del curso al validar entrada al curso.

### 4.2 Dónde son PELIGROSOS si se usan sin cuidado
1. **Colisión de permisos compartidos en `GET /dashboard` (`routes/web.php:71`) y `IsDocente`**:
   - Los roles `Estudiante` y `Ayudante` **comparten `cursos:ver` y `componentes:ver`** con `Docente Titular` y `Docente Componente`.
   - Por tanto, `hasAnyPermForTheRole($user, 'Docente Titular')` devolvería **`true` para TODOS los estudiantes del sistema**, desviándolos a `/docente/dashboard` (y causando loops o crashes 500).
   - **Regla obligatoria**: `hasAnyPermForTheRole()` **NO** sirve para enrutar portales si el rol incluye permisos de lectura compartidos con `Estudiante`, salvo que se excluyan permisos base compartidos o se use una lista de capacidades exclusivas del portal (ver Helper #1 abajo).
2. **Por qué `hasAllPermsForTheRole()` NO debe usarse en middlewares ni delegación**:
   - Exigir *todos* los permisos del rol rompe cualquier delegación parcial (1 permiso), rompe `Docente Titular Restringido`, y expulsa del portal a cualquier docente al que se le haya aplicado una sola revocación (`DENY` tri-estado).
   - Su único uso legítimo es auditoría/diagnóstico o detección de "equivalencia completa de rol" (ej. para decidir si un usuario tiene perfil pleno de administrador).
3. **Lo que NO resuelven por sí solos**:
   - Si se abre `IsDocente` a perfiles sin ficha `usuario.docente` (A y B), **hay que refactorizar antes** `DocenteCursoController:147,443`, `CourseTeamController@store:134` (cerrar escalación a Admin), `DocenteActivityController:134` y `AsistenciaController:339`.
   - No arreglan los filtros SQL duros por `id_docente_titular` / `docente_componente` en Dashboard, `UserCoursesService`, `/docente/cursos`, `/docente/calificaciones`, ni los filtros UI (`Actividades.svelte:138`, `CursoDetalle.svelte:392`).

---

## 5. Propuesta de Helpers Adicionales (Backend y Frontend)

Además de `hasAnyPermForTheRole()` / `hasAllPermsForTheRole()`, la auditoría revela **6 patrones repetitivos** donde conviene introducir helpers canónicos en `PermissionValidator` (y su espejo en `permissionValidator.ts`):

| # | Helper propuesto | Firma sugerida | Qué problema resuelve |
| :---: | :--- | :--- | :--- |
| **1** | **`hasAnyPermissionIn(user, permissions, resource)`** | `PermissionValidator::hasAny(Usuario $u, array $perms, ?HasContext $res = null): bool` | Permite expresar puertas de entrada sin colisión con `Estudiante` (ej. permisos de *escritura/gestión* docente para `IsDocente`, o los permisos que abren `showEvaluacion`: `[ACTIVIDADES_EVALUAR, ACTIVIDADES_GRUPOS_*, ACTIVIDADES_DAR_FEEDBACK, ACTIVIDADES_DESCARGAR_ENTREGAS]`). |
| **2** | **`resolveAccessibleCourseIds(user, permissions)`** | `PermissionValidator::resolveAccessibleCourseIds(Usuario $u, array $perms): int[]` | Unifica el descubrimiento de cursos en `Docente\DashboardController`, `DocenteCursoController::index`, `UserCoursesService::getDocenteCourses`, `AsistenciaController::centro` y `centroCalificaciones` — resuelve el problema de **Perfil C (sidebar)** y **Perfil D (cursos invisibles)** en una sola consulta. |
| **3** | **`resolveAccessibleComponentIds(user, curso, permissions)`** | `PermissionValidator::resolveAccessibleComponentIds(Usuario $u, Curso $c, array $perms): int[]` | Reemplaza el filtro duro `docente_componente` en `DocenteCursoController::show` y `Actividades.svelte` (`misComponentesIds`) para que un colegiado o externo vea exactamente los componentes sobre los que tiene permiso efectivo. |
| **4** | **`hasAnyDelegableInCourse(user, curso)`** | `PermissionValidator::hasAnyDelegableInCourse(Usuario $u, Curso $c, array $matrixSlugs): bool` | Reemplaza el bloque hardcoded de 4 slugs y `$misComponentes` en `DocenteCursoController::show:331-345`, eliminando los 3 bugs del botón "Delegación de permisos" (33 slugs ocultos, Perfil D en componente, falso positivo por `manageTeam`). |
| **5** | **`canOnResourceHierarchy(user, perm, resource)`** | Usar `$actividad` (no `$actividad->componente`) en `DocenteActivityController::assertPermisoEnActividad:139` | Alinea el runtime con `config/permission-context-metadata.php` para que permisos otorgados en `ContextType::ACTIVIDAD` (como `actividades/grupos:*` o `actividades:evaluar` en 1 sola actividad) funcionen realmente. |
| **6** | **`hasAnyPermissionInAnyContext(map, slugs)`** *(Frontend TS)* | `hasAnyPermissionInAnyContext(userPermissions, userPermissionsByComponent, slugs): boolean` en `permissionValidator.ts` | Unifica la lógica duplicada en `CursoDetalle.svelte`, `Actividades.svelte`, `RoleSidebar.svelte` (corrigiendo el bug de `can('cursos:ver')` solo global en sidebar) y habilita los checks faltantes en `AsistenciaPanel.svelte` y `GrupoCard.svelte`. |
