# 06. Plan de Fixes Priorizado (Post-Auditoría de Alcance Individual)

> Basado en la deliberación del Consejo sobre [05-matriz-resumen-y-brechas.md](./05-matriz-resumen-y-brechas.md).
> Estrategia elegida: **Opción B — Híbrida Escalonada** (Fase 1: seguridad + poda + fixes de flujos rotos; Fase 2: desacoplamiento de `$user->docente` y `docente_componente` para habilitar delegación real a Alumnos, Ayudantes y Docentes externos).

---

## Reglas de Diseño Acordadas (Helpers)
- **NO** usar `hasAnyPermForTheRole('Docente ...')` sin filtrar en `GET /dashboard` ni `IsDocente` (colisiona con `Estudiante`/`Ayudante` por compartir `cursos:ver` y `componentes:ver`).
- **NO** usar `hasAllPermsForTheRole()` en middlewares ni guards de acceso (rompe delegación parcial y revocaciones tri-estado).
- Implementar helpers canónicos en `PermissionValidator` (y espejo en `permissionValidator.ts`):
  1. `hasAny(Usuario $user, array $permissions, ?HasContext $resource = null): bool`
  2. `hasAnyDelegableInCourse(Usuario $user, Curso $curso, array $matrixSlugs): bool`
  3. `resolveAccessibleCourseIds(Usuario $user, array $permissions): int[]`
  4. `resolveAccessibleComponentIds(Usuario $user, Curso $curso, array $permissions): int[]`
  5. `hasAnyPermissionInAnyContext(userPermissions, userPermissionsByComponent, slugs): boolean` (frontend TS)

---

## Fase 1 — Seguridad P0, Poda de Permisos Muertos y Reparación de Flujos Rotos

### 1.1 Seguridad P0 (cerrar brechas antes de abrir portales)
1. **`app/Http/Controllers/Admin/CourseTeamController.php:134-147`**: reemplazar `$isDocente = $currentUser && $currentUser->docente` (donde `else` asumía Admin) por verificación explícita `$currentUser->esAdministradorGlobal()`.
2. **`app/Http/Controllers/Admin/CourseTeamController.php:736-748`**: reemplazar `getDelegablePermissions()` (que ignora `$idContexto` en `rolesAsignados()`) por `PermissionValidator::resolveDelegableSlugsByContexts()`.
3. **`app/Http/Controllers/Docente/CursoPermisosController.php`**: agregar guard IDOR en `syllabusSync` / `componenteSync`, validar `$componente->id_curso === $curso->id_curso`, reemplazar bypass `docente_componente.es_titular` (`:208-223`) por `PermissionValidator` / `puede_delegar`, y hacer null-safe `$user->docente?->id_docente` (`:243`).
4. **`app/Http/Controllers/Docente/DocenteActivityController.php:1666`**: en `gruposPorActividad` (`GET /grupos-list`), exigir `ACTIVIDADES_GRUPOS_VER` / `ACTIVIDADES_EVALUAR` / `ACTIVIDADES_VER` sobre el componente (cierra fuga cross-componente).
5. **`app/Http/Controllers/Docente/DocenteActivityController.php:1421`**: en `updateIntegrante`, exigir `ACTIVIDADES_EVALUAR` para modificar `diferencia_decimas` / `nota_individual` (no `ACTIVIDADES_GRUPOS_EDITAR`).

### 1.2 Poda de permisos muertos / fantasma en `DELEGABLE_MATRIX`
- En `app/Http/Controllers/Docente/DelegacionPermisosController.php`: retirar los permisos sin flujo operativo para docentes (`cursos/unidades:*` mientras no existan rutas, `cursos:eliminar`, `cursos/inscripciones:ver`, `actividades:crear_plantilla`, `actividades:subir_entregas`) para que toda celda visible en la matriz tenga efecto real.

### 1.3 Reparación de flujos rotos para Colegiado (C) y Ayudante (B)
1. **Redirección y Sidebar**:
   - `routes/web.php:71`: agregar `'Docente Componente Colegiado'` a `hasAnyRole([...])` (evita caída a `/sin-rol`).
   - `resources/js/components/custom/layout/RoleSidebar.svelte:48` y `app/Services/UserCoursesService.php:70`: incluir cursos donde el docente es miembro de `docente_componente` (no solo `id_docente_titular`) y no depender de `cursos:ver` únicamente Global.
