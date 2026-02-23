# Fix: Cursos no se mostraban a los docentes

## Problema Identificado

Los docentes no veían sus cursos asignados en la vista `/docente/cursos` aunque técnicamente estaban asignados a través de secciones.

### Causa Raíz

La relación docente-curso en el sistema es **indirecta mediante secciones**:
- Docente has many Secciones (`id_docente`)
- Seccion belongs to Curso (`id_curso`)
- Por lo tanto: Docente → Seccion → Curso

**El problema original era:**
1. Las consultas en `DocenteCursoController` y `DashboardController` usando dos queries separadas (menos eficiente)
2. No se estaba cargando la información de **carrera** (plan → carrera)
3. La vista `Cursos.svelte` no mostraba información de carrera aunque era importante

## Cambios Implementados

### 1. **DocenteCursoController::index()** - Optimización de consulta
**Antes:**
```php
$secciones = Seccion::where('id_docente', $user->docente->id_docente)->get();
$cursoIds = $secciones->pluck('id_curso')->unique();
$cursos = Curso::whereIn('id_curso', $cursoIds)
    ->with(['asignacionPlan.asignatura'])
    ->orderBy('fecha_inicio', 'desc')
    ->get();
```

**Después:**
```php
$cursos = Curso::join('curso.seccion', 'curso.curso.id_curso', '=', 'curso.seccion.id_curso')
    ->where('curso.seccion.id_docente', $user->docente->id_docente)
    ->distinct()
    ->select('curso.curso.*')
    ->with(['asignacionPlan.asignatura', 'asignacionPlan.plan.carrera'])
    ->orderBy('curso.curso.fecha_inicio', 'desc')
    ->get();
```

**Ventajas:**
- Una sola consulta en lugar de dos
- JOIN garantiza que solo se obtienen cursos donde el docente tiene secciones
- Carga información de carrera
- Más eficiente a nivel de base de datos

### 2. **Cursos.svelte** - Visualización de carrera
Se agregó el campo de carrera en ambas secciones (Semestre 1 y Semestre 2):
```svelte
<div class="flex items-center text-sm text-slate-600">
    <span class="font-medium mr-2">Carrera:</span>
    <span class="truncate" title={curso.carrera_nombre}>{curso.carrera_nombre}</span>
</div>
```

Y en el mapeo del controlador:
```php
'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
```

### 3. **DashboardController::index()** - Mismo patrón de optimización
Se aplicó la misma mejora que en DocenteCursoController para consistencia.

### 4. **HandleInertiaRequests.php** - Mejora de compartición de datos
Se mejoró la consulta de `docente_courses` que se comparte en todas las vistas:
```php
'docente_courses' => $docente ? \App\Models\Curso\Curso::join('curso.seccion', 'curso.curso.id_curso', '=', 'curso.seccion.id_curso')
    ->where('curso.seccion.id_docente', $docente->id_docente)
    ->distinct()
    ->select('curso.curso.id_curso', 'curso.curso.nombre', 'curso.curso.cod_curso')
    ->with(['asignacionPlan.plan.carrera'])
    ->get()
    ->map(function ($curso) {
        return [
            'id_curso' => $curso->id_curso,
            'nombre' => $curso->nombre,
            'cod_curso' => $curso->cod_curso,
            'carrera_nombre' => $curso->asignacionPlan?->plan?->carrera?->nombre ?? 'N/A',
            'tiene_programa' => \App\Models\Administrativo\Programa::where('id_curso', $curso->id_curso)->exists(),
        ];
    })
    ->values() : [],
```

## Archivos Modificados

1. `app/Http/Controllers/Docente/DocenteCursoController.php` - Optimización de consulta y carga de carrera
2. `resources/js/pages/docente/Cursos.svelte` - Visualización de carrera
3. `app/Http/Controllers/Docente/DashboardController.php` - Optimización de consulta
4. `app/Http/Middleware/HandleInertiaRequests.php` - Mejora de compartición de datos

## Validación

Para validar que los cursos aparecen correctamente:

### Requisitos Previos
1. Asegurar que existan secciones (`curso.seccion`) para los cursos
2. Que esas secciones tengan `id_docente` asignado
3. Que el docente esté autenticado como docente (tenga perfil en tabla `usuario.docente`)

### Test Rápido
```bash
# 1. Usuario debe tener perfil de docente
SELECT * FROM usuario.docente WHERE id_usuario = <USER_ID>;

# 2. Docente debe ser responsable de al menos una sección
SELECT * FROM curso.seccion WHERE id_docente = <DOCENTE_ID>;

# 3. Esa sección debe estar asignada a un curso
SELECT c.* FROM curso.curso c 
JOIN curso.seccion s ON c.id_curso = s.id_curso 
WHERE s.id_docente = <DOCENTE_ID>;
```

## Impacto

✅ **Positivo:**
- Los docentes ahora ven todos sus cursos
- Mejor visualización con información de carrera
- Consultas más eficientes (una query en lugar de dos)
- Consistencia en todas las vistas (Dashboard, Cursos, HandleInertiaRequests)

## Notas Adicionales

- La relación docente-curso es **siempre indirecta** mediante secciones
- Cada sección tiene un `id_docente` que indica el docente responsable
- Los filtros siempre deben usar JOIN con la tabla `seccion`
- La información de carrera se carga desde: `asignacionPlan → plan → carrera`

## Próximos Pasos (Opcional)

1. Considerar crear un accessor en el modelo `Curso` para obtener cursos de un docente:
   ```php
   // En modelo Docente
   public function cursos() {
       return $this->hasManyThrough(Curso::class, Seccion::class, 'id_docente', 'id_curso', 'id_docente', 'id_curso')->distinct();
   }
   ```

2. Crear índices en BD para mejorar performance de JOINs:
   ```sql
   CREATE INDEX idx_seccion_docente ON curso.seccion(id_docente);
   CREATE INDEX idx_seccion_curso ON curso.seccion(id_curso);
   ```
