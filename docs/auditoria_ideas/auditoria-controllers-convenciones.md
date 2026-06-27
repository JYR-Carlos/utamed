# Auditoría Backend — Convenciones, documentación y estructura de Controllers
> Fecha: 2026-06-26 | Rama: `admin` | Directorio: `app/Http/Controllers/` (51 clases, ~13.800 líneas)
>
> Alcance: **documentación, estructura, arquitectura y convenciones Laravel**. No cubre bugs
> de lógica (para Student, esos están en `auditoria-backend-estudiante.md`).

---

## Resumen ejecutivo

| Categoría | Hallazgos |
|---|---|
| Documentación (docblocks) | 4 |
| Type hints / firmas | 3 |
| Estructura y arquitectura | 4 |
| Convenciones de nombres | 3 |
| Consistencia de patrones | 5 |
| **Total** | **19** |

**Diagnóstico de una línea:** el código está dividido en **dos generaciones**. Los controllers
tocados recientemente (`Docente/AsistenciaController`, `Docente/MensajesController`, los *traits*
`ResolvesJefaturaCarrera` / `ContaPendientesMensajes`, `Admin/UsuarioController`,
`Admin/CourseTeamController`) tienen documentación ejemplar y deberían ser **el modelo a seguir**.
El resto (`Student/*`, `Ayudante/*`, `Administrativo/ProgramaController`, `Docente/DocenteUnidadController`)
arrastra docblocks vacíos, sin type hints y con duplicación. El objetivo de esta auditoría es
**nivelar todo hacia arriba** con una convención única.

---

## 0. Convención objetivo (lo que queremos que sea "lo normal")

Antes de los hallazgos, este es el estándar propuesto. Sirve como checklist al tocar cualquier controller.

```php
<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Curso\Curso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Una frase: qué recurso gestiona y desde qué perspectiva (rol).
 *
 * Reglas de acceso / modelo de datos no obvios van aquí (ver AsistenciaController
 * como referencia de oro).
 */
class EjemploController extends Controller
{
    /**
     * Qué hace el endpoint, en una frase.
     *
     * GET docente/cursos/{curso}/ejemplo   ← ruta + verbo
     */
    public function index(Curso $curso): Response
    {
        $this->authorize('view', $curso);
        // ...
    }
}
```

Reglas:
1. **Docblock de clase obligatorio** (1 frase mínimo; reglas de negocio si las hay).
2. **Type hint de retorno en TODA acción pública** (`Response`, `RedirectResponse`, `JsonResponse`).
3. **Imports cortos** — nunca FQCN inline (`\Illuminate\Http\Request $request`).
4. **Route-model binding** en vez de `$id` + `findOrFail`.
5. **Docblocks en español** (el dominio es español); sin docblocks vacíos ni de Breeze sin traducir.
6. Lógica reutilizada → *trait* (`app/Http/Controllers/Concerns`) o *Action/Service*, no copy-paste.

---

## 1. Documentación (docblocks)

### DOC-01 — 13 de 50 controllers no tienen docblock de clase
**Archivos:** `Student/CourseController.php:17`, `Student/ProgramaController.php:12`,
`Student/AgendaController.php:27`, `Ayudante/CourseController.php:12`,
`Ayudante/DashboardController.php:12`, `Ayudante/ProgramaController.php:20`,
`Administrativo/ProgramaController.php:16`, `Docente/JefeCarreraController.php:16`,
`Docente/DocenteUnidadController.php:12`, `Settings/ProfileController.php:14`,
`Settings/PasswordController.php:14`, `Settings/TwoFactorAuthenticationController.php:13`.

La clase entra directo tras los `use`, sin una sola línea que diga qué gestiona. Comparar con
el modelo de oro `Docente/AsistenciaController.php:14-29`, que documenta recurso, modelo de datos
y reglas de acceso. **Acción:** añadir docblock de 1–3 frases a cada clase.

---

