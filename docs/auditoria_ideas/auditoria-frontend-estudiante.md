# Auditoría Frontend — Módulo Estudiante
> Fecha: 2026-06-26 | Rama: `admin` | Referencia de estilo: módulo Docente

---

## Resumen ejecutivo

| Categoría | Hallazgos |
|---|---|
| Bugs lógicos | 3 |
| Imports/código muerto | 9 |
| Inconsistencias de estilo vs docente | 7 |
| Datos de ejemplo en producción | 1 |
| Total | 20 |

---

## 1. Bugs lógicos

### B-01 — `exedioFechaLimite` deriva una función, no un valor
**Archivo:** `resources/js/pages/student/Activities/Index.svelte:84`

```ts
// ❌ Actual — $derived recibe una arrow que retorna el resultado
const exedioFechaLimite = $derived(() => {
  return new Date(fecha_limite) < new Date();
});
// En template se llama: exedioFechaLimite()
// Esto funciona accidentalmente: el getter devuelve la función, luego la invoca.
// Es un anti-patrón y puede romper con futuras versiones de Svelte 5.

// ✅ Correcto
const exedioFechaLimite = $derived.by(() => {
  return new Date(fecha_limite) < new Date();
});
// En template: {#if exedioFechaLimite}  (sin paréntesis)
```

---

### B-02 — Lógica invertida en `puedeSubirArchivo`
**Archivo:** `resources/js/pages/student/Activities/Index.svelte:99-101`

```ts
// ❌ Actual — retorna true cuando la holgura YA SE EXCEDIÓ (no se puede subir)
const puedeSubirArchivo = $derived.by(() => {
  return (estado === 'ACTIVA' && excedioHolgura) || (puedeApelar);
});

// ✅ Correcto — retorna true si la actividad está activa Y la holgura NO se excedió
const puedeSubirArchivo = $derived.by(() => {
  return (estado === 'ACTIVA' && !excedioHolgura) || puedeApelar;
});
```

**Impacto:** El botón "Subir entrega" sólo aparece después de que venció el plazo + holgura, bloqueando entregas válidas.

---

### B-03 — Chip de `estado` sin color de fondo
**Archivo:** `resources/js/pages/student/Activities/Index.svelte:244-248`

```svelte
<!-- ❌ Actual — sin clase de color, chip invisible/blanco -->
<div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm">
  {estado?.toUpperCase()}
</div>

<!-- ✅ Correcto — asignar color según estado -->
<div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold shadow-sm
  {estado === 'ACTIVA' ? 'bg-emerald-100 text-emerald-800' :
   estado === 'CERRADA' ? 'bg-slate-100 text-slate-600' :
   'bg-amber-100 text-amber-800'}">
  {estado?.toUpperCase()}
</div>
```

---

## 2. Imports y código muerto

### D-01 — `Sparkles` importado, no usado
**Archivo:** `resources/js/pages/student/Dashboard.svelte:8`
```ts
import { Sparkles, Calendar } from 'lucide-svelte'; // Sparkles → eliminar
```

---

### D-02 — Seis imports de lucide no usados en `Show.svelte`
**Archivo:** `resources/js/pages/student/Courses/Show.svelte:4`
```ts
import { ArrowLeft, PlayCircle, FileText, Bookmark, Share2, ScrollText } from 'lucide-svelte';
// Ninguno aparece en el template → eliminar todos
```

---

### D-03 — `CourseSidebar` y `ResourceCard` importados, no usados
**Archivo:** `resources/js/pages/student/Courses/Show.svelte:5-6`
```ts
import CourseSidebar from '@/components/student/CourseSidebar.svelte';  // no usado
import ResourceCard from '@/components/student/ResourceCard.svelte';    // no usado
```

---

### D-04 — `StudentLayout` y `Link` importados en `Syllabus.svelte` (componente embebido, no página)
**Archivo:** `resources/js/pages/student/Courses/Syllabus.svelte:2,4`
```ts
import StudentLayout from '@/layouts/StudentLayout.svelte'; // no usado (Syllabus se embebe en Show)
import { Link } from '@inertiajs/svelte';                   // no usado
```

---

### D-05 — `ArrowLeft` y `Download` importados, no usados en `Syllabus.svelte`
**Archivo:** `resources/js/pages/student/Courses/Syllabus.svelte:6`
```ts
import { ArrowLeft, BookOpen, Download, User, Award, Clock, ExternalLink } from 'lucide-svelte';
// ArrowLeft y Download → eliminar
```

---

### D-06 — Estado `showActividades` siempre `true`, nunca se cambia
**Archivo:** `resources/js/pages/student/Courses/Show.svelte:98`
```ts
let showActividades = $state(true); // nunca se cambia a false
// El bloque {#if showActividades} siempre renderiza
// → eliminar el state y renderizar la sección directamente
```

---

### D-07 — Bloque `bibliografias` inaccesible desde la UI
**Archivo:** `resources/js/pages/student/Courses/Show.svelte:140-142`

`activeView` se inicializa como `'principal'` y no hay ningún elemento en la UI que lo cambie a `'bibliografias'`. El bloque `{:else if activeView === 'bibliografias'}` nunca se ejecuta. `CourseSidebar` (que tenía el botón "Ver Bibliografía") fue removido del template pero no del import.

