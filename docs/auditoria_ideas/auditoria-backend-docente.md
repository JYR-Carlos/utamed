# Auditoría de buenas prácticas — `app/Http/Controllers/Docente/`

**Fecha:** 2026-06-24
**Alcance:** `app/Http/Controllers/Docente/**` (15 archivos, ~5.6k líneas) y sus rutas en `routes/web.php`.
**Estado:** Borrador para acción. Pensado para que otra IA / desarrollador tome cada hallazgo como tarea independiente.
**Hermana de:** [`auditoria-frontend-docente.md`](./auditoria-frontend-docente.md) (hallazgos `D-01..D-11`). Varios hallazgos de aquí son la "otra cara" de los del frontend.

## Cómo usar este documento

Cada hallazgo tiene:
- **ID** estable (ej. `B-01`) para referenciarlo en commits/PRs.
- **Severidad**: 🔴 Alta · 🟠 Media · 🟢 Baja.
- **Evidencia** con `archivo:línea`.
- **Acción propuesta** concreta y autocontenida.

Antes de tocar cualquier endpoint, leer el ADR del proyecto: [`docs/0001-uso-de-inertia-sobre-rest.md`](../0001-uso-de-inertia-sobre-rest.md).

---

## Resumen ejecutivo

La carpeta es **funcional y, en su mayoría, está bien documentada** (cabeceras de clase que listan tablas implicadas, métodos con propósito claro, `MensajesController`/`AsistenciaController`/`JefeCarrera/*` son ejemplares). Los problemas **no son de "no funciona"**, sino de **deuda estructural que encarece cada cambio y enturbia el contrato con el frontend**:

1. **Doble API de grupos** (`DocenteActivityController`): conviven dos juegos de métodos casi idénticos — uno en español (Inertia/redirect) y otro en inglés (JSON) — **ambos ruteados**, y el frontend vivo consume una **mezcla** de los dos. Hay métodos que ya nadie llama.
2. **Permisos granulares definidos pero no aplicados**: `DelegacionPermisosController`/`CursoPermisosController` definen un catálogo fino (`actividades:evaluar`, `actividades:editar`…) y permiten delegarlos, pero `DocenteActivityController` autoriza todo con `viewPrograma`/`manageTeam` genéricos e incluso devuelve `userPermissions = []`. La delegación es, hoy, en buena parte decorativa.
3. **Lógica de permisos triplicada**: tres controladores reimplementan `syncPermiso`/`ensureContext`/`buildPermisosMap` con criterios que **ya divergieron**.
4. **Contrato de respuesta incoherente** (JSON vs redirect Inertia), incluso en mutaciones — roza el ADR-0001.
5. **Helpers reinventados** (nombre completo, query grupos→mensajes) repetidos en ~15 sitios.

Prioridad sugerida: **B-01 → B-03 → B-09** (limpieza de bajo riesgo/alto impacto) → **B-02 → B-04 → B-05** (consolidación) antes del resto.

---

## 🔴 Hallazgos de severidad alta

### B-01 — Doble API de gestión de grupos (español vs inglés), ambas ruteadas
**Severidad:** 🔴 · **Esfuerzo:** Medio

`DocenteActivityController` mantiene **dos familias paralelas** de endpoints para lo mismo (crear grupo, borrar grupo, alta/baja de integrante), y `routes/web.php` registra **ambas**:

| Acción | Método "español" (Inertia) | Método "inglés" (JSON) |
|---|---|---|
| Crear grupo | `storeGrupo` (`:590`, ruta `:377`) | `storeGroup` (`:789`, ruta `:388` `grupos-create`) |
| Borrar grupo | `deleteGrupo` (`:642`, ruta `:379`) | `deleteGroup` (`:542`, ruta `:391` `grupos-delete`) |
| Alta integrante | `addIntegrante` (`:660`, ruta `:380`) | `addStudentToGroup` (`:860`, ruta `:389`) |
| Baja integrante | `removeIntegrante` (`:768`, ruta `:383`) | `removeStudentFromGroup` (`:565`, ruta `:390`) |

