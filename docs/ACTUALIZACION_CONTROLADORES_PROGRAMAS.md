# Resumen de Actualización - Controladores de Programas

## Cambios Realizados (25 de Febrero 2026)

### 1. ProgramaController (`app/Http/Controllers/Admin/ProgramaController.php`)

#### Métodos Actualizados

**`store(Request $request, Curso $curso)`** ✅
- Parámetro obligatorio: `tipo_syllabus` = **BASICO** | **COMPLETO**
- **BASICO**: Valida 5 secciones requeridas (I, II, VI, VII-actividades, VIII)
  - Estado inicial: **BASICO_COMPLETO**
- **COMPLETO**: Valida 9 secciones completas (I-IX)
  - Estado inicial: **COMPLETO**
- Validaciones específicas:
  - **Sección I**: Identificación (7 campos obligatorios)
  - **Sección II**: Descripción/Propósito (texto mín. 100 caracteres)
  - **Sección III**: Estándares (texto mín. 100 caracteres) [COMPLETO]
  - **Sección IV**: Competencias (mín. 1 específica + 1 genérica) [COMPLETO]
  - **Sección V**: Evaluación diagnóstica (mín. 1 item) [COMPLETO]
  - **Sección VI**: Unidades de Aprendizaje (mín. 1 unidad)
  - **Sección VII**: Actividades de Aprendizaje (instancias de tabla `actividades`)
  - **Sección VIII**: Bibliografía/Recursos (mín. 2 recursos)
  - **Sección IX**: Aspectos administrativos [COMPLETO]
- Permisos: **Admin** ✅ | **Docente** ✅ | **Ayudante** ❌
- Registra: `creado_por`, `tipo_syllabus`, `estado`
- Response JSON: `id_programa`, `estado`, `tipo_syllabus`, `creado_por`, `fecha_creacion`

**`updateSeccion(Request $request, Programa $programa, string $seccionId)`** ✨ NUEVO
- Actualiza **secciones individuales** de programas existentes
- Parámetro `$seccionId`: I, II, III, IV, V, VI, VII, VIII, IX
- Estados permitidos para edición: **BASICO_COMPLETO** | **COMPLETO**
- Permisos: 
  - **Admin** ✅ (cualquier programa)
  - **Docente** ✅ (solo si es creador: `creado_por == auth()->id()`)
  - **Ayudante** ✅ (solo si fue asignado como colaborador)
- **Lógica de conversión automática**:
  - Si estado es **BASICO_COMPLETO** y se agrega sección III, IV, V, o IX
  - Cambia automáticamente a **COMPLETO** (convierte versión)
- Validación específica por sección agrega antes de actualizar
- Registra: `ultima_modificacion`, usuario que editó, sección modificada
- Response: `estado` actualizado (puede cambiar a COMPLETO), `secciones` modificadas

**`approve(Request $request, Curso $curso)`** ✅ MEJORADO
- Valida completitud según `tipo_syllabus`:
  - **BASICO_COMPLETO**: Verifica 5 secciones requeridas (I, II, VI, VII, VIII) ✅
  - **COMPLETO**: Verifica 9 secciones completas ✅
- Cambio de estado: **BASICO_COMPLETO/COMPLETO** → **APROBADO**
- **Permisos: SOLO ADMIN** ❌ (Docente y Ayudante NO pueden)
  - Valida `Auth::user()->role == 'admin'` o sale error 403
- Registra: `aprobado_por` (admin_id), `fecha_aprobacion`, log de auditoría
- Validaciones antes de aprobar:
  - ¿Está en estado BASICO_COMPLETO o COMPLETO?
  - ¿Tiene todas las secciones requeridas?
  - ¿Tiene contenido en todas ellas (no null/vacío)?
- Response: Redirige a programa con flash: "Programa aprobado correctamente"

**`index(Request $request)`** ✅ MEJORADO
- ListaPrograma s del curso según permisos:
  - **Admin**: Ve todos los estados (BASICO_COMPLETO, COMPLETO, APROBADO, PUBLICADO)
  - **Docente**: Ve solo los que creó o están en su curso
  - **Ayudante**: Ve solo los del curso asignado
