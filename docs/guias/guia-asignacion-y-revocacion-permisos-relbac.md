# Guía de Asignación, Herencia y Revocación de Permisos (RelBAC)

> **Propósito**: Documentar mediante casos reales y ejemplos concretos cómo operan los roles prehechos (`URA`), los permisos especiales (`UPE`: otorgar y revocar), la jerarquía de contextos, la delegación entre docentes y la resolución efectiva tanto en backend como en frontend.

---

## 1. Modelo Conceptual: Las 4 Dimensiones y el Tri-Estado

En el modelo RelBAC de UTAMED, cada autorización responde a:

$$\text{Autorizado}(usuario, permiso, contexto, tiempo)$$

Existen dos fuentes de permisos que convergen sobre un mismo `(id_usuario, id_contexto)`:

1. **Roles asignados (`usuario.usuario_rol_asignacion` + `asignacion_rol_permiso`)**:
   - Colecciones predefinidas de permisos (`Docente Titular`, `Docente Componente`, `Docente Componente Colegiado`, `Ayudante`, `Jefe de Carrera`, etc.).
   - Cada permiso dentro de un rol define si es delegable mediante `puede_delegar_permiso` (`boolean`).
2. **Permisos especiales (`usuario.usuario_permiso_especial`)**:
   - Asignaciones quirúrgicas por usuario, permiso y contexto que permiten **3 estados de control**:
     - **`✓` Asignado (`GRANT`)**: Fila activa con `esta_permitido = true`.
     - **`—` No asignado (Neutro)**: Sin fila activa (`esta_activo = false`). Rige lo que otorgue un Rol base o un contexto superior.
     - **`✕` Revocado (`DENY`)**: Fila activa con `esta_permitido = false`. Deniega explícitamente el permiso en ese contexto y hacia abajo, incluso si un rol prehecho lo incluía.

---

## 2. Jerarquía de Contextos y Reglas de Precedencia

La cadena jerárquica académica sigue este orden descendente (de mayor a menor jerarquía):

```text
Global (id_contexto = 1)
  └── Facultad
        └── Departamento
              └── Carrera
                    └── Curso (id_contexto del curso)
                          └── Componente (Cátedra, Laboratorio, Taller…)
```

### Reglas de precedencia (De mayor a menor peso)

1. **`DENY` (`✕`) en jerarquía superior prevalece sobre todo**:
   - Si un permiso tiene `esta_permitido = false` en `Curso` (o `Carrera`, `Global`, etc.), **ningún** `GRANT` ni `Rol` en un `Componente` hijo puede habilitarlo.
2. **`DENY` (`✕`) en el mismo contexto prevalece sobre `Rol` (`URA`) y sobre empate de wildcards**:
   - Si un usuario tiene el rol `Docente Componente Colegiado` en `Laboratorio` (que incluye `actividades:ver`), pero se le registra `UPE (actividades:ver, esta_permitido = false)` en `Laboratorio` o en `Curso`, el usuario **pierde** el permiso.
3. **`GRANT` (`✓`) en jerarquía superior se hereda hacia abajo**:
   - Un permiso asignado en `Curso` cubre automáticamente a `Cátedra` y `Laboratorio` (superseding positivo), bloqueando su edición individual en los componentes hijos con candado.
4. **Permisos sin asignación especial (`—`)**:
   - Si no hay `UPE` que otorgue ni revoque en la cadena, prevalece el `Rol` (`URA`) asignado en el contexto o en sus ancestros válidos (respetando `config/permission-no-inherit.php` y `PermissionContextConstraints`).

---

## 3. Ejemplos Concretos de Uso

### Ejemplo 1: Docente Colegiado con Rol Prehecho ("Hilar Fino")

**Escenario**:
- *Amalia Flores* es asignada al componente **Laboratorio** (`id_contexto = 276`) del Curso 109 con el rol **`Docente Componente Colegiado`**.
- Según [`scripts/roles_config.php`](../../scripts/roles_config.php), ese rol incluye por defecto 4 permisos en el componente:
  - `componentes:ver`
  - `componentes/inscripciones:ver`
  - `actividades:ver`
  - `actividades/grupos:ver`

**Qué ocurre en la matriz de delegación**:
- En la pestaña **Todo el Curso (General)** (`id_contexto = 274`):
  - Ninguno de esos 4 permisos aparece heredado desde global ni desde curso (el rol es de nivel Componente).
- En la pestaña **Laboratorio** (`id_contexto = 276`):
  - Los 4 permisos aparecen en estado **`—` (No asignado especial)** pero marcados visualmente como **`Activo por Rol`** (tienen vigencia real gracias a su rol prehecho).
- Si el Docente Titular quiere que Amalia **no vea los grupos de actividad** en Laboratorio:
  - Pulsa **`✕` (Revocar)** en `actividades/grupos:ver` dentro de Laboratorio.
  - Se crea una fila `UPE` con `esta_permitido = false` en `id_contexto = 276`.
  - Amalia mantiene su rol `Docente Componente Colegiado` intacto para los otros 3 permisos, pero queda restringida en `actividades/grupos:ver`.

---

### Ejemplo 2: Otorgar en Curso Completo (`✓`) $\to$ Herencia en Componentes

**Escenario**:
- El Titular entra a la pestaña **Todo el Curso (General)** (`274`) y marca **`✓`** en `actividades:evaluar` para *Amalia Flores*.