### DOC-02 — Docblocks vacíos / de plantilla que no aportan nada
**Archivo:** `Student/CourseController.php:1-9` (ya señalado como CQ-05 en la auditoría de Student)

```php
/**
 * Controlador de Curso para
 *
 *
 *
 */
```

Un docblock vacío es peor que ninguno: simula documentación. **Acción:** completarlo o borrarlo.

---

### DOC-03 — Docblocks en inglés sin traducir (heredados de Laravel Breeze)
**Archivos:** `Settings/ProfileController.php:16-46` ("Show the user's profile settings page."),
todo `Auth/*`.

El dominio, los nombres de variables y el resto de docblocks están en español. Los de Breeze
quedaron en inglés. No es grave en `Auth/*` (código de andamiaje estándar), pero conviene
**decidir un idioma y unificar** — recomendación: español para todo lo propio, dejar `Auth/*`
como está por ser scaffolding intacto.

---

### DOC-04 — Comentarios de debug y código comentado en producción
**Archivos:** `Student/DashboardController.php:59-72` (ya señalado como DC-06),
y en general la densidad de `//` es alta en los controllers grandes
(`DocenteActivityController` 93, `UsuarioController` 82, `CourseTeamController` 55).

No todo comentario es malo, pero conviene distinguir documentación de **ruido de debug**
(`// OBTENER EL PROGRESO REAL ...!!!!!`, código comentado, TODOs sin ticket). **Acción:**
barrido para borrar código muerto comentado y exclamaciones de debug.

---

## 2. Type hints y firmas

### TH-01 — Las acciones públicas casi nunca declaran tipo de retorno
**Evidencia:** sólo ~97 firmas con `: Tipo` en todo el directorio, y la mayoría son helpers
privados (`: array`, `: void`, `: string`). Acciones HTTP típicas:

```php
// Admin/UsuarioController.php:620 — el docblock SÍ documenta el retorno...
/** @return \Illuminate\Http\RedirectResponse  Redirección con mensaje de resultado */
public function update(Request $request, $id)        // ← ...pero la firma no lo declara
```

El tipo vive sólo en el docblock, no lo aplica PHP. **Acción:** añadir `: RedirectResponse` /
`: Response` / `: JsonResponse` a las firmas. Es trivial y mejora el autocompletado y los errores
en tiempo de compilación.

---

### TH-02 — FQCN inline en firmas y cuerpos en vez de `use`
**Archivos:**
- `Ayudante/ProgramaController.php:103` — `update(Curso $curso, \Illuminate\Http\Request $request)`
- `Admin/AssignmentWizardController.php:178` — `\Illuminate\Support\Facades\DB::table(...)`
- `DocenteCursoController.php:19` — `use \Illuminate\Support\Facades\Auth;` (backslash de más en el `use`)
- `Admin/UsuarioController.php:270` — `import(Request $request): \Illuminate\Http\RedirectResponse`
- `Docente/JefeCarreraController.php:29,99,192,...` — `instanceof \Illuminate\Http\RedirectResponse` repetido

**Acción:** importar la clase arriba y usar el nombre corto. Consistente con el resto del archivo.

---

### TH-03 — `@param` sin tipo y typos en docblocks
**Archivo:** `Admin/UsuarioController.php:267` — `* * @param` (doble asterisk),
varios `private function formatRut($rut)` (`UsuarioController.php:322`) sin tipo de parámetro
ni retorno. **Acción:** tipar parámetros escalares (`string $rut`) y limpiar el typo.

---

## 3. Estructura y arquitectura

### AR-01 — "God controllers": clases con 5+ responsabilidades
| Archivo | Líneas | Métodos | Responsabilidades mezcladas |
|---|---|---|---|
| `Docente/DocenteActivityController` | 1275 | 30 | Actividades CRUD · calificaciones · rúbricas · grupos · entregas · mensajes/feedback |
| `Admin/UsuarioController` | 1145 | 24 | CRUD usuario · import Excel · permisos · roles · password · activar |
| `Admin/ProgramaController` | 1135 | 18 | Syllabus CRUD · workflow de aprobación · instanciación · reglas de validación |