El frontend vivo (`Activities/Index.svelte`) consume una **mezcla incoherente**: usa la familia inglesa para crear/borrar grupo y alta/baja (`grupos-create`, `grupos-delete`, `…/estudiante`, `…/estudiantes/{id}` — ver `Index.svelte:207,231,245,256`) **pero** la española para décimas, recálculo y evaluación (`…/integrantes/{asignado}`, `…/recalcular-notas`, `…/evaluacion` — `Index.svelte:290,302,401`).

Consecuencia: los métodos **españoles de grupos quedan, casi con seguridad, muertos** (`storeGrupo`, `deleteGrupo`, `addIntegrante`, `removeIntegrante`, y probablemente `updateGrupo` `:621`), pero siguen ruteados y mantenidos. Además `deleteGrupo` (`:642`) y `deleteGroup` (`:542`) son **literalmente el mismo cuerpo** (transacción que borra integrantes + grupo).

**Acción:**
1. Confirmar con búsqueda en `resources/js/pages/{docente,ayudante}/**` qué rutas se invocan realmente (ojo: `resources/js/actions|routes/**` está **autogenerado**, no cuenta como uso real).
2. Elegir **una** familia como canónica. Recomendado: la que ya usa el frontend nuevo (inglesa para CRUD de grupo/integrante) o, mejor, **renombrar todo a un esquema consistente** (un único idioma + un único contrato).
3. Borrar los métodos y rutas huérfanos.
4. De paso, decidir el contrato: si se queda Inertia, que **todas** devuelvan `redirect()->back()` (ver B-08).

---

### B-02 — Permisos granulares definidos y delegables, pero no aplicados al evaluar/editar
**Severidad:** 🔴 · **Esfuerzo:** Medio-Alto

`DelegacionPermisosController::DELEGABLE_MATRIX` (`:38-90`) y `CursoPermisosController` (`:29-49`) definen un catálogo fino: `actividades:evaluar`, `actividades:editar`, `actividades:crear`, `componentes/asistencia:registrar`, etc., y ofrecen UI para **delegarlos** a colegiados/ayudantes. Pero el controlador que ejecuta esas acciones **no los comprueba**:

- `DocenteActivityController` autoriza casi todo con `$this->authorize('viewPrograma', $curso)` — un permiso de **lectura** — incluyendo **mutaciones**: `deleteGroup` (`:544`), `storeGrupo` (`:592`), `updateGrupo` (`:623`), `updateIntegrante` (`:717`), `storeEvaluacion` (`:1383`), `sendFeedback` (`:1482`). Crear/editar actividades usa `manageTeam` (`:98,198`), también genérico.
- Peor: `show()` calcula y devuelve `'userPermissions' => []` **fijo** (`:82-85`), de modo que el frontend de actividades nunca recibe los permisos granulares del docente.

Resultado: un ayudante con `actividades:evaluar` pero **sin** `viewPrograma` no podría; y al revés, cualquiera con `viewPrograma` puede evaluar aunque no se le haya delegado. La delegación fina es, en la práctica, **decorativa** para este módulo.

**Acción:**
1. Decidir el modelo: ¿los permisos finos gobiernan estas acciones o sólo el rol de titular/colegiado? Documentarlo (1 párrafo en el ADR o en la cabecera del controlador).
2. Si gobiernan: sustituir los `authorize('viewPrograma'…)` de **mutación** por chequeos del permiso correspondiente (`actividades:evaluar`, `actividades:editar`, `actividades/grupos:crear`…), idealmente vía Policy o `Gate` que ya lee `UsuarioPermisoEspecial`.
3. Poblar `userPermissions` real en `show()`/`showEvaluacion()` (ya existe `getAllPermissions($curso->id_contexto)` y se usa en `DocenteCursoController::show:242`).
4. Si **no** gobiernan: marcar la matriz delegable como "informativa" y quitar la UI que sugiere lo contrario, para no engañar al usuario.

---

