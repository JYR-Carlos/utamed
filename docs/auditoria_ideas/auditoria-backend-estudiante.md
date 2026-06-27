# Auditoría Backend — Controllers Estudiante
> Fecha: 2026-06-26 | Rama: `admin` | Directorio: `app/Http/Controllers/Student/`

---

## Resumen ejecutivo

| Categoría | Hallazgos |
|---|---|
| Bugs / crashes potenciales | 4 |
| Código muerto | 6 |
| Calidad / inconsistencias | 5 |
| Total | 15 |

---

## 1. Bugs y crashes potenciales

### BC-01 — `ActivityController`: crash si el estudiante no tiene grupo asignado
**Archivo:** `ActivityController.php:68-69`

```php
// ❌ Actual — $grupo puede ser null (actividad no asignada aún)
$restoIntegrantes = IntegranteGrupo
    ::where('id_actividad_asignada_grupo','=',$grupo->id_actividad_asignada_grupo)
    // ^^ Fatal error: Call to a member function on null
```

```php
// ✅ Correcto — agregar guardia
$restoIntegrantes = $grupo
    ? IntegranteGrupo::where('id_actividad_asignada_grupo', $grupo->id_actividad_asignada_grupo)
        ->where('id_estudiante', '!=', $estudiante->id_estudiante)
        ->get()
        ->map(fn($i) => [...])
    : collect();
```

---

### BC-02 — `ActivityController`: `$ultimaEntrega` puede ser undefined
**Archivo:** `ActivityController.php:132-139,173`

```php
// ❌ Actual — $ultimaEntrega solo se define dentro del bloque if ($grupo)
if ($grupo) {
    // ...
    $ultimaEntrega = null; // ← definida aquí
    foreach (array_reverse($interacciones) as $item) { ... }
}
// Si $grupo === null, $ultimaEntrega nunca se declara
// y la línea 173 produce "Undefined variable $ultimaEntrega"
'ultima_entrega' => $ultimaEntrega,
```

```php
// ✅ Correcto — inicializar antes del bloque condicional
$ultimaEntrega = null;
if ($grupo) { ... }
```

---

### BC-03 — `ActivityController`: `$estado` puede ser un objeto Enum, no un string
**Archivo:** `ActivityController.php:147,174`

```php
// ❌ Actual — si el modelo castea el campo a Enum, se envía el objeto, no el valor
$estado = $grupo?->estado_actividad_asignada;
// ...
'estado' => $estado,
// El frontend compara: estado === 'ACTIVA' → nunca coincide con un Enum object
```

```php
// ✅ Correcto — extraer el valor escalar
$estado = $grupo?->estado_actividad_asignada?->value;
```

**Verificar:** si `EstadoActividadAsignada` ya es un backed enum con `value`, confirmar que el cast en el modelo lo retorna como tal.

---

### BC-04 — `AgendaController`: validación de fecha ignora la holgura (`dias_adicionales`)
**Archivo:** `AgendaController.php:97-102`

```php
// ❌ Actual — solo compara con fecha_limite, ignora nro_dias_adicionales_para_bloqueo
if ($actividad && now()->isAfter($actividad->fecha_limite->endOfDay())) {
    return back()->withErrors(['error_general' => 'La fecha límite ha vencido...']);
}

// ✅ Correcto — incluir holgura, igual que la lógica del frontend
$limiteReal = $actividad->fecha_limite->copy()
    ->addDays($actividad->nro_dias_adicionales_para_bloqueo ?? 0)
    ->endOfDay();

if ($actividad && now()->isAfter($limiteReal)) {
    return back()->withErrors(['error_general' => 'La fecha límite ha vencido...']);
}
```

**Impacto:** El frontend permite entregas dentro de la holgura, pero el backend las rechaza. Inconsistencia que bloquea entregas válidas.

---

## 2. Código muerto

### DC-01 — `CourseController::show()`: primer `$actividadesData` inmediatamente sobreescrito
**Archivo:** `CourseController.php:143-185`

```php
// ❌ Primer mapping (líneas 143–174) — nunca llega a usarse
$actividadesData = $actividades->map(function (Actividad $actividad) use ($estudiante) {
    $asignado = IntegranteGrupo::where(...)->first(); // N queries extra
    $grupo = $asignado?->actividadAsignadaGrupo;
    return [ 'nota_grupal' => ..., 'estado' => ..., ... ]; // datos ricos
});

// ❌ Segundo mapping (líneas 176–185) — sobreescribe $actividadesData inmediatamente
$actividadesData = $actividades->map(fn (Actividad $actividad) => [
    'es_sumativa' => ..., 'con_entrega' => ..., // datos simples
]);
```

El primer mapping genera queries innecesarias y es dead code. Eliminar el bloque de líneas 143–174 completo.

---

### DC-02 — `ProgramaController`: ~165 líneas de métodos nunca llamados
**Archivo:** `ProgramaController.php:178-342`

```php
// ❌ Ambos métodos son private y no se invocan en ninguna parte del controlador
private function parseSecciones(array $data): array { ... }   // línea 178
private function extraeContenidos(array $contenido, string $seccionId): array { ... } // línea 224
```

`parseSecciones` nunca es llamado. `extraeContenidos` solo es llamado desde `parseSecciones`. Ambos son código muerto. Eliminar las dos funciones.

---

### DC-03 — `ProgramaController`: `$userPermissions` calculado, nunca enviado a la vista
**Archivo:** `ProgramaController.php:50-58`

```php
// ❌ Se computa la colección pero no aparece en el return de Inertia::render
$userPermissions = collect($userPermissionsData)->map(function ($perm) {
    return [...];
})->values()->toArray();
// ...
return Inertia::render('student/Courses/Syllabus', [
    'programa' => $programaData,
    'curso'    => $cursoData,
    // ← $userPermissions no está aquí
]);
```