Violan *Single Responsibility*. Cada bloque cabe en su propio controller (p.ej.
`CalificacionController`, `GrupoActividadController`, `EntregaController`) o en *Actions* /
*FormRequests* (las `getValidationRulesForX` de `ProgramaController.php:798-973` son FormRequests
disfrazados). **Acción:** plan de extracción gradual; empezar por mover las reglas de validación
a `FormRequest`.

---

### AR-02 — Lógica de syllabus duplicada entre 4 ProgramaControllers
**Archivos:** `Admin/ProgramaController`, `Administrativo/ProgramaController`,
`Ayudante/ProgramaController`, `Student/ProgramaController` (+ `JefeCarreraController` toca programa).

`createUnidadesFromSyllabus()` y `createActividadesFromSyllabus()` existen **idénticos** en
`Admin/ProgramaController.php:975,1029` y `Ayudante/ProgramaController.php:247,269`.
`parseSecciones()`/`extraeContenidos()` están duplicados en `Ayudante` y `Student` (allá son
dead code — ver DC-02). **Acción:** extraer a un *trait* `BuildsSyllabus` o un `SyllabusService`
único e inyectarlo. Hoy un cambio de regla obliga a editar 2–4 archivos.

---

### AR-03 — `JefeCarreraController` reimplementa lo que ya hace un trait
**Archivo:** `Docente/JefeCarreraController.php:427` define `jefaturaOrRedirect()`, mientras el
*trait* `Docente/JefeCarrera/ResolvesJefaturaCarrera.php` ya ofrece `jefaturaOrAbort()` /
`carreraIdOrAbort()` usados por los controllers de `Docente/JefeCarrera/*`.

Dos resoluciones paralelas de "jefatura activa", con criterio de fallo distinto (redirect vs 403).
**Acción:** que `JefeCarreraController` use el trait; si necesita redirect en vez de abort,
añadir esa variante al trait. Una sola fuente de verdad.

---

### AR-04 — Los *traits* compartidos viven dentro de `Controllers/` con nombres no estándar
**Archivos:** `Docente/ContaPendientesMensajes.php`, `Docente/JefeCarrera/ResolvesJefaturaCarrera.php`.

Dos detalles:
1. `ContaPendientesMensajes` es un **trait** (bien documentado), pero su nombre es un sustantivo
   truncado ("Conta"). Convención de traits = adjetivo/verbo en 3ª persona: `CuentaMensajesPendientes`
   o `CountsPendingMessages`. `ResolvesJefaturaCarrera` sí sigue la convención.
2. Ambos están bajo `App\Http\Controllers\…`. Laravel suele agrupar traits de controller en
   `app/Http/Controllers/Concerns/`. **Acción (opcional):** mover a `Concerns/` y renombrar
   `ContaPendientesMensajes`.

---

## 4. Convenciones de nombres

### NM-01 — Mezcla de inglés y español en nombres de métodos (mismo controller)
**Archivo:** `Docente/DocenteActivityController.php`

```
deleteGroup · removeStudentFromGroup · addStudentToGroup · getGroupsByActivity   ← inglés
copyGroupsFromActivity · getSubmissionsByActivity · sendFeedback · downloadSubmissionFile
showEvaluacion · storeRubrica · centroCalificaciones · recalcularNotasIndividuales ← español
```

Comparar con `Administrativo/ProgramaController` (todo español: `aprobar`, `rechazar`,
`completarBasico`, `enviarParaRevision`). **Acción:** elegir un idioma para los verbos de acción.
Recomendación: español para acciones de dominio, manteniendo los 7 verbos RESTful estándar en
inglés (`index/show/store/update/destroy/create/edit`).

---

### NM-02 — Nombres de método confusos o mal formados
**Archivo:** `Docente/DocenteActivityController.php:259` — `getBysCursoJson()`

