# Sistema de Inscripción de Estudiantes en Cursos

## Descripción General

Se ha implementado un sistema completo para que los **administradores y docentes** inscriban estudiantes en cursos a través de la tabla `Inscripcion_Curso`. Este sistema proporciona:

- **CRUD completo** (Crear, Leer, Actualizar, Eliminar) de inscripciones
- **Control de acceso basado en roles** (RBAC) con autorización granular
- **Interfaz web intuitiva** con búsqueda y filtros
- **Validación de integridad** de datos
- **Exportación a CSV** de inscripciones

## Componentes Implementados

### 1. **Modelo: InscripcionCurso** 
Ubicación: `app/Models/Curso/InscripcionCurso.php`

Extiende del modelo base `BaseInscripcionCurso` que proporciona:
- Relaciones con `Estudiante` y `Curso`
- Claves primarias compuestas (`id_curso`, `id_estudiante`)
- Campos editables:
  - `cod_inscripcion_uta`: Código de inscripción del sistema UTA
  - `fecha_inscripcion`: Fecha en que se realizó la inscripción
  - `estado_inscripcion`: Estado (INSCRITO, RETIRADO, SUSPENDIDO, APROBADO, REPROBADO)
  - `num_intento`: Número de intento del estudiante
  - `promedio_parcial`: Promedio parcial del estudiante

### 2. **Política de Autorización: InscripcionCursoPolicy**
Ubicación: `app/Policies/InscripcionCursoPolicy.php`

Implementa reglas de autorización:

| Operación | Administrador | Docente | Estudiante |
|-----------|---------------|---------|-----------|
| Ver todas | ✓ | ✓* | ✗ |
| Ver una | ✓ | ✓* | ✓ (propia) |
| Crear | ✓ | ✓* | ✗ |
| Actualizar | ✓ | ✓* | ✗ |
| Eliminar | ✓ | ✗ | ✗ |

*Docentes solo pueden operar en cursos donde dictan secciones

**Métodos de autorización:**
- `viewAny()`: Ver listado de inscripciones
- `view()`: Ver inscripción específica
- `create()`: Crear nueva inscripción
- `createForCourse()`: Crear en un curso específico (validación adicional)
- `update()`: Actualizar inscripción
- `delete()`: Eliminar inscripción

### 3. **Form Requests (Validación)**

#### `StoreInscripcionCursoRequest`
Ubicación: `app/Http/Requests/StoreInscripcionCursoRequest.php`

Valida la creación de inscripciones:
- `id_curso`: Requerido, debe existir en tabla Curso
- `id_estudiante`: Requerido, debe existir en tabla Estudiante, no duplicado
- `cod_inscripcion_uta`: Opcional, máximo 255 caracteres
- `fecha_inscripcion`: Opcional, formato fecha válido
- `estado_inscripcion`: Opcional, valores permitidos
- `num_intento`: Opcional, mínimo 1

#### `UpdateInscripcionCursoRequest`
Ubicación: `app/Http/Requests/UpdateInscripcionCursoRequest.php`

Valida la actualización de inscripciones (todos los campos opcionales)

### 4. **Controlador: InscripcionCursoController**
Ubicación: `app/Http/Controllers/Admin/InscripcionCursoController.php`

**Métodos principales:**

```php
// Listado con filtros y búsqueda
index(Request $request)

// Mostrar formulario crear
create(Request $request)

// Guardar nueva inscripción
store(StoreInscripcionCursoRequest $request)

// Mostrar detalle
show(InscripcionCurso $inscripcionCurso)

// Mostrar formulario editar
edit(InscripcionCurso $inscripcionCurso)

// Guardar cambios
update(UpdateInscripcionCursoRequest $request, InscripcionCurso $inscripcionCurso)

// Eliminar inscripción
destroy(InscripcionCurso $inscripcionCurso)

// AJAX: Obtener estudiantes sin inscribir en un curso
getEstudiantesDisponibles(Request $request)

// AJAX: Obtener inscripciones de un curso
getByCurso(Request $request)

// Exportar a CSV
exportCsv(Request $request)
```

**Características:**
- Validación de autorización con `Gate::authorize()`
- Filtrado por usuario (docentes ven solo sus cursos)
- Transacciones de base de datos
- Manejo de errores con logging
- Respuestas JSON para endpoints AJAX

### 5. **Rutas**
Ubicación: `routes/web.php`

```php
// Rutas CRUD estándar
Route::resource('inscripciones_cursos', InscripcionCursoController::class);

// Endpoints AJAX
Route::get('inscripciones_cursos/ajax/disponibles', 'getEstudiantesDisponibles')
Route::get('inscripciones_cursos/ajax/by-curso', 'getByCurso')

// Exportar CSV
Route::get('inscripciones_cursos/export/csv', 'exportCsv')
```

Todas las rutas están en el grupo `admin` con middleware:
- `auth`: Usuario autenticado
- `verified`: Email verificado
- `is_admin`: Para la mayoría de operaciones