**Resultado**:
1. Por grafo de dependencias ([`CoursePermissionGraph`](../../app/Support/CoursePermissionGraph.php)), se otorga automáticamente `actividades:evaluar` y su prerrequisito `actividades:ver` en el contexto `274`.
2. Al cambiar a las pestañas **Cátedra** (`275`) o **Laboratorio** (`276`), ambos permisos aparecen **bloqueados con candado por herencia** (`✓` heredado del padre): no se pueden desactivar ni duplicar en el componente.
3. Si el Titular solo hubiese otorgado `actividades:ver` en el Curso y luego va a **Laboratorio** a otorgar `actividades:evaluar`:
   - `actividades:evaluar` queda **`✓` directo** en Laboratorio (`276`).
   - `actividades:ver` **conserva su candado heredado** (no se crea UPE duplicada en `276` ni se convierte en modificable dentro de Laboratorio).

---

### Ejemplo 3: Asignar Abajo (`✓`) y Luego Revocar Arriba (`✕`) — Colisión Jerárquica

**Escenario**:
1. En **Laboratorio** (`276`), el Titular había asignado previamente **`✓`** en `actividades:evaluar`.
2. Más tarde, en **Todo el Curso (General)** (`274`), el Titular pulsa **`✕` (Revocar)** sobre `actividades:ver` (o sobre `actividades:evaluar`).

**Regla de negocio y flujo**:
1. El sistema detecta que *Amalia Flores* tiene asignaciones activas (`✓`) en niveles inferiores (`Laboratorio: actividades:evaluar`) que quedarían anuladas por esta revocación superior.
2. Se muestra una **advertencia previa de confirmación** al usuario listando los permisos en componentes inferiores que serán bloqueados.
3. Si el Titular confirma:
   - Se registra `UPE (esta_permitido = false)` en **Todo el Curso (`274`)**.
   - Se limpian/neutralizan las asignaciones directas en conflicto en **Laboratorio (`276`)** para evitar registros contradictorios en BD.
   - Al entrar a **Laboratorio**, la pastilla de `actividades:ver` (y la de `actividades:evaluar`, al depender de un prerrequisito revocado) aparece **bloqueada en rojo** con el atributo nativo `title` explicando que fue revocada en una jerarquía superior.

---

### Ejemplo 4: Restricciones del Actor que Delega (No Delegable y Revocado Superior)

**Caso A — Permiso no delegable (`puede_delegar = false`)**:
- El actor tiene rol `Docente Componente` en Cátedra, donde posee `actividades:editar` para uso personal, pero en `asignacion_rol_permiso` tiene `puede_delegar_permiso = false` (o el permiso es exclusivo de Titular/Admin como `cursos:eliminar`).
- **Resultado**: El actor **no puede** delegar (`✓`) ni revocar (`✕`) ese permiso a terceros. El control aparece deshabilitado (o filtrado de su catálogo delegable).

**Caso B — Permiso revocado en mayor jerarquía al propio actor**:
- Un usuario tiene `puede_delegar = true` sobre `actividades:editar` en un Componente específico (`UPE GRANT` con delegación), **pero** un superior (Jefe de Carrera o Admin) le revocó (`esta_permitido = false`) `actividades:editar` a nivel de `Curso` o `Carrera`.
- **Resultado**: Al validarse el actor de arriba hacia abajo, la revocación superior prevalece. El actor pierde la capacidad efectiva sobre ese permiso y **tampoco puede delegarlo ni revocarlo** en niveles inferiores.

---

### Ejemplo 5: Grafo de Dependencias y Feedback al Revocar

**Escenario**:
- En `Curso Completo`, *Amalia Flores* tiene únicamente:
  - `actividades:ver` = `✓`
  - `actividades:evaluar` = `✓`
  - `actividades:dar_feedback` = `—` (no asignado)
  - `actividades:editar` = `—` (no asignado)
- El Titular cambia `actividades:ver` de `✓` a `—` (o a `✕`).

**Regla de UI y backend**:
- El grafo downstream identifica que `evaluar`, `dar_feedback` y `editar` dependen de `ver`.
- **Solo se mutan y muestran check verde de confirmación (`confirmado`)** los permisos que **realmente estaban en `✓`** (`actividades:ver` y `actividades:evaluar`).
- `actividades:dar_feedback` y `actividades:editar`, que ya estaban en `—`, **no reciben spinner ni check verde** porque no sufrieron cambios de asignación.

---

### Ejemplo 6: Cómo Validan Backend y Frontend (Resumen Operativo)

| Capa | Qué evalúa | Formato de datos |
| :--- | :--- | :--- |
| **Motor Backend (`PermissionValidator`)** | Cadena completa de ancestros `[contexto ... global]`, wildcards (`*`, `recurso:*`), prioridad absoluta de `DENY` sobre `GRANT`/`ROL` y restricciones de `no-inherit`. | `bool` (`can(...)` / `hasPermission(...)`) |
| **Matriz de Administración (`DelegacionPermisosController`)** | Estado tri-estado por contexto (`asignado`, `neutro`, `revocado`), cobertura por `Rol` base, bloqueos por herencia (`✓` padre) o revocación (`✕` padre / prerrequisito) y capacidad de delegación del actor (`puede_delegar`). | Mapa completo por contexto (`permisos_por_contexto`) |
| **Vistas de Consumo Frontend (`CursoDetalle`, `Actividades`, `usePermissions`)** | No interpreta wildcards ni jerarquías por su cuenta. Recibe desde backend el conjunto **ya resuelto y expandido** de slugs efectivos permitidos para cada contexto del curso. | `string[]` (o mapa `{ [id_contexto]: string[] }`) evaluado en $O(1)$ con `hasPermission()` |