"getBys" parece un typo de "getByCurso". Además rompe REST (`get...Json` mezcla verbo HTTP +
formato en el nombre). **Acción:** renombrar a algo como `actividadesJson(Curso $curso)` o
exponerlo como un `index` que negocie formato por `Accept`.

---

### NM-03 — `Controller` que en realidad es un trait sin sufijo claro
**Archivo:** `Docente/ContaPendientesMensajes.php` — el filename no indica que es un trait y no un
controller (está en `Controllers/` sin sufijo `Controller`, lo cual confunde al navegar la carpeta).
Ver AR-04. **Acción:** renombrar al mover a `Concerns/`.

---

## 5. Consistencia de patrones

### CP-01 — Route-model binding usado a medias dentro del MISMO controller
**Archivo:** `Admin/UsuarioController.php`

```php
public function getUserPermissions(Usuario $usuario)      // ✅ binding (línea 904)
public function syncPermissions(Request $request, Usuario $usuario)  // ✅ binding (línea 969)

public function show($id, Request $request)               // ❌ $id manual (línea 590)
public function update(Request $request, $id)             // ❌ + 11 findOrFail($id) sueltos
public function destroy($id, Request $request)            // ❌ (línea 791)
```

Hay 12 `findOrFail($id)` (líneas 597–870) que el binding resolvería solo. **Acción:** migrar a
`Usuario $usuario` / `Estudiante $estudiante` donde el modelo lo permita.

---

### CP-02 — Orden de parámetros inconsistente: `Request` a veces primero, a veces último
**Archivos:**
- `store(Request $request, Curso $curso)` — `DocenteActivityController.php:133`
- `update(Curso $curso, \Illuminate\Http\Request $request)` — `Ayudante/ProgramaController.php:103`
- `show($id, Request $request)` — `UsuarioController.php:590`

**Convención Laravel:** dependencias inyectadas (`Request`) **primero**, parámetros de ruta después.
**Acción:** ordenar `(Request $request, Curso $curso, ...)` de forma uniforme.

---

### CP-03 — Estrategia de autorización heterogénea
Conviven cuatro estilos:
1. **Policies** `$this->authorize(...)` — `DocenteUnidadController.php:16,36,74,104` (✅ ideal)
2. **Trait** `jefaturaOrAbort()` / `carreraIdOrAbort()` — `Docente/JefeCarrera/*`
3. **Guardia manual + redirect** — `Student/CourseController.php:27` (`if (!$user->estudiante) return redirect(...)`)
4. **`abort(403)` inline** — disperso

No hay que unificar todo a la fuerza, pero **el patrón objetivo son Policies** para recursos
(`Curso`, `Actividad`, `Unidad`). **Acción:** documentar la regla y migrar guardias manuales de
recurso a Policies cuando se toque cada controller.

---

### CP-04 — Tipo de respuesta inconsistente para el mismo tipo de endpoint
Algunos "index/show" devuelven `Inertia::render`, otros `response()->json` sin un criterio visible
(`DocenteUnidadController::index` → JSON; `DocenteActivityController::getBysCursoJson` → JSON;
`Student/CourseController::index` → Inertia). Métodos como `UsuarioController::index` y
`ComponenteController` negocian por `Accept` header (`@return Response|JsonResponse`), lo cual es
correcto pero no está documentado como convención. **Acción:** documentar cuándo un endpoint es
"página Inertia" vs "API JSON" (sufijo `Json`/ruta `api.*`) y aplicarlo.

---

### CP-05 — Falta `declare(strict_types=1)` en todo el directorio
**Evidencia:** 0 archivos lo declaran. No es obligatorio, pero dado que el proyecto ya tipa
parámetros y retornos en los controllers nuevos, activarlo evita coerciones silenciosas
(p.ej. `"5"` → `5`). **Acción (opcional, decisión de equipo):** adoptarlo de forma uniforme o
descartarlo explícitamente; hoy es simplemente "ausente sin decisión".

---

## 6. Inventario por archivo (estado de documentación)