### 6. **Vistas (Interfaz Web)**
Ubicación: `resources/js/pages/admin/`

#### InscripcionesCursos.svelte
Página principal de listado:
- Busca por nombre de estudiante o código de curso
- Filtra por curso específico
- Filtra por estado de inscripción
- DataTable con opciones de editar/eliminar
- Botón para exportar a CSV
- Paginación

#### CreateInscripcionCurso.svelte
Formulario para crear nueva inscripción:
- Selector dinámico de estudiantes (se actualiza al seleccionar curso)
- Muestra solo estudiantes sin inscribir en el curso seleccionado
- Validación del lado del cliente y servidor
- Manejo de errores con mensajes claros

#### EditInscripcionCurso.svelte
Formulario para editar inscripción:
- Datos de curso y estudiante (read-only)
- Campos editables: estado, código, fecha, promedio
- Botón para eliminar inscripción
- Confirmación antes de eliminar

### 7. **Registro en AuthServiceProvider**
Ubicación: `app/Providers/AuthServiceProvider.php`

La política se registra en el mapeo de políticas:

```php
protected $policies = [
    InscripcionCurso::class => InscripcionCursoPolicy::class,
];
```

## Flujo de Uso

### Para Administrador

1. **Navegar a Inscripciones**: Ir a `admin/inscripciones_cursos`
2. **Ver Listado**: Se muestran todas las inscripciones
3. **Crear Nueva**: Clic en "Nueva Inscripción"
   - Selecciona curso
   - El sistema carga estudiantes disponibles
   - Completa los datos
   - Confirma
4. **Editar**: Clic en inscripción, modifica estado/datos, guarda
5. **Eliminar**: Clic en inscripción, confirma eliminación
6. **Exportar**: Clic en "Exportar CSV" para descarga

### Para Docente

1. **Navegar a Inscripciones**: Ir a `admin/inscripciones_cursos`
2. Ver solo inscripciones de sus cursos (donde dicta secciones)
3. Crear/actualizar inscripciones solo en sus cursos
4. No puede eliminar inscripciones
5. Puede exportar datos de sus cursos

### Para Estudiante

1. Ver sus propias inscripciones en su contexto
2. No puede crear/editar/eliminar inscripciones
3. Solo administrador y docentes pueden manejar inscripciones

## Base de Datos

### Tabla: Inscripcion_Curso

```sql
CREATE TABLE Inscripcion_Curso (
    id_curso INTEGER NOT NULL,
    id_estudiante INTEGER NOT NULL,
    cod_inscripcion_uta VARCHAR,
    num_intento SMALLINT DEFAULT 1,
    fecha_inscripcion DATE,
    estado_inscripcion VARCHAR(20) DEFAULT 'INSCRITO',
    promedio_parcial NUMERIC,
    PRIMARY KEY (id_curso, id_estudiante),
    FOREIGN KEY (id_curso) REFERENCES Curso(id_curso),
    FOREIGN KEY (id_estudiante) REFERENCES Estudiante(id_estudiante)
);
```

### Estados de Inscripción

- `INSCRITO`: Estudiante actualmente inscrito
- `RETIRADO`: Se retiró del curso
- `SUSPENDIDO`: Suspensión temporal
- `APROBADO`: Completó exitosamente
- `REPROBADO`: No aprobó el curso

## Relaciones en Modelos

### Estudiante → Inscripciones Cursos

```php
public function cursosInscritos()
{
    return $this->belongsToMany(
        Curso::class,
        'Inscripcion_Curso',
        'id_estudiante',
        'id_curso'
    )->withPivot('cod_inscripcion_uta', 'num_intento', 'fecha_inscripcion', 
                 'estado_inscripcion', 'promedio_parcial');
}
```

### Curso → Inscripciones Cursos

```php
public function inscripcionCursos()
{
    return $this->hasMany(InscripcionCurso::class, 'id_curso', 'id_curso');
}
```

### Docente → Secciones → Cursos

Los docentes acceden a cursos a través de las secciones que dictan:

```php
public function seccionesQueDicta()
{
    return $this->hasMany(Seccion::class, 'id_docente', 'id_docente');
}
```

## Validaciones Implementadas

### Nivel de Base de Datos

- Claves primarias compuestas previenen duplicados
- Restricciones de integridad referencial
- Valores por defecto

### Nivel de Aplicación

- FormRequest con validaciones personalizadas
- Evita inscripciones duplicadas
- Valida estados permitidos
- Valida rango de promedio (0-7)
- Valida fechas válidas

### Nivel de Autorización

- Gate::authorize() en cada método
- Docentes solo acceden sus cursos
- Estudiantes solo ven sus inscripciones
- Admin acceso completo

## Endpoints API

### Listar Inscripciones
```
GET /admin/inscripciones_cursos
Parámetros: search, id_curso, estado_inscripcion, page, per_page
```