- Campos devueltos:
  - `id_programa`, `id_curso`, `tipo_syllabus` (BASICO/COMPLETO)
  - `estado`, `creado_por`, `aprobado_por`, `publicado_por`
  - `fecha_creacion`, `fecha_aprobacion`, `fecha_publicacion`
  - `completud` (0-100%, según tipo) - porcentaje de secciones con contenido
- Estadísticas por estado y tipo (para admin)
- Filtros opcionales: estado, tipo_syllabus, creado_por
- Orden por defecto: `fecha_creacion DESC`

**`reject(Request $request, Curso $curso)`** ✨ NUEVO
- **Permiso: SOLO ADMIN** (rechaza programas aprobados)
- Cambio de estado: **APROBADO** → **BASICO_COMPLETO/COMPLETO** (según tipo_syllabus)
- Parámetro opcional: `razon_rechazo` (texto para notificación)
- Valida: `Auth::user()->role == 'admin'` o error 403
- Permite nuevamente la edición (Admin/Docente/Ayudante pueden continuar)
- Registra: `rechazado_por` (admin_id), `fecha_rechazo`, `razon_rechazo`, log de auditoría
- Response: Redirige con flash: "Programa devuelto a {estado} para revisión"

#### Métodos Nuevos Privados

**`getValidationRulesForSeccion(string $seccionId): array`**
- Retorna reglas de validación específicas por sección
- Centraliza todas las validaciones en un solo lugar

**`isProgramaComplete(Programa $programa): bool`**
- Verifica que existan las 9 secciones con contenido

**`calculateCompleteness(Programa $programa): int`**
- Calcula porcentaje de completitud (usado en index)

---

### 2. ProgramaService (`app/Services/ProgramaService.php`)

#### Métodos Actualizados

**`updateSeccion(Programa $programa, string $seccionId, array $contenido)`** ✨ REDISEÑADO
- Ahora recibe `$seccionId` (string: I, II, III, etc.) en lugar de `$orden` (int)
- Busca sección por campo `id` en JSONB
- Actualiza campo `contenido` con nueva estructura
- Agrega `ultima_modificacion` timestamp
- Transaccional (DB::transaction)

**`updateSeccionByOrden()` (nuevo alias)**
- Mantiene compatibilidad con código anterior que usa orden numérico
- Internamente usa el nuevo método

---

## Estructura JSONB Esperada

```json
{
  "metadata": {
    "asignatura": {...},
    "curso": {...},
    "horas": {...}
  },
  "secciones": [
    {
      "id": "I",
      "nombre": "Identificación de la Asignatura",
      "orden": 1,
      "tipo": "identificacion",
      "contenido": { /* campos específicos */ },
      "ultima_modificacion": "2026-02-25T14:30:00Z"
    },
    {
      "id": "II",
      "nombre": "Presentación, Descripción y Propósito Formativo",
      "orden": 2,
      "tipo": "texto",
      "contenido": { "texto": "..." }
    },
    // ... V más secciones (III-IX)
  ],
  "timestamp": "2026-02-25T14:30:00Z"
}
```

---

## Estados del Programa

| Estado              | Descripción                                       | Transiciones Permitidas                            | Editable | Visible Alumnos  |
| ------------------- | ------------------------------------------------- | -------------------------------------------------- | :------: | :--------------: |
| **BASICO_COMPLETO** | Básico con 5 secciones (I, II, VI, VII-act, VIII) | → COMPLETO (al agregar III/IV/V/IX) / → APROBADO   |    ✅     | ✅ (simplificado) |
| **COMPLETO**        | Versión completa (9 secciones) sin aprobar        | → APROBADO                                         |    ✅     |        ❌         |
| **APROBADO**        | Aprobado por admin, listo para publicación        | → PUBLICADO / → BASICO_COMPLETO/COMPLETO (rechazo) |    ❌     |        ❌         |
| **PUBLICADO**       | Visible para todos (read-only)                    | (sin vuelta atrás)                                 |    ❌     |        ✅         |

---

## Flujo de Datos