| Controller | Líneas | Docblock clase | Type hints retorno | Observación principal |
|---|---:|:---:|:---:|---|
| `Docente/AsistenciaController` | 337 | ✅ oro | parcial | **Referencia a seguir** |
| `Docente/MensajesController` | 317 | ✅ | parcial | Buen estado |
| `Docente/JefeCarrera/ResolvesJefaturaCarrera` (trait) | 77 | ✅ | ✅ | **Referencia a seguir** |
| `Docente/ContaPendientesMensajes` (trait) | 69 | ✅ | ✅ | Renombrar + mover a Concerns |
| `Admin/UsuarioController` | 1145 | ✅ | docblock-only | God controller (AR-01), binding parcial (CP-01) |
| `Admin/CourseTeamController` | 723 | ✅ | docblock-only | Buena doc, FQCN en @return |
| `Admin/ComponenteController` | 663 | ✅ | docblock-only | Buena doc |
| `Admin/ProgramaController` | 1135 | ✅ | parcial | God controller, duplica syllabus (AR-02) |
| `Docente/DocenteActivityController` | 1275 | ✅ | ❌ | God controller, naming mixto (NM-01/02) |
| `Docente/DocenteCursoController` | 820 | ✅ | docblock-only | `use \…Auth` (TH-02) |
| `Docente/JefeCarreraController` | 811 | ❌ | parcial | Sin docblock, duplica trait (AR-03) |
| `Ayudante/ProgramaController` | 636 | ❌ | parcial | Duplica syllabus + dead code |
| `Administrativo/ProgramaController` | 338 | ❌ | ❌ | Sin docblock |
| `Student/CourseController` | 158 | ❌ vacío | ❌ | DOC-02 |
| `Student/ProgramaController` | 161 | ❌ | ❌ | Dead code (auditoría Student) |
| `Student/AgendaController` | 231 | ❌ | parcial | — |
| `Student/ActivityController` | 182 | ✅ | ❌ | Bugs en auditoría Student |
| `Student/DashboardController` | 93 | ✅ | docblock-only | Debug comments (DOC-04) |
| `Docente/DocenteUnidadController` | 128 | ❌ | ❌ | Limpio pero sin doc ni tipos |
| `Settings/*`, `Auth/*` | 33–84 | mixto | ✅ | Scaffolding Breeze, inglés (DOC-03) |

*(Resto de Admin y Docente/JefeCarrera: docblock de clase presente, type hints sólo en docblock.)*

---

## 7. Prioridad sugerida

| Prioridad | IDs | Motivo |
|---|---|---|
| **Alta** (barato, alto impacto) | DOC-01, DOC-02, TH-01, TH-02 | Legibilidad inmediata; mecánico y sin riesgo de comportamiento |
| **Media** (consistencia) | NM-01, NM-02, CP-01, CP-02, AR-03 | Reduce sorpresas al navegar; algo de refactor |
| **Media-baja** (arquitectura) | AR-02, AR-04 | Elimina duplicación; requiere diseño de trait/service |
| **Baja** (decisión de equipo) | DOC-03, DOC-04, TH-03, NM-03, CP-03, CP-04, CP-05 | Mejora de fondo; conviene acordar la regla primero |
| **Proyecto aparte** | AR-01 | Partir los 3 god controllers es un esfuerzo grande y planificado |

---

## 8. Cómo aplicar (sugerencia de tandas)

1. **Tanda mecánica** (1 PR): DOC-01 + TH-01 + TH-02 en los 13 controllers sin docblock. Sin cambio de comportamiento.
2. **Tanda de limpieza** (1 PR): DOC-02, DOC-04, TH-03 + dead code ya listado en la auditoría de Student.
3. **Tanda de consistencia** (1 PR por rol): CP-01, CP-02, NM-01/02, AR-03.
4. **Tanda de des-duplicación** (1 PR): AR-02 → `SyllabusService`/trait; AR-04 → mover traits a `Concerns/`.
5. **Backlog**: AR-01 (god controllers) como épica separada con su propio plan.