**Opciones:**
- Reconectar `CourseSidebar` al template con `onBibliografiaClick` para que active la vista.
- O eliminar el bloque `bibliografias` y el state `activeView` si la funcionalidad fue descartada.

---

## 3. Inconsistencias de estilo vs módulo Docente

El módulo Docente usa consistentemente la paleta **`indigo-*` / `slate-*`** y tipografía `font-extrabold tracking-tight`. El módulo Estudiante mezcla `blue-*`, `cyan-*`, `gray-*` y `purple-*`.

### E-01 — `bg-linear-to` es clase inválida de Tailwind
**Archivo:** `resources/js/pages/student/Dashboard.svelte:139`
```svelte
<!-- ❌ -->
<div class="relative overflow-hidden rounded-3xl bg-linear-to from-purple-600/5 ...">
<!-- ✅ -->
<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-600/5 ...">
```

---

### E-02 — Botones de semestre usan `bg-cyan-600` (vs `indigo-600` en docente)
**Archivo:** `resources/js/pages/student/Dashboard.svelte:165,173`
```svelte
<!-- ❌ -->
class="... bg-cyan-600 text-white ..."
<!-- ✅ -->
class="... bg-indigo-600 text-white ..."
```

---

### E-03 — Ícono `Calendar` usa `text-purple-600` (vs `indigo-600` en docente)
**Archivo:** `resources/js/pages/student/Dashboard.svelte:143`
```svelte
<!-- ❌ -->
<Calendar class="w-4 h-4 text-purple-600" />
<!-- ✅ -->
<Calendar class="w-4 h-4 text-indigo-600" />
```

---

### E-04 — Header del Dashboard no sigue la estructura del Docente
**Archivo:** `resources/js/pages/student/Dashboard.svelte:120-127`

Docente tiene: badge de periodo → `h1 font-extrabold text-slate-900 tracking-tight` → `p.text-slate-500`.
Estudiante tiene: `h1 text-4xl font-bold` sin tracking, `p.text-gray-500` sin `slate`.

```svelte
<!-- ✅ Estructura a replicar (como en DocenteDashboard) -->
<header class="flex items-center justify-between gap-4 flex-wrap">
  <div class="flex flex-col gap-1">
    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-600
                 bg-indigo-50 border border-indigo-200 rounded-full px-3 py-0.5 w-fit">
      Semestre {semestre} · {anoAcademico}
    </span>
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
      Portal Estudiante
    </h1>
    <p class="text-sm text-slate-500">
      Bienvenido, <strong class="text-slate-700 font-semibold">{nameParts.nombre}</strong>
    </p>
  </div>
</header>
```

---

### E-05 — Filtros de actividades usan `bg-blue-500` (vs `indigo-600` en docente)
**Archivo:** `resources/js/pages/student/Activities/ActividadesView.svelte:50-73`
```svelte
<!-- ❌ -->
class="... bg-blue-500 text-white border-blue-500 ..."
<!-- ✅ -->
class="... bg-indigo-600 text-white border-indigo-600 ..."
```

---

### E-06 — `ProfileCard` tiene párrafo vacío (whitespace)
**Archivo:** `resources/js/components/student/ProfileCard.svelte:26-29`
```svelte
<!-- ❌ -->
<p class="text-sm text-gray-600">
  
</p>
<!-- ✅ Eliminar el párrafo vacío -->
```

---

### E-07 — `class="pg"` sin definición CSS
**Archivo:** `resources/js/pages/student/Courses/Show.svelte:104`
```svelte
<!-- ❌ La clase no existe en app.css ni en Tailwind -->
<div class="pg">
<!-- ✅ Reemplazar con clases funcionales -->
<div class="flex flex-col gap-4 px-2 py-4">
```

---

## 4. Datos de ejemplo en producción

### P-01 — `actividadesEjemplo` hardcodeado en `Show.svelte`
**Archivo:** `resources/js/pages/student/Courses/Show.svelte:46-67`

```ts
// Dos actividades de ejemplo con fechas calculadas en runtime.
// Se usan como fallback cuando actividades.length === 0, pero viajan en el bundle de producción.
const actividadesEjemplo: Actividad[] = [ ... ];
const actividadesBase = $derived(actividades.length > 0 ? actividades : actividadesEjemplo);
```

**Recomendación:** Eliminar y mostrar un estado vacío ("No hay actividades en este curso") cuando `actividades.length === 0`.

---

## 5. Mapa de archivos afectados

| Archivo | Hallazgos |
|---|---|
| `pages/student/Dashboard.svelte` | D-01, E-01, E-02, E-03, E-04 |
| `pages/student/Activities/Index.svelte` | B-01, B-02, B-03 |
| `pages/student/Activities/ActividadesView.svelte` | E-05 |
| `pages/student/Courses/Show.svelte` | D-02, D-03, D-06, D-07, E-07, P-01 |
| `pages/student/Courses/Syllabus.svelte` | D-04, D-05 |
| `components/student/ProfileCard.svelte` | E-06 |

---

## 6. Prioridad sugerida de corrección

| Prioridad | IDs | Motivo |
|---|---|---|
| Alta | B-02, B-03 | Afectan funcionalidad visible para el estudiante |
| Media | B-01, E-01, E-04—E-07 | Correctitud de código y coherencia visual |
| Baja | D-01—D-07, P-01 | Bundle limpio, mantenibilidad |
