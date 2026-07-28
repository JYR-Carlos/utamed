# Módulo B — Docente / Actividades, Grupos, Evaluación y Entregas

**Alcance auditado:** `DocenteActivityController` (1318 LOC, 22 métodos), `CursoPolicy`,
`GrupoIndividualService`, `ConversacionDocenteService`, frontend `pages/docente/Activities/*` (2815 LOC).

---

## 🔴 Crítico

### B-1 · IDOR: tres endpoints de notas y descarga no verifican que la actividad pertenezca al curso
**Archivo:** `app/Http/Controllers/Docente/DocenteActivityController.php`

El controlador define el helper correcto en `:49`:

```php
private function assertActividadDeCurso(Curso $curso, Actividad $actividad): void
{
    if ($actividad->componente?->id_curso === $curso->id_curso) return;
    abort(404, 'Actividad no encontrada en este curso.');
}
```

Nueve métodos lo llaman. **Tres no**, y son los sensibles:

| Método | Línea | grupo↔actividad | actividad↔curso |
|---|---|---|---|
| `updateIntegrante` | 601 | ✅ | ❌ |
| `recalcularNotasIndividuales` | 632 | ✅ | ❌ |
| `descargarEntrega` | 1003 | ✅ | ❌ |

El único control restante es `authorize('viewPrograma', $curso)`, que valida el curso de la URL, no la
actividad. Explotación con sesión de docente legítima:

```
PUT /docente/cursos/{MI_CURSO}/actividades/{ACT_AJENA}/grupos/{G}/integrantes/{I}
    { "diferencia_decimas": 9.9 }              → modifica notas de otro curso

GET /docente/cursos/{MI_CURSO}/actividades/{ACT_AJENA}/grupos/{G}/entregas/{AGENDA}/descargar
                                               → descarga cualquier archivo del sistema
```

IDs secuenciales → enumeración trivial. **Fix:** añadir `$this->assertActividadDeCurso($curso, $actividad);`
en los tres.

### B-2 · `storeRubrica` escribe la rúbrica de cualquier actividad del sistema
**Archivo:** `DocenteActivityController.php:497-524`

```php
'id_actividad' => 'required|integer|exists:actividad,id_actividad',   // única validación
```

`exists:` solo confirma que la fila existe, no que pertenezca a `$curso`. Un docente con acceso a un
curso cualquiera sobrescribe la rúbrica de evaluación de cualquier otro.

### B-3 · `storeEvaluacion` cierra rúbricas ajenas
**Archivo:** `DocenteActivityController.php:1172` (validación) y `:1235-1238` (efecto)

```php
DB::table('agenda.rubrica')
    ->where('id_rubrica', $validated['id_rubrica'])
    ->where('estado_rubrica', 'POSTULADA')
    ->update(['estado_rubrica' => 'CERRADA']);
```

`id_rubrica` validado solo con `exists:`. El resto de `storeEvaluacion` sí está blindado (verifica
actividad↔curso, grupo↔actividad y entrega↔grupo) — el hueco es exclusivamente la rúbrica.

### B-4 · `store`/`update` no validan que el componente y la unidad pertenezcan al curso
**Archivo:** `DocenteActivityController.php:154-155` y `:218-219`

```php
'id_componente' => 'required|integer|min:1',
'id_unidad'     => 'required|integer|min:1',
```

Sin `exists`, sin `Rule::in` acotado al curso. El titular de A puede crear o mover actividades a
componentes de B. Agrava: el comentario de `:163` indica que `id_contexto` lo asigna el trigger
`tr_actividad_pre_insert` desde el componente → inyecta un registro en el árbol de contextos de otro
curso, con efectos sobre el RBAC entero (ver A-4/A-7).

---

## 🟠 Alto

### B-5 · Un permiso de lectura autoriza todas las escrituras destructivas
`authorize('viewPrograma', $curso)` gobierna: `eliminarGrupo`, `crearGrupo`, `quitarEstudianteDeGrupo`,
`agregarEstudianteAGrupo`, `updateIntegrante`, `recalcularNotasIndividuales`, `storeEvaluacion`,
`storeRubrica`, `enviarFeedback`, `descargarEntrega`, `copiarGruposDeActividad`.

`CursoPolicy::viewPrograma` (`:103-133`) concede acceso a cualquier docente asignado a **cualquier
componente** del curso. El docente de un laboratorio puede borrar los grupos y fijar las notas de la
cátedra completa.

Contraste: `CursoPolicy::manageTeam` (`:42-66`) sí es estricto (solo titular actual) y registra los
intentos fallidos en el canal `seguridad`. El control existe y está bien hecho; no se aplicó a notas.