### B-03 — `storeFile()` es un stub roto pero está ruteado
**Severidad:** 🔴 · **Esfuerzo:** Bajo

`DocenteActivityController::storeFile()` (`:149-191`, ruta `web.php:468`) **no funciona**:
- Todos los `catch` están **vacíos** (`:173-188`): traga `FileValidationException`, `VirusDetectedException`, `StorageException`, etc. sin hacer nada.
- **No retorna ninguna respuesta** en ninguna rama → el cliente recibe `null`/200 vacío incluso si el archivo se rechazó por virus o validación.
- Quedan `// TODO` y comentarios de pseudo-código (`:162-171,187`).

Es código a medio terminar conectado a una ruta pública: silencia errores de subida de archivos (incluida detección de virus). Riesgo funcional y de seguridad.

**Acción:**
1. O implementarlo de verdad (responder éxito/`redirect()->back()` y, en cada `catch`, registrar y devolver un error usable; manejar el `$storedFile->deleteFromDisk()` del TODO en fallo de DB).
2. O, si la subida de adjuntos de actividad se hace por otra vía, **eliminar método + ruta**.
3. Mientras tanto, no dejar `catch {}` vacíos: como mínimo `Log::error` + re-throw.

---

### B-04 — Lógica de permisos especiales triplicada y ya divergente
**Severidad:** 🔴 · **Esfuerzo:** Medio

Tres controladores manipulan `UsuarioPermisoEspecial` con código casi calcado:

- `CursoPermisosController::syncPermiso` (`:308-359`) y `DelegacionPermisosController::syncPermiso` (`:308-360`) son **prácticamente idénticos** (mismo upsert con `now()->addYears(5)`, mismo soft-delete).
- `ensureContext` está copiado en `CursoPermisosController:207` y `DelegacionPermisosController:177` (mismo `firstOrCreate` por `contexto_display`).
- `getPermisosEnContexto` (`CursoPermisos:288`) ≈ `buildPermisosMap` (`Delegacion:253`) ≈ el filtrado de `special_permissions` en `DocenteCursoController::getMemberPermissions` (`:650`).
- `getDocentesCurso` (`CursoPermisos:240`) ≈ `getMiembrosCurso` (`Delegacion:224`) — misma consulta `componentes.docenteComponentes.docente.usuario`.

**Divergencias ya presentes** (señal de bug latente):
- Regla `exists` del `id_usuario`: `CursoPermisos:104` usa `exists:usuario,id_usuario`; `Delegacion:155` usa `exists:usuario.usuario,id_usuario` (con esquema). Una de las dos está mal calificada (ver B-11).
- `DocenteCursoController::syncMemberPermissions` (`:696`) reimplementa **otra vez** el sync, esta vez vía `givePermission(...)->on($curso)`, distinto a los dos `syncPermiso` directos.

**Acción:**
1. Extraer un servicio `PermisosCursoService` (o un trait) con `syncPermiso`, `ensureContext`, `mapaPermisos`, `miembrosDelCurso`.
2. Unificar el mecanismo de escritura: o todo por `UsuarioPermisoEspecial::create/update` o todo por el builder `givePermission()`. No ambos.
3. Centralizar la calificación de esquema de las reglas `exists` (B-11).

---

## 🟠 Hallazgos de severidad media

### B-05 — `JefeCarreraController` ignora el trait `ResolvesJefaturaCarrera`
**Severidad:** 🟠 · **Esfuerzo:** Bajo

Existe el trait `JefeCarrera/ResolvesJefaturaCarrera` (`:24-77`) con `resolveJefatura()`, `jefaturaOrAbort()` y `carreraIdOrAbort()`, usado limpiamente por `PlanController`, `AsignaturaController`, `AsignacionPlanController`, `CarreraController`. Pero `JefeCarreraController`:
- **Re-implementa `resolveJefatura()`** (`:803-828`) — copia exacta del trait.
- Repite en **cada** acción el preámbulo `if (!$user->docente) … ; $jefatura = $this->resolveJefatura($user); if (!$jefatura) …` (`dashboard:29-37`, `seguimiento:105-114`, `metricas:205-214`, `programaPreview:322-327`, `aprobarPrograma:361-365`, `rechazarPrograma:391-395`) en vez de `jefaturaOrAbort()`.
- `DashboardController::index:52-66` **vuelve a duplicar** la misma query de jefatura (`UsuarioRolAsignacion … 'Jefe de Carrera' … categoria 'carrera'`).