2. **Programas / Syllabus**:
   - `app/Http/Controllers/Admin/ProgramaController.php:275`: si el programa ya existe, validar `authorize('update', $programa)` en vez de `create` (desbloquea `modulo_1..9` sin exigir `agregar`).
   - `app/Http/Controllers/Administrativo/ProgramaController.php:238`: permitir crear cuando tiene `agregar` (o exigir `modulo_1..9` en el grafo).
   - `app/Http/Controllers/Ayudante/ProgramaController.php:99,168`: aceptar `modulo_1..9` / `agregar` en lugar de exigir el wildcard `CURSOS_PROGRAMAS_MODIFICAR_ALL`.
   - `resources/js/pages/docente/Programa.svelte:332-360`: respetar `layoutType` para URLs de acciones en lugar de hardcodear `/docente/*`.
   - `resources/js/pages/docente/CursoDetalle.svelte:373`: mostrar botón Programa/Crear Programa si `tiene_programa || canCreatePrograma`.
   - Corregir slugs fantasma `'cursos/programas:crear'` → `'cursos/programas:agregar'` (`CourseListNav.svelte:203`, `ayudante/Courses/Show.svelte:35`) y `'CURSOS_PROGRAMA'` (`ayudante/Courses/Index.svelte:39`).
3. **Actividades, Grupos y Recordatorios**:
   - `app/Http/Controllers/Docente/DocenteActivityController.php:900`: `showEvaluacion` aceptar `hasAny([ACTIVIDADES_EVALUAR, ACTIVIDADES_GRUPOS_VER, ACTIVIDADES_GRUPOS_CREAR, ACTIVIDADES_GRUPOS_EDITAR, ACTIVIDADES_GRUPOS_ELIMINAR, ACTIVIDADES_DAR_FEEDBACK, ACTIVIDADES_DESCARGAR_ENTREGAS])`.
   - `gruposDeActividadOrigen:1701` y `copiarGruposDeActividad:1754`: aceptar `ACTIVIDADES_GRUPOS_CREAR` además de `ACTIVIDADES_EVALUAR` (validando lectura del componente origen).
   - `app/Http/Controllers/Docente/MensajesController.php:137`: aceptar `Permissions::ACTIVIDADES_ENVIAR_RECORDATORIOS` en el componente/curso (no solo `id_docente_titular`).
   - `resources/js/pages/docente/Activities/Index.svelte` y `GrupoCard.svelte`: pasar capacidades granulares (`puedeCrearGrupos`, `puedeEditarGrupos`, `puedeEliminarGrupos`, `puedeEvaluar` sin bypass `esDocenteComponente`).
   - `resources/js/pages/docente/components/AsistenciaPanel.svelte` y `CursoDetalle.svelte:538`: condicionar pestaña y botones de asistencia a `userPermissionsByComponent`.
4. **Botón de Delegación y `CursoDetalle`**:
   - `app/Http/Controllers/Docente/DocenteCursoController.php:331-345`: reemplazar los 4 slugs hardcoded por `PermissionValidator::hasAnyDelegableInCourse()`.
   - `resources/js/pages/docente/CursoDetalle.svelte:392,782`: mostrar gestión de equipo según `manageTeam` (no solo `es_titular_curso`).
5. **ContextTypes y `viewAny`**:
   - Evaluar `assertPermisoEnActividad:139` sobre `$actividad` (no solo `$actividad->componente`) para honrar `ContextType::ACTIVIDAD`.
   - Corregir `Admin\ComponenteController::indexByCurso:91` para validar sobre `$curso` en vez de `viewAny` global-only.

---

## Fase 2 — Desacoplamiento de `$user->docente` y `docente_componente` (Delegación a Alumnos, Ayudantes y Externos)

1. **Null-safety de `$user->docente`**:
   - Hacer null-safe `$user->docente?->id_docente` en `DocenteCursoController::show:147`, `DocenteCursoController::docentes:443`, `Docente\DashboardController:58` y `DocenteActivityController`.
2. **Desacoplar guards de identidad física**:
   - Reemplazar `if (!$user->docente)` en `IsDocente.php:37`, `DocenteActivityController.php:134` y `AsistenciaController.php:339` por validación de capacidades exclusivas de gestión vía `PermissionValidator::hasAny()`.
   - Habilitar delegación a Ayudantes (y alumnos habilitados) en `DelegacionPermisosController::assertIsMiembroCurso:452` y `PermisosCursoService::miembrosDelCurso:633`.
3. **Descubrimiento basado en permisos**:
   - Conectar `resolveAccessibleCourseIds()` en `Docente\DashboardController`, `DocenteCursoController::index`, `UserCoursesService`, `AsistenciaController::centro` y `centroCalificaciones`.
   - Conectar `resolveAccessibleComponentIds()` en `DocenteCursoController::show` y `Actividades.svelte:138-147` (reemplazando el filtro duro por `misComponentesIds` / `c.es_mio`).
   - Mostrar tarjeta *"Cursos con permisos delegados"* en Dashboards de Estudiante/Ayudante cuando `resolveAccessibleCourseIds()` no sea vacío.