Eliminar el bloque completo de `$userPermissions` (líneas 50-58) o enviarlo si la vista lo necesita.

---

### DC-04 — `AgendaController::store()`: verificación redundante que nunca puede ser verdadera
**Archivo:** `AgendaController.php:54`

```php
$integrante = IntegranteGrupo::where('id_estudiante', $estudiante->id_estudiante)
    ->where('id_actividad_asignada_grupo', $actividadAsignadaGrupo->id_actividad_asignada_grupo)
    ->firstOrFail(); // ← ya filtra por id_actividad_asignada_grupo

// ❌ Esta condición NUNCA puede ser true porque firstOrFail ya lo garantizó
if ($integrante->id_actividad_asignada_grupo !== $actividadAsignadaGrupo->id_actividad_asignada_grupo) {
    abort(403, 'Estudiante no pertenece a este grupo');
}
```

Eliminar las líneas 54-56.

---

### DC-05 — `CourseController`: imports no usados (`Rol`, `UsuarioRolAsignacion`)
**Archivo:** `CourseController.php:17-18`

```php
use App\Models\Usuario\Rol;                  // no aparece en ningún método
use App\Models\Usuario\UsuarioRolAsignacion; // no aparece en ningún método
```

Eliminar ambos imports.

---

### DC-06 — `DashboardController`: código comentado y comentario engañoso sobre progreso
**Archivo:** `DashboardController.php:59-72`

```php
// ❌ Comentario de TODO con exclamaciones, seguido de código comentado
// OBTENER EL PROGRESO REAL DEL CURSO BASADO EN LAS ACTIVIDADES COMPLETADAS!!!!!
// NO SE USA: $progreso = $inscripcion->progreso ?? 50; 
// ...
//'progreso' => $progreso,  // campo comentado en el return
```

Limpiar: si el progreso no está implementado, simplemente no incluirlo ni mencionar su ausencia con comentarios de debug.

---

## 3. Calidad e inconsistencias

### CQ-01 — `CourseController`: imagen base64 de 3 KB hardcodeada inline
**Archivo:** `CourseController.php:77`

```php
// ❌ Una cadena base64 de ~2.5 KB inline en el método, dificulta lectura y mantenimiento
$default_img = "data:image/png;base64,iVBORw0KGgo..."; // 2500+ caracteres
```

Mover a una constante de clase, un archivo de config, o referenciar un asset público:
```php
// ✅ Opción recomendada
private const DEFAULT_COURSE_IMAGE = '/images/default-course.png';
```

---

### CQ-02 — `ActivityController`: `rubrica_evaluada` y `rubrica` envían el mismo objeto
**Archivo:** `ActivityController.php:177-178`

```php
// ❌ Duplicado — ambas claves apuntan a la misma variable
'rubrica_evaluada' => $rubrica,
'rubrica'          => $rubrica,
```

El frontend (`Index.svelte`) solo usa la prop `rubrica`. Eliminar `rubrica_evaluada` del response o unificar nombres.

---

### CQ-03 — `ActivityController`: `es_de_docente` puede marcar a compañeros de grupo como docente
**Archivo:** `ActivityController.php:116`

```php
// ❌ Lógica: "es de docente" = "no es el usuario actual"
// Esto marca mensajes de otros estudiantes del grupo como si fueran del docente
'es_de_docente' => $agenda->id_usuario_emisor !== $user->id_usuario,
```

```php
// ✅ Correcto — verificar si el emisor es docente del curso
'es_de_docente' => $agenda->usuario?->docente !== null,
```

---

### CQ-04 — `AgendaController::mapearTipoMensaje()`: permite `'Entrega de Avance'` en el endpoint de mensajes de texto
**Archivo:** `AgendaController.php:45,222`

```php
// store() — endpoint de texto puro
$validated = $request->validate([
    'tipo' => 'required|string|in:Consulta,Entrega de Avance,Duda sobre Rúbrica,Otro',
    //                                    ^^^ permitido aquí
]);
// Si el usuario envía 'Entrega de Avance', mapea a TipoMensaje::ENTREGA_DE_ARCHIVO
// creando un registro de "entrega de archivo" sin ningún archivo adjunto.
```

```php
// ✅ Correcto — excluir 'Entrega de Avance' del endpoint de texto
'tipo' => 'required|string|in:Consulta,Duda sobre Rúbrica,Otro',
```

---

### CQ-05 — `CourseController`: docblock incompleto en cabecera del archivo
**Archivo:** `CourseController.php:1-9`

```php
/**
 * Controlador de Curso para
 * 
 * 
 * 
 * 
 * 
 */
```

Completar o eliminar. Un docblock vacío no aporta nada.

---

## 4. Mapa de archivos afectados

| Archivo | Hallazgos |
|---|---|
| `ActivityController.php` | BC-01, BC-02, BC-03, CQ-02, CQ-03 |
| `AgendaController.php` | BC-04, DC-04, CQ-04 |
| `CourseController.php` | DC-01, DC-05, CQ-01, CQ-05 |
| `ProgramaController.php` | DC-02, DC-03 |
| `DashboardController.php` | DC-06 |

---

## 5. Prioridad sugerida de corrección

| Prioridad | IDs | Motivo |
|---|---|---|
| Alta | BC-01, BC-02, BC-04 | Crashes o funcionalidad rota visible |
| Media | BC-03, CQ-03, CQ-04 | Lógica incorrecta, silenciosa pero incorrecta |
| Baja | DC-01–DC-06, CQ-01, CQ-02, CQ-05 | Mantenibilidad y limpieza |