**Acción:**
1. `JefeCarreraController` debe `use ResolvesJefaturaCarrera` y borrar su `resolveJefatura` privado.
2. Reemplazar los preámbulos por `$jefatura = $this->jefaturaOrAbort();` (ajustando la redirección vs `abort(403)` según UX deseada — quizá añadir un `jefaturaOrRedirect()` al trait).
3. `DashboardController` puede reutilizar el mismo trait para el badge de jefatura.

---

### B-06 — Helper "nombre completo" reinventado ~15 veces (PHP y SQL)
**Severidad:** 🟠 · **Esfuerzo:** Bajo-Medio

La concatenación del nombre del usuario está copiada por todos lados, en **dos dialectos**:

- **PHP** `trim((nombre1).' '.(nombre2).' '.(apellido1).' '.(apellido2))`: `DocenteActivityController:388,428,1096,1150`, `DocenteCursoController:187,227,383,400`, `AsistenciaController:271`, `CursoPermisosController:253,275`, `DelegacionPermisosController:237`.
- **SQL** `DB::raw("TRIM(CONCAT(u.nombre1,' ',COALESCE(u.nombre2,''),' ',u.apellido1,' ',COALESCE(u.apellido2,'')))")`: `DocenteActivityController:475,1228,1297,1351,1555`, `DocenteCursoController:335`, `MensajesController:184,202`.

Además **no son consistentes**: a veces incluyen `nombre2`/`apellido2`, a veces no (`AsistenciaController:271` omite `nombre2`; `MensajesController:184` usa sólo `nombre1+apellido1`).

**Acción:**
1. Añadir un accessor `getNombreCompletoAttribute()` en `App\Models\Usuario\Usuario` como **fuente de verdad** para el caso Eloquent.
2. Para las queries crudas, crear un helper PHP (`NombreUsuario::completo($row)`) o una **vista/función SQL** reutilizable, y usarla en los `DB::raw`.
3. Decidir el formato canónico (¿con segundos nombres/apellidos?) y aplicarlo en todos.

---

### B-07 — Query "grupos del estudiante → mensajes" duplicada 4–5 veces
**Severidad:** 🟠 · **Esfuerzo:** Medio

El mismo bloque (resolver los `id_actividad_asignada_grupo` de un estudiante en un curso y traer la conversación filtrando `tipo_mensaje IN ('Mensaje al profesor','Feedback'[,'Entrega…','Evaluación'])`) aparece replicado:

- `DocenteActivityController::showMensajesCurso` (closure lazy, `:1273-1304`)
- `DocenteActivityController::getMensajesEstudiante` (`:1327-1356`) — JSON, **misma** consulta
- `DocenteActivityController::getGrupoMensajes` (`:1543-1571`)
- `DocenteActivityController::showEvaluacion` (closure `interaccionesGrupo`, `:463-494`)
- `DocenteCursoController::show` (closure lazy `:311-341`) — **otra copia** del mismo join
- Relacionado: el cálculo de "pendientes" con `DISTINCT ON` está en `MensajesController::pendientesPorActividad:244` **y** `DashboardController:92-107`.

El `array_merge((array)$m, [...])` que decora cada mensaje (`es_de_docente`, `es_retroalimentacion`, `es_entrega`…) también está duplicado (`:482-493` y `:1561-1571`).

**Acción:**
1. Extraer un `ConversacionDocenteService` (o query builder) con: `gruposDeEstudianteEnCurso($curso,$est)`, `mensajesDeGrupos($grupoIds, $tipos)`, `pendientesPorActividad($cursoIds)` y el mapeo decorador.
2. Reemplazar las 5 copias por llamadas al servicio. Reduce superficie de bug cuando cambien los tipos de mensaje.

