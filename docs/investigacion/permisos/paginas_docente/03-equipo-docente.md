# Reporte de Auditoría: Gestión de Equipo Docente y Permisos de Miembros

- **Rutas Auditadas**:
  - `GET /docente/cursos/{curso}/team` (`docente.cursos.team.index`)
  - `GET /docente/cursos/{curso}/team/search-assistants` (`docente.cursos.team.search-assistants`)
  - `POST /docente/cursos/{curso}/team` (`docente.cursos.team.store`)
  - `DELETE /docente/cursos/{curso}/team/{usuario}` (`docente.cursos.team.destroy`)
  - `GET /docente/cursos/{curso}/team/{usuario}/permissions` (`docente.cursos.team.permissions`)
  - `POST /docente/cursos/{curso}/team/{usuario}/sync-permissions` (`docente.cursos.team.sync-permissions`)
- **Vistas Frontend**:
  - [`resources/js/pages/docente/DocentesCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DocentesCurso.svelte)
  - Modales de asignación y sincronización de ayudantes.
- **Controladores Backend**:
  - [`app/Http/Controllers/Admin/CourseTeamController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php)
  - [`app/Http/Controllers/Docente/DocenteCursoController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteCursoController.php)
- **Policy Asociada**:
  - [`app/Policies/CursoPolicy.php`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php)
- **Middlewares**: `['auth', 'verified', 'is_docente']`

---

## 1. Alcance y Flujo de Navegación

Este módulo permite al **Docente Titular** de un curso conformar su equipo de cátedra (ayudantes, docentes de apoyo) y regular los roles y permisos especiales que dichos miembros ejercen dentro del contexto del curso.

```mermaid
flowchart TD
    A[Docente Titular] --> R1["GET /docente/cursos/{curso}/team"]
    R1 --> P1["CursoPolicy@manageTeam($curso)"]
    P1 -->|Solo Titular Actual| C1[CourseTeamController@index]
    P1 -->|No es Titular| DENY1[403 Forbidden]
    
    A --> R2["POST /docente/cursos/{curso}/team"]
    R2 --> P2["CursoPolicy@manageTeam($curso)"]
    P2 --> C2[CourseTeamController@store]
    C2 --> V1{Es Docente?}
    V1 -->|Solo rol ayudante permitido| S1[Crea UsuarioRolAsignacion en Curso]
    V1 -->|Intento de rol admin/superadmin| ERR1[422 Error de Validacion]

    A --> R3["POST .../sync-permissions"]
    R3 --> P3["CursoPolicy@manageTeam($curso)"]
    P3 --> C3[DocenteCursoController@syncMemberPermissions]
    C3 --> V2{Auto-modificacion?}
    V2 -->|Target = Self| BLK1[Bloqueado: No puede modificar sus propios permisos]
    V2 -->|Target = Otro Miembro| S2[Transaccion DB: Sincroniza Roles y UPEs delegables]
```

---

## 2. Fase 1: Frontend (Svelte 5 / Inertia)

- **Vistas y Componentes**:
  - [`DocentesCurso.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/docente/DocentesCurso.svelte): Despliega nómina de componentes y docentes colegiados.
  - Modales de asignación: Permite buscar ayudantes por RUT o nombre e invoca los endpoints AJAX/JSON.
- **Protección de Interfaz**:
  - Los formularios solo muestran los roles autorizados (`ayudante`, `estudiante`) y los permisos de la categoría `Docencia`.

---

## 3. Fase 2: Enrutamiento y Middlewares