### B-6 · Guard defectuoso en `update` y `destroy`
**Archivo:** `DocenteActivityController.php:204` y `:269`

```php
$actividadCursoId = $actividad->componente?->id_curso;
if ($actividadCursoId && $actividadCursoId !== $curso->id_curso) { abort(404, ...); }
```

Si la actividad no tiene componente, `$actividadCursoId` es null → condición falsa → **el guard se salta
y la operación procede**. `assertActividadDeCurso` invierte la lógica correctamente; estos dos métodos no
se migraron.

### B-7 · `GrupoIndividualService` ejecuta un bucle de queries en cada carga de pantalla
**Archivo:** `DocenteActivityController.php:406-408` (y `:172`)

`asegurarGruposDelCurso` recorre todos los inscritos y por cada uno ejecuta un `whereHas` + hasta dos
INSERT. Curso de 60 estudiantes = 60+ queries **en cada GET** de la pantalla de evaluación. Es una
reparación de datos ejecutándose como si fuera lectura; debería ser un comando o un evento de inscripción.

### B-8 · N+1 anidado en el centro de calificaciones
**Archivo:** `DocenteActivityController.php:346`

`Actividad::where('id_componente', ...)` dentro del `map()` sobre componentes, que a su vez está dentro
del `map()` sobre cursos. 8 cursos × 3 componentes = 24 queries + las de `withCount`.

### B-9 · Doble round-trip en cada mutación del frontend
**Archivo:** `resources/js/pages/docente/Activities/Index.svelte:294, 304, 413, 685`

```js
router.put(url, data, { onSuccess: () => router.reload({ only: ['grupos'] }) });
```

El controlador ya responde `redirect()->back()`, que hace que Inertia re-renderice con props frescos. La
recarga adicional vuelve a ejecutar `showEvaluacion()` completo — incluido el bucle de B-7. Cada ajuste
de una décima dispara dos ciclos de esa pantalla.

---

## 🟡 Medio

| # | Hallazgo | Ubicación |
|---|---|---|
| B-10 | RUT de estudiantes en los JSON de entregas; la UI solo muestra el nombre. | `:943`, `:991` |
| B-11 | Modelos completos a la vista: `array_merge($curso->toArray(), …)` + `$actividades`, `$componentes`, `$unidades` sin `select()`. `showEvaluacion` (`:461-483`) sí mapea campo a campo — dos criterios opuestos en el mismo archivo. | `:122-128` |
| B-12 | Payload completo al log en cada creación: `'payload' => $request->except(['_token'])`. | `:138-141` |
| B-13 | Ruta física concatenada sin normalizar: `storage_path('app/' . $agenda->archivo->ruta_fisica)`, sin `realpath()` ni verificación de que quede dentro del directorio. Defensa en profundidad; depende del módulo C. | `:1026` |
| B-14 | TOCTOU en `max_integrantes`: `count()` y luego `create()` sin bloqueo ni constraint. | `:765-776` |
| B-15 | `crearGrupo` no impide que un estudiante quede en dos grupos de la misma actividad; `nombre_grupo` se valida pero nunca se persiste. | `:671-711` |
| B-16 | `Inertia::lazy()` está deprecado en Inertia 2 → `Inertia::optional()`. | `:484`, `:1103` |

---

## ✅ Verificado correcto

- **El patrón de defensa existe y está centralizado**: 9 de 12 métodos llaman `assertActividadDeCurso`.
  B-1 es aplicación incompleta, no ausencia de diseño — la corrección son tres líneas.
- `crearGrupo`, `agregarEstudianteAGrupo` y `copiarGruposDeActividad` verifican inscripción real en el
  curso antes de tocar grupos (`:685-693`, `:747-753`, `:865-870`).
- `enviarFeedback` (`:1266-1275`) valida grupo↔curso con un JOIN explícito de tres tablas.
- `storeEvaluacion` es transaccional y verifica que la entrega referenciada pertenece al grupo (`:1182-1191`).
- **Frontend de alta calidad**: cero `$:` legacy en las 28 páginas docente, props tipadas,
  `$state`/`$derived`, `router.reload({only:[...]})` en 6 sitios, `Inertia::lazy` en servidor para las
  conversaciones. Los `fetch()` nativos son GET con `credentials: 'same-origin'` y están documentados como
  tales; las mutaciones usan `router.post` con comentario explícito sobre CSRF (`Index.svelte:382`).
- El patrón `grupoSnap` (`Index.svelte:381`) evita usar estado obsoleto en callbacks asíncronos.