---

### B-08 — Contrato de respuesta incoherente: JSON vs redirect Inertia (incluso en mutaciones)
**Severidad:** 🟠 · **Esfuerzo:** Medio

Conviven dos contratos sin criterio claro, a veces **dentro del mismo método**:

- Mutaciones que devuelven **JSON con códigos HTTP**: `storeGroup` (`:800,811,853` → 422/500 pero éxito como `redirect()->back()` `:848` — **incoherente consigo mismo**), `addStudentToGroup` (`:875,888,921` → 404/422/201), `copyGroupsFromActivity` (`:1043,1052`).
- Lecturas en JSON puro: `getGroupsByActivity`, `getSubmissionsByActivity/ByGroup`, `getGrupoMensajes`, `getMensajesEstudiante`, `getBysCursoJson`, `DocenteUnidadController::index`, `AsistenciaController::index`, `JefeCarreraController::programaPreview`.
- El resto del módulo es Inertia puro (`redirect()->back()->with(...)`).

El ADR-0001 empuja a Inertia; tener mutaciones que responden `201/422` rompe el manejo uniforme de errores del frontend (mezcla `try/catch` de fetch con flash de Inertia, justo el síntoma de `D-03`).

**Acción:**
1. Definir la regla (alineada con la decisión de `D-03`): **(a)** mutaciones siempre `redirect()->back()` con `withErrors`, JSON sólo para GET auxiliares lazy; o **(b)** API JSON explícita y separada. Documentarla.
2. Como mínimo, arreglar la incoherencia interna de `storeGroup`/`addStudentToGroup` (éxito redirect + error JSON).

---

### B-09 — Ruta `cursos/{curso}/actividades/json` registrada por triplicado
**Severidad:** 🟠 · **Esfuerzo:** Bajo

`getBysCursoJson` se registra **tres veces** con el **mismo nombre** `cursos.actividades.json`: `web.php:107`, `web.php:366` y `web.php:491`. Con nombres de ruta repetidos, `route('cursos.actividades.json')` resuelve a la última definición y las demás quedan como ruido (y posible confusión de prefijos/middleware entre grupos).

**Acción:**
1. Dejar **una** definición (la del grupo de middleware correcto para docente) y borrar las otras dos.
2. Revisar el resto de `routes/web.php` por nombres duplicados (`php artisan route:list` ayuda a detectarlos).

---

### B-10 — Fuga de `$e->getMessage()` al usuario final
**Severidad:** 🟠 · **Esfuerzo:** Bajo

Varios `catch` devuelven el mensaje crudo de la excepción al cliente: `store` (`:145`), `update` (`:228`), `storeGroup` (`:853`), `addStudentToGroup` (`:928`), `copyGroupsFromActivity` (`:1053`), `storeEvaluacion` (`:1473`), `syncMemberPermissions` (`:809`), `DocenteUnidadController:64,92,118`, `JefeCarrera/PlanController:77,117`, `AsignaturaController:84,121,141`. Esto expone detalles internos (SQL, nombres de tabla, rutas) y degrada UX.

**Acción:**
1. Mensaje genérico al usuario ("No se pudo crear la actividad, intenta nuevamente") + `Log::error` con el detalle (varios ya loguean; basta con no concatenar `getMessage()` en el flash).
2. Considerar un handler central en `app/Exceptions` para uniformar.

---

### B-11 — Reglas `exists` con calificación de esquema inconsistente
**Severidad:** 🟠 · **Esfuerzo:** Bajo

El proyecto usa PostgreSQL con varios esquemas (`usuario.`, `curso.`, `agenda.`). Las reglas `exists` no son consistentes y algunas podrían no resolver la tabla correcta:

- `DelegacionPermisosController:155` → `exists:usuario.usuario,id_usuario` (con esquema)
- `CursoPermisosController:104,171` → `exists:usuario,id_usuario` (sin esquema)
- `DocenteActivityController:597,670` → `exists:usuario.estudiante,id_estudiante`
- `DocenteActivityController:1394,1395` → `exists:pgsql.agenda.agenda,…` / `exists:pgsql.agenda.rubrica,…` (prefijo de **conexión** `pgsql.` dentro del `exists`)
- `:982` → `exists:agenda.actividad,id_actividad`

Tres convenciones distintas (`tabla`, `esquema.tabla`, `conexion.esquema.tabla`) conviviendo. Al menos una variante probablemente no apunta donde se cree (riesgo de validación que siempre pasa o siempre falla).

**Acción:**
1. Definir **una** convención (lo más seguro: `Rule::exists((new Modelo)->getConnectionName()...` o `esquema.tabla` consistente) y aplicarla.
2. Escribir un test que envíe un id inexistente a cada endpoint y verifique el 422.

---

### B-12 — Guard "actividad pertenece al curso" repetido ~12 veces
**Severidad:** 🟠 · **Esfuerzo:** Bajo-Medio

El patrón `if ($actividad->componente?->id_curso !== $curso->id_curso) abort(404, …)` se repite en casi cada método de `DocenteActivityController` (`:202,240,546,569,794,865,942,972,1071,1119,1385,1525`). Lo mismo con la verificación "grupo pertenece a la actividad" (`firstOrFail`/`exists` repetido).

**Acción:**
1. Usar **scoped route binding** de Laravel: declarar `actividad` anidada bajo `curso` para que el binding valide la pertenencia automáticamente, o
2. Extraer un helper `assertActividadDeCurso($curso,$actividad)` y `assertGrupoDeActividad(...)` en el controlador (o un trait), y llamarlos al inicio.

---

## 🟢 Hallazgos de severidad baja (pulido)

### B-13 — Comentarios "Autor/Fecha" embebidos (gemelo de `D-10`)
**Severidad:** 🟢

Bloques `// 1. Autor: Juan Y. // 2. Fecha: … // 3. …` en `DocenteActivityController:402-405,1361-1366,1420-1422,1463-1465,1500-1503,1538-1542`. Git ya registra autoría/fecha; estos sellos envejecen y ensucian. **Acción:** conservar el *qué/por qué*, eliminar *quién/cuándo*.

### B-14 — `ensureContext()` escribe en endpoints de lectura
**Severidad:** 🟢

`CursoPermisosController::syllabusIndex` (GET) llama `ensureContext` (`:67`), que hace `firstOrCreate` + `update` del curso. Igual `componenteIndex:135` y `DelegacionPermisosController::index:110`. Un GET no debería tener efectos de escritura (rompe idempotencia, problemático con prefetch/caché). **Acción:** mover la creación de contexto a un punto de escritura (al crear el curso/componente, o a un job de migración de datos), y que las vistas asuman que ya existe.

### B-15 — N+1 en `centroCalificaciones`
**Severidad:** 🟢

`DocenteActivityController::centroCalificaciones` (`:311-355`) hace `Actividad::where('id_componente',…)->withCount(…)->get()` **dentro** del `->map` por componente (`:318`) → una query por componente por curso. **Acción:** precargar las actividades con `with(['componentes.actividades' => …])` y `withCount` en la consulta inicial, o agrupar las actividades en una sola query por los `id_componente` recolectados.

### B-16 — Pequeñas redundancias y logging verboso
**Severidad:** 🟢

- `showEvaluacion` calcula `$esTitular` **dos veces** (`:371` y `:420`).
- `store` loguea el **payload completo** en `Log::info` (`:100-103,122`) — ruido y posible PII en logs de producción; bajar a `debug` o quitar.

### B-17 — Falta de paginación en listados potencialmente grandes
**Severidad:** 🟢

`estudiantesDelComponente`, `getSubmissionsByActivity`, `getGrupoMensajes`, `showMensajesCurso` y los `->get()` de inscripciones traen **todo** sin paginar. En cursos masivos crece la carga. **Acción:** paginar (o `limit`) las conversaciones/entregas largas; al menos las de mensajería.