### Ver Formulario Crear
```
GET /admin/inscripciones_cursos/create
```

### Crear Inscripción
```
POST /admin/inscripciones_cursos
Body: id_curso, id_estudiante, cod_inscripcion_uta, fecha_inscripcion, estado_inscripcion, num_intento
```

### Ver Detalle
```
GET /admin/inscripciones_cursos/{id_curso},{id_estudiante}
```

### Ver Formulario Editar
```
GET /admin/inscripciones_cursos/{id_curso},{id_estudiante}/edit
```

### Actualizar
```
PUT /admin/inscripciones_cursos/{id_curso},{id_estudiante}
Body: cod_inscripcion_uta, fecha_inscripcion, estado_inscripcion, num_intento, promedio_parcial
```

### Eliminar
```
DELETE /admin/inscripciones_cursos/{id_curso},{id_estudiante}
```

### AJAX - Estudiantes Disponibles
```
GET /admin/inscripciones_cursos/ajax/disponibles?id_curso=1
Response: { estudiantes: [...] }
```

### AJAX - Inscripciones por Curso
```
GET /admin/inscripciones_cursos/ajax/by-curso?id_curso=1
Response: { inscripciones: [...] }
```

### Exportar CSV
```
GET /admin/inscripciones_cursos/export/csv?id_curso=1
Response: CSV file download
```

## Manejo de Errores

### Errores 404
- Inscripción no encontrada
- Curso no encontrado
- Estudiante no encontrado

### Errores 403
- Usuario sin autorización
- Docente intenta acceder curso que no dicta
- Estudiante intenta eliminar inscripción

### Errores 422 (Validación)
- Inscripción duplicada
- Datos inválidos (formato, tipos)
- Valores fuera de rango

### Errores 500
- Error de base de datos
- Error de transacción
- Se registran en `storage/logs/`

## Casos de Uso

### 1. Administrador Inscribe Estudiante en Curso
1. Admin va a Inscripciones
2. Clic "Nueva Inscripción"
3. Selecciona curso y estudiante
4. Completa datos (fecha, código si tiene)
5. Guarda
6. Sistema crea registro en Inscripcion_Curso

### 2. Docente Gestiona Inscripciones de Sus Cursos
1. Docente va a Inscripciones
2. Sistema filtra automáticamente sus cursos
3. Puede ver estudiantes inscritos
4. Puede cambiar estado (ej: APROBADO)
5. Puede actualizar promedio
6. No puede crear/eliminar (solo admin)

### 3. Ver Estudiantes Inscritos en Curso
1. En página de cursos, expandir curso
2. Sistema carga automáticamente inscritos
3. Muestra apellido, usuario, estado, promedio

### 4. Retirar Estudiante de Curso
1. Ir a Inscripción del estudiante
2. Cambiar estado a "RETIRADO"
3. Guardar
4. Sistema actualiza registro

### 5. Exportar Nómina de Curso
1. Filtrar por curso específico
2. Clic "Exportar CSV"
3. Descargar archivo con: ID, nombre, usuario, estado, intento, promedio

## Tests (Recomendados)

```php
// Crear inscripción
test('admin_can_create_inscription', function() {
    $admin = User::admin();
    $curso = Curso::factory()->create();
    $estudiante = Estudiante::factory()->create();
    
    $this->post(route('admin.inscripciones_cursos.store'), [
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante
    ])->assertRedirect(route('admin.inscripciones_cursos.index'));
});

// Docente solo en sus cursos
test('docente_cannot_create_inscription_in_other_courses', function() {
    $docente = User::with('docente');
    $curso = Curso::factory()->create();
    $estudiante = Estudiante::factory()->create();
    
    $this->post(route('admin.inscripciones_cursos.store'), [
        'id_curso' => $curso->id_curso,
        'id_estudiante' => $estudiante->id_estudiante
    ])->assertForbidden();
});

// Evitar duplicados
test('cannot_create_duplicate_inscription', function() {
    $inscripcion = InscripcionCurso::factory()->create();
    
    $this->post(route('admin.inscripciones_cursos.store'), [
        'id_curso' => $inscripcion->id_curso,
        'id_estudiante' => $inscripcion->id_estudiante
    ])->assertHasErrors('id_estudiante');
});
```

## Próximos Pasos (Opcionales)

1. **Importar inscripciones desde CSV**: Carga masiva
2. **Secciones inscritas**: Relación automática Inscripcion_Seccion
3. **Reportes**: Estadísticas de inscripciones por curso
4. **Notificaciones**: Avisar estudiante cuando se inscribe
5. **Cambio de sección**: Mover estudiante entre secciones
6. **Auditoría**: Log de cambios en inscripciones

## Referencias

- [Laravel Policies Documentation](https://laravel.com/docs/authorization#creating-policies)
- [Inertia.js Documentation](https://inertiajs.com/)
- [Svelte Documentation](https://svelte.dev/)
- [Form Requests](https://laravel.com/docs/validation#form-request-validation)