### 1. Crear Nuevo Programa
```
POST /admin/cursos/{curso}/programas
Headers: Authorization, Content-Type: application/json
Payload:
{
  "secciones": {
    "I": { "contenido": { "nombre_asignatura": "...", ... } },
    "II": { "contenido": { "texto": "..." } },
    ...
    "IX": { "contenido": { ... } }
  }
}

Response:
{
  "message": "Programa generado correctamente.",
  "programa": {
    "id_programa": 1,
    "version_programa": 1,
    "estado": "ABIERTO",
    "creado_por": "Juan Pérez",
    "fecha_creacion": "2026-02-25T14:30:00Z"
  }
}
```

### 2. Actualizar Sección Individual
```
PATCH /admin/programas/{programa}/secciones/{seccionId}
Params: seccionId = I, II, III, ... IX

Payload:
{
  "contenido": {
    // Contenido específico de la sección
  }
}

Response:
{
  "message": "Sección I actualizada correctamente.",
  "programa": {
    "id_programa": 1,
    "estado": "ABIERTO"
  }
}
```

### 3. Aprobar Programa
```
POST /admin/cursos/{curso}/programas/{programa}/approve

Response:
Redirect + Flash: "Programa aprobado correctamente"
```

---

## Validaciones Implementadas

### Por Sección

| Sección    | Validaciones Clave                                   |
| ---------- | ---------------------------------------------------- |
| **I**      | Identificación con 7 campos obligatorios             |
| **II-III** | Texto mínimo 100 caracteres                          |
| **IV**     | Mínimo 1 competencia específica + 1 genérica         |
| **V**      | Mínimo 1 item de evaluación diagnóstica              |
| **VI**     | Mínimo 1 unidad avec contenidos                      |
| **VII**    | 3 subsecciones (Resultados, Metodología, Evaluación) |
| **VIII**   | Mínimo 2 recursos                                    |
| **IX**     | Tabla de componentes + ponderación optativa          |

---

## Logs Registrados

Todas las operaciones ahora registran:
- Creación de programa: id_programa, id_curso, creado_por, version
- Actualización de sección: id_programa, seccion ID, usuario
- Aprobación: id_programa, aprobado_por
- Rechazo: id_programa, por (usuario)

Ejemplo log:
```
[2026-02-25 14:30:15] admin.INFO: Programa creado/actualizado
{
  "id_programa": 1,
  "id_curso": 5,
  "creado_por": 3,
  "version": 1
}
```

---

## Cambios en el Modelo

No hay cambios en el modelo `Programa`. Se mantiene compatibilidad total:
- Campo `data_syllabus` JSONB: ✅
- Campo `creado_por`: ✅ (ya existía)
- Campo `version_programa`: ✅ (ya existía)
- Campo `es_actual`: ✅ (ya existía)
- Relación `autor()`: ✅ (ya existía)

---

## Próximos Pasos Recomendados

1. **Crear vistas Inertia** para editar secciones (formularios por sección)
2. **Agregar validador personalizado** para estructura completa
3. **Implementar sistema de borrador automático** (auto-save cada 30 segundos)
4. **Agregar historial de cambios** (audit log detallado)
5. **Crear builder frontend** para generar JSONB desde formularios

---

## Compatibilidad

- ✅ Mantiene compatibilidad con `ProgramaService::generateProgramaWithSyllabus()`
- ✅ Mantiene compatibilidad con controladores de Estudiante/Ayudante/Docente (read-only)
- ✅ Mantiene compatibilidad con SyllabusStructure (si existe)
- ⚠️ Requiere adaptación de vistas existentes para nueva estructura JSON

---

## Testing Sugerido

```php
// Test: Crear programa con 9 secciones
$response = $this->post('/admin/cursos/1/programas', [
    'secciones' => [
        'I' => [...], 'II' => [...], ..., 'IX' => [...]
    ]
]);
$response->assertStatus(200);

// Test: Actualizar sección individual
$response = $this->patch(
    '/admin/programas/1/secciones/IV',
    ['contenido' => [...]]
);
$response->assertStatus(200);

// Test: Validar completitud
$programa = Programa::find(1);
$this->assertTrue($programa->isComplete());
```