### B-18 — Doble fuente de verdad de la fórmula de nota (liga con `D-02`)
**Severidad:** 🟢

El frontend define `calcularNotaChilena` (puntaje→nota, `lib/notas.ts`) y el backend define `calcularNotaIndividual` (nota grupal + décimas, acotada 1.0–7.0, `DocenteActivityController:698-707`). Son fórmulas **distintas** que conviven en el flujo de evaluación. **Acción:** documentar cuál es la fuente de verdad de cada cálculo y verificar que el frontend no recalcule lo que el backend persiste (y viceversa). El backend `calcularNotaIndividual` debería ser el canónico para `nota_individual`.

---

## Tabla de seguimiento

| ID | Hallazgo | Sev | Esfuerzo | Archivos clave |
|----|----------|-----|----------|----------------|
| B-01 | Doble API de grupos (es/en) ruteada | 🔴 | Medio | `DocenteActivityController` `:542,565,590,642,660,768,789,860`; `web.php:377-391` |
| B-02 | Permisos granulares no aplicados | 🔴 | Medio-Alto | `DocenteActivityController` (authorize/`userPermissions`); `Delegacion/CursoPermisos` |
| B-03 | `storeFile()` stub roto y ruteado | 🔴 | Bajo | `DocenteActivityController:149`; `web.php:468` |
| B-04 | Lógica de permisos triplicada | 🔴 | Medio | `CursoPermisos`, `Delegacion`, `DocenteCurso` |
| B-05 | `JefeCarreraController` no usa el trait | 🟠 | Bajo | `JefeCarreraController:803`; `ResolvesJefaturaCarrera` |
| B-06 | "Nombre completo" reinventado ~15× | 🟠 | Bajo-Medio | varios + `Models/Usuario` |
| B-07 | Query grupos→mensajes duplicada 5× | 🟠 | Medio | `DocenteActivityController`, `DocenteCurso`, `Mensajes`, `Dashboard` |
| B-08 | Contrato JSON vs Inertia incoherente | 🟠 | Medio | `DocenteActivityController` (storeGroup/addStudent…) |
| B-09 | Ruta `actividades/json` ×3 | 🟠 | Bajo | `web.php:107,366,491` |
| B-10 | Fuga de `$e->getMessage()` | 🟠 | Bajo | 10+ catch en varios controladores |
| B-11 | `exists` con esquema inconsistente | 🟠 | Bajo | `Delegacion:155`, `CursoPermisos:104`, `DocenteActivity:1394` |
| B-12 | Guard actividad↔curso repetido ×12 | 🟠 | Bajo-Medio | `DocenteActivityController` |
| B-13 | Comentarios Autor/Fecha | 🟢 | Bajo | `DocenteActivityController` |
| B-14 | `ensureContext` escribe en GET | 🟢 | Bajo | `CursoPermisos`, `Delegacion` |
| B-15 | N+1 en `centroCalificaciones` | 🟢 | Bajo | `DocenteActivityController:311` |
| B-16 | Redundancias + log verboso | 🟢 | Bajo | `DocenteActivityController:100,371` |
| B-17 | Falta de paginación | 🟢 | Bajo | listados con `->get()` |
| B-18 | Doble fórmula de nota (con D-02) | 🟢 | Bajo | `DocenteActivityController:698` + `lib/notas.ts` |

## Notas para quien implemente

- **No hacer "big bang".** Cada ID es un PR pequeño y verificable.
- **B-02 y B-03 tienen implicación de seguridad** (autorización efectiva y errores de subida silenciados): priorizar.
- **B-01 es el de mayor reducción de código** sin perder funcionalidad: tras confirmar el frontend, borra ~4 métodos + rutas.
- Verificar todo con la cadena del proyecto: `php artisan route:list` (para B-01/B-09) y los tests/feature que existan del módulo docente.
- Antes de borrar métodos "muertos", confirmar con búsqueda en `resources/js/pages/**` (no en `resources/js/actions|routes/**`, que es autogenerado).