| Verbo | URI | Nombre de Ruta | Middlewares | Controlador |
|---|---|---|---|---|
| `GET` | `/docente/cursos/{curso}/team` | `docente.cursos.team.index` | `['auth', 'verified', 'is_docente']` | [`CourseTeamController@index`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L57) |
| `GET` | `/docente/cursos/{curso}/team/search-assistants` | `docente.cursos.team.search-assistants` | `['auth', 'verified', 'is_docente']` | [`CourseTeamController@searchAssistants`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L594) |
| `POST` | `/docente/cursos/{curso}/team` | `docente.cursos.team.store` | `['auth', 'verified', 'is_docente']` | [`CourseTeamController@store`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L130) |
| `DELETE` | `/docente/cursos/{curso}/team/{usuario}` | `docente.cursos.team.destroy` | `['auth', 'verified', 'is_docente']` | [`CourseTeamController@destroy`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CourseTeamController.php#L218) |
| `GET` | `/docente/cursos/{curso}/team/{usuario}/permissions` | `docente.cursos.team.permissions` | `['auth', 'verified', 'is_docente']` | [`DocenteCursoController@getMemberPermissions`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteCursoController.php#L617) |
| `POST` | `/docente/cursos/{curso}/team/{usuario}/sync-permissions` | `docente.cursos.team.sync-permissions` | `['auth', 'verified', 'is_docente']` | [`DocenteCursoController@syncMemberPermissions`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Docente/DocenteCursoController.php#L696) |

---

## 4. Fase 3 & 4: Controlador Backend, Policies y RelBAC

### 4.1. Autorización Estricta mediante `CursoPolicy@manageTeam`
Todos los métodos ejecutan en su primera línea:
```php
$this->authorize('manageTeam', $curso);
```
[`CursoPolicy@manageTeam`](file:///c:/Users/dyri0n/Code/utamed/app/Policies/CursoPolicy.php#L42) garantiza que:
1. Administradores / SuperAdmin tienen bypass.
2. Docentes **solo pueden gestionar si son el docente titular actual** (`$curso->id_docente_titular === $user->docente->id_docente`).
3. Docentes colegiados, ayudantes o ex-titulares son rechazados con 403 y se registra un evento de auditoría en el canal `seguridad` (`ACCESO_DENEGADO_MANAGEAM_TEAM`).

### 4.2. Prevención de Escalada de Privilegios en `store()`
- Si el usuario que ejecuta la petición es docente, se fuerza que el rol a asignar sea estrictamente `ayudante`:
  ```php
  if ($isDocente) {
      $rolesPermitidos = ['ayudante'];
      if (!in_array(strtolower($validated['role_name']), $rolesPermitidos)) {
          return back()->with('error', 'Como docente solo puedes asignar el rol de Ayudante.');
      }
  }
  ```

### 4.3. Prevención de Auto-Modificación y Delegación Ilegal en `syncMemberPermissions()`
1. **Anti-Auto-Modificación**:
   ```php
   if ($usuario->id_usuario === $user->id_usuario) {
       return back()->with('error', 'No puedes modificar tus propios permisos.');
   }
   ```
2. **Validación de Pertenencia del Usuario Destino**:
   - Verifica que el usuario `$usuario` pertenezca al equipo del curso (`isMember`) o sea docente de algún componente del curso (`isColegiadoDocente`).
3. **Regla de Delegación Acotada**:
   - El docente solo puede delegar permisos que él mismo posee en el contexto del curso (`$delegablePermIds = $this->getDelegablePermissions($user, $idContexto)`).
4. **Transaccionalidad Atómica**:
   - Operaciones envueltas en `DB::beginTransaction()` y `DB::commit()`.

---

## 5. Fase 5: Mapeo al Catálogo de Permisos

- Constantes aplicadas:
  - [`Permissions::CURSOS_VER`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L101) (`'cursos:ver'`)
  - [`Permissions::USUARIOS_PERMISOS_ROLES_GESTIONAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L174) (`'usuarios/permisos/roles:gestionar'`)
  - [`Permissions::USUARIOS_PERMISOS_INDIVIDUALES_GESTIONAR`](file:///c:/Users/dyri0n/Code/utamed/app/Support/Permissions.php#L165) (`'usuarios/permisos/individuales:gestionar'`)

---

## 6. Fase 6: Matriz de Seguridad y Veredicto

| Endpoint | Perímetro (Middleware) | Policy / RelBAC | Blindaje Anti-Escalada | Auditoría | Estado |
|---|:---:|:---:|:---:|:---:|:---:|
| `GET .../team` | `is_docente` | `CursoPolicy@manageTeam` | Solo miembros del contexto | Log Info | ✅ **CUMPLE** |
| `GET .../search-assistants` | `is_docente` | `CursoPolicy@manageTeam` | Búsqueda acotada a ayudantes | - | ✅ **CUMPLE** |
| `POST .../team` | `is_docente` | `CursoPolicy@manageTeam` | Restringido a rol `ayudante` | - | ✅ **CUMPLE** |
| `DELETE .../team/{u}` | `is_docente` | `CursoPolicy@manageTeam` | Soft-delete contextual | - | ✅ **CUMPLE** |
| `GET .../permissions` | `is_docente` | `CursoPolicy@manageTeam` | Validación de pertenencia target | Log Info | ✅ **CUMPLE** |
| `POST .../sync-permissions` | `is_docente` | `CursoPolicy@manageTeam` | Anti-self-edit + Permisos delegables | Transacción DB | ✅ **CUMPLE** |

**Veredicto**: Módulo **100% CUMPLE**. Presenta controles estrictos contra escalada horizontal y vertical de privilegios, validación contextual atómica y registro de eventos sospechosos.
