# Gestión de Grupos y Entregas - Funcionalidad Docente

## Descripción General

Sistema completo que permite a docentes crear, gestionar y evaluar grupos de trabajo en actividades, con capacidad de reutilizar membresías de actividades anteriores y revisar entregas de archivos.

**Requisito Principal**: Los grupos se conforman **únicamente por alumnos inscritos en el curso** (validado en BD mediante `InscripcionCurso`).

---

## 1. Modelos Extendidos

### 1.1 `ActividadAsignadaGrupo` 

**Ubicación**: `app/Models/Agenda/ActividadAsignadaGrupo.php`

**Relaciones Agregadas**:
```php
public function miembros()  // hasMany IntegranteGrupo
public function entregas()  // hasMany Agenda
```

**Métodos Helpers**:
```php
/**
 * Obtiene los miembros con sus datos de estudiante cargados
 * Retorna array con: id_asignado_actividad, id_estudiante, 
 * nota_individual, diferencia_decimas, nombre_completo, rut
 */
public function getMiembrosConDetalles()
```

### 1.2 `IntegranteGrupo`

**Ubicación**: `app/Models/Agenda/IntegranteGrupo.php`

**Métodos Helpers**:
```php
/**
 * Obtiene detalles del estudiante (nombre, rut, email)
 */
public function getDetallesEstudiante()
```

### 1.3 `Agenda` (Entregas)

**Ubicación**: `app/Models/Agenda/Agenda.php`

**Métodos Helpers**:
```php
public function getArchivoInfo()           // Info del archivo adjunto
public function tieneEvaluacion()          // Verifica si está evaluada
public function getDetallesEntrega()       // Info completa de la entrega
```

---

## 2. Controlador: DocenteActivityController

**Ubicación**: `app/Http/Controllers/Docente/DocenteActivityController.php`

### 2.1 Métodos de Gestión de Grupos

#### **storeGroup()** - Crear Grupo
```
POST /docente/cursos/{curso}/actividades/{actividad}/grupos-create

Body:
{
  "nombre_grupo": "Equipo A",          // opcional
  "estudiantes": [10, 15, 20]          // IDs de estudiantes inscritos
}

Respuesta (201):
{
  "success": "Grupo creado correctamente.",
  "grupo": { ... }
}

Validaciones:
- ✅ Actividad es grupal (es_grupal = TRUE)
- ✅ Estudiantes existen (exists:usuario.estudiante)
- ✅ Todos inscritos en el curso (InscripcionCurso)
- ✅ No supera max_integrantes
```

#### **addStudentToGroup()** - Agregar Estudiante a Grupo
```
POST /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/estudiante

Body:
{
  "id_estudiante": 25
}

Respuesta (201):
{
  "success": "Estudiante agregado al grupo."
}

Validaciones:
- ✅ Grupo existe y pertenece a la actividad
- ✅ Estudiante inscrito en curso
- ✅ No está ya en el grupo
- ✅ No supera límite de integrantes
```

#### **getGroupsByActivity()** - Listar Grupos
```
GET /docente/cursos/{curso}/actividades/{actividad}/grupos-list

Respuesta (200):
[
  {
    "grupo": 100,
    "nota": 7.5,
    "id_estado": 2,
    "estado": {
      "id_estado": 2,
      "titulo": "ENVIADA"
    },
    "integrantes": [
      {
        "id_asignado_actividad": 1,
        "id_estudiante": 10,
        "nota_individual": 7.0,
        "diferencia_decimas": 0,
        "nombre_completo": "Juan Pérez García",
        "rut": "12345678-9"
      },
      ...
    ],
    "cantidad_integrantes": 3
  },
  ...
]
```

#### **copyGroupsFromActivity()** - Copiar Grupos
```
POST /docente/cursos/{curso}/actividades/{actividad}/grupos-copy

Body:
{
  "id_actividad_origen": 5
}

Respuesta (200):
{
  "success": "Se copiaron 3 grupos correctamente.",
  "grupos_creados": 3,
  "grupos_total_origen": 3
}

Lógica:
- Obtiene todos los grupos de la actividad origen
- Para cada grupo, verifica que TODOS los estudiantes sigan inscritos
- Crea nuevos grupos con nuevos IDs para evitar conflictos
- Solo copia si están todos inscritos (evita datos huérfanos)

Validaciones:
- ✅ Actividad actual es grupal
- ✅ Actividad origen es grupal
- ✅ Ambas pertenecen al mismo curso
```

### 2.2 Métodos de Gestión de Entregas

#### **getSubmissionsByActivity()** - Listar Entregas de Actividad
```
GET /docente/cursos/{curso}/actividades/{actividad}/entregas

Respuesta (200):
[
  {
    "id_agenda": 1,
    "fecha_envio": "2026-05-13 14:30:00",
    "mensaje": "Aquí está la entrega",
    "tipo_registro": "ENTREGA",
    "archivo": {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "nombre_original": "trabajo_grupo_a.pdf",
      "extension": "pdf",
      "mime_type": "application/pdf",
      "peso_bytes": 1024000,
      "fecha_creacion": "2026-05-13 14:30:00"
    },
    "usuario_emisor": {
      "nombre": "Juan Pérez García",
      "rut": "12345678-9"
    },
    "evaluada": false
  },
  ...
]
```

#### **getSubmissionsByGroup()** - Entregas de Grupo Específico
```
GET /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas

Respuesta (200):
[
  { ... mismo formato que arriba ... }
]

Validaciones:
- ✅ Grupo pertenece a la actividad
```

#### **downloadSubmissionFile()** - Descargar Archivo
```
GET /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas/{agenda}/descargar

Respuesta: 
- File download con headers correctos
- Content-Type del archivo (PDF, DOCX, etc)
- Nombre original preservado

Validaciones:
- ✅ Entrega pertenece al grupo
- ✅ Archivo existe en servidor
- ✅ Permisos de docente
```

---

## 3. Rutas Implementadas

```php
// Gestión avanzada de grupos
POST   /docente/cursos/{curso}/actividades/{actividad}/grupos-create
POST   /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/estudiante
GET    /docente/cursos/{curso}/actividades/{actividad}/grupos-list
POST   /docente/cursos/{curso}/actividades/{actividad}/grupos-copy

// Gestión de entregas/archivos
GET    /docente/cursos/{curso}/actividades/{actividad}/entregas
GET    /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas
GET    /docente/cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas/{agenda}/descargar
```

**Archivo**: `routes/web.php` (líneas 301-310)

---

## 4. Validation Request

**Ubicación**: `app/Http/Requests/StoreGroupRequest.php`

```php
[
    'nombre_grupo' => 'nullable|string|max:100',
    'estudiantes' => 'required|array|min:1',
    'estudiantes.*' => 'required|integer|exists:usuario.estudiante,id_estudiante',
]
```

---

## 5. Flujos de Uso

### Flujo 1: Crear un Grupo Nuevo

```
1. Docente selecciona actividad grupal
2. POST /grupos-create con lista de estudiantes
3. Sistema valida:
   - Estudiantes existen en BD
   - Todos inscritos en InscripcionCurso
   - No supera max_integrantes
4. Crea ActividadAsignadaGrupo + IntegranteGrupo (múltiples)
5. Retorna grupo con detalles
```

### Flujo 2: Copiar Grupos de Actividad Anterior

```
1. Docente crea actividad nueva (grupal)
2. POST /grupos-copy con id_actividad_origen
3. Sistema obtiene todos los grupos origen
4. Para cada grupo:
   - Obtiene lista de estudiantes
   - Verifica que TODOS sigan inscritos
   - Si sí: crea nuevo grupo con nuevos miembros
   - Si no: descarta ese grupo (evita inconsistencias)
5. Retorna cantidad de grupos copiados
```

### Flujo 3: Revisar Entregas de un Grupo

```
1. Docente selecciona grupo
2. GET /grupos/{grupo}/entregas
3. Sistema retorna todas las Agenda del grupo
4. Docente puede:
   - Ver metadata del archivo
   - Ver quién envió (usuario_emisor)
   - Saber si está evaluada
   - Descargar archivo
```

### Flujo 4: Descargar Archivo Entregado

```
1. Docente en vista de entregas
2. Click en "Descargar"
3. GET /entregas/{agenda}/descargar
4. Sistema valida permisos
5. Retorna descarga con:
   - Nombre original del archivo
   - MIME type correcto
   - Contenido del archivo
```

---

## 6. Seguridad y Validaciones

### Validaciones de Seguridad (en cada endpoint)

| Validación                | Método                            | Descripción                                |
| ------------------------- | --------------------------------- | ------------------------------------------ |
| **Autenticación**         | `autorizarDocenteCurso()`         | Verifica que sea docente del curso         |
| **Actividad en Curso**    | Comparación `id_curso`            | Evita acceso a actividades de otros cursos |
| **Grupo en Actividad**    | Comparación `id_actividad`        | Evita acceso a grupos de otras actividades |
| **Inscritos en Curso**    | `InscripcionCurso::where()`       | Solo estudiantes inscritos                 |
| **Límite de Integrantes** | Comparación con `max_integrantes` | Previene sobrecarga de grupos              |
| **Duplicados**            | `IntegranteGrupo::where()`        | No permite duplicar estudiante en grupo    |
| **Archivo Existe**        | `file_exists()` en BD             | Valida que file esté en servidor           |

### Restricciones de Base de Datos

```sql
-- Integrantes vinculados a grupos específicos
ALTER TABLE agenda.integrante_grupo 
ADD CONSTRAINT fk_grupo 
FOREIGN KEY (grupo) REFERENCES actividad_asignada_grupo(grupo)
ON DELETE RESTRICT;  -- Protege contra borrado accidental
```

---

## 7. Casos de Uso Avanzados

### Caso 1: Reutilizar Grupos Parcialmente

**Escenario**: 
- Actividad 1 tenía grupos: A(10,15,20), B(25,30,35)
- Estudiante 30 se retiró del curso
- Se copia a Actividad 2

**Resultado**:
- Grupo A se copia: OK (todos siguen inscritos)
- Grupo B se descarta: Estudiante 30 no está inscrito

```json
{
  "success": "Se copiaron 1 grupos correctamente.",
  "grupos_creados": 1,
  "grupos_total_origen": 2
}
```

### Caso 2: Entregas Múltiples de un Grupo

**Escenario**: Grupo de 3 estudiantes envía trabajo varias veces

```
Entrega 1: 2026-05-10 (borrador)
Entrega 2: 2026-05-12 (versión mejorada)
Entrega 3: 2026-05-13 (versión final)
```

**Sistema**:
- Todas quedan registradas en Agenda
- Docente puede ver todas y descargar cada una
- Evaluación se linkea a entrega específica

---

## 8. Relaciones de Bases de Datos

```
Actividad (es_grupal = TRUE, max_integrantes = 5)
  ↓
ActividadAsignadaGrupo (grupo = 100, id_estado = ABIERTA)
  ↓
IntegranteGrupo (grupo = 100, id_estudiante = 10)  ← Vinculado por FK
    ├─→ Estudiante (id_estudiante = 10)
    └─→ Usuario (nombre, rut, email)

Agenda (grupo = 100)  ← Entrega del grupo
  ├─→ uuid_archivo_subido (FK → Operaciones.Archivo)
  ├─→ id_usuario_emisor (FK → Usuario)
  └─→ Evaluacion (calificación)
```

---

## 9. Transacciones

### Creación de Grupo (con Integrantes)
```php
DB::beginTransaction();
  - Crear ActividadAsignadaGrupo
  - Crear N filas de IntegranteGrupo
  - Si falla cualquiera: ROLLBACK completo
DB::commit();
```

### Copia de Grupos
```php
DB::beginTransaction();
  - Para cada grupo origen:
    - Verificar inscritos
    - Crear nuevo grupo
    - Insertar integrantes
  - Si falla: ROLLBACK de TODOS los grupos creados
DB::commit();
```

---

## 10. Manejo de Errores

| Error                    | Status | Mensaje                                                 |
| ------------------------ | ------ | ------------------------------------------------------- |
| Actividad no grupal      | 422    | "Esta actividad no es grupal."                          |
| Estudiantes no inscritos | 422    | "Algunos estudiantes no están inscritos en este curso." |
| Excede max_integrantes   | 422    | "No se pueden agregar más de X estudiantes."            |
| Estudiante ya en grupo   | 422    | "El estudiante ya está en este grupo."                  |
| Grupo no encontrado      | 404    | "Grupo no encontrado."                                  |
| Archivo no existe        | 404    | "El archivo no existe en el servidor."                  |
| Sin permisos             | 403    | "No tienes permiso para gestionar este curso."          |
| Error BD                 | 500    | "Error al crear el grupo: ..."                          |

---

## 11. Instalación y Configuración

### Paso 1: Agregar Modelos Extendidos

Asegúrate que los modelos tienen los métodos en sus clases:
- `ActividadAsignadaGrupo::getMiembrosConDetalles()`
- `IntegranteGrupo::getDetallesEstudiante()`
- `Agenda::getArchivoInfo()`

### Paso 2: Agregar Rutas

En `routes/web.php` dentro del grupo de docente:
```php
// Gestión avanzada de grupos
Route::post('cursos/{curso}/actividades/{actividad}/grupos-create', [...])->name('...');
Route::post('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/estudiante', [...])->name('...');
Route::get('cursos/{curso}/actividades/{actividad}/grupos-list', [...])->name('...');
Route::post('cursos/{curso}/actividades/{actividad}/grupos-copy', [...])->name('...');

// Gestión de entregas
Route::get('cursos/{curso}/actividades/{actividad}/entregas', [...])->name('...');
Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas', [...])->name('...');
Route::get('cursos/{curso}/actividades/{actividad}/grupos/{grupo}/entregas/{agenda}/descargar', [...])->name('...');
```

### Paso 3: Crear Request Validation

Crear `app/Http/Requests/StoreGroupRequest.php` con validaciones de grupos.

### Paso 4: Extender Controlador

Agregar los métodos al `DocenteActivityController`:
- `storeGroup()`
- `addStudentToGroup()`
- `getGroupsByActivity()`
- `copyGroupsFromActivity()`
- `getSubmissionsByActivity()`
- `getSubmissionsByGroup()`
- `downloadSubmissionFile()`

---

## 12. Pruebas Recomendadas

```bash
# Crear grupo
POST /docente/cursos/1/actividades/5/grupos-create
{"estudiantes": [10, 15, 20]}

# Agregar estudiante
POST /docente/cursos/1/actividades/5/grupos/100/estudiante
{"id_estudiante": 25}

# Listar grupos
GET /docente/cursos/1/actividades/5/grupos-list

# Copiar grupos
POST /docente/cursos/1/actividades/7/grupos-copy
{"id_actividad_origen": 5}

# Obtener entregas
GET /docente/cursos/1/actividades/5/entregas
GET /docente/cursos/1/actividades/5/grupos/100/entregas

# Descargar archivo
GET /docente/cursos/1/actividades/5/grupos/100/entregas/1/descargar
```

---

## 13. Notas Importantes

⚠️ **Validación de Inscritos**
- Toda operación que agregue estudiantes valida `InscripcionCurso`
- Si un estudiante se retira, los grupos existentes NO se eliminan (integridad histórica)
- Al copiar, solo se copian grupos con todos los integrantes inscritos

⚠️ **IDs de Grupos**
- Los IDs de `ActividadAsignadaGrupo.grupo` son únicos por actividad
- No se reutilizan entre actividades diferentes
- Esto previene conflictos de FK con `IntegranteGrupo`

⚠️ **Transacciones**
- Las operaciones complejas usan `DB::transaction()` para atomicidad
- Si falla una parte, se revierte todo (no quedanregistros huérfanos)

⚠️ **Archivos**
- Los archivos se almacenan en `operaciones.archivo` con UUID
- La ruta física se guarda en `ruta_fisica` (relativa a `storage/app/`)
- Asegúrate que la carpeta existe y tiene permisos de lectura

---

## 14. Preguntas Frecuentes

**P: ¿Qué pasa si un estudiante se retira después de estar en un grupo?**
R: Los grupos existentes se mantienen (integridad histórica). Si intentas copiar ese grupo, se descartará porque el estudiante no está en `InscripcionCurso`.

**P: ¿Puedo usar los mismos IDs de grupo en actividades diferentes?**
R: No. La FK de `IntegranteGrupo` previene reusar IDs. Sistema genera automáticamente nuevos.

**P: ¿Dónde se guardan los archivos?**
R: En `storage/app/` con path guardado en `operaciones.archivo.ruta_fisica`.

**P: ¿Qué significa "estado" del grupo?**
R: Es `id_estado` que referencia `EstadoActividad` (ABIERTA, ENVIADA, CALIFICADA, etc).

---

**Documento versión**: 1.0  
**Última actualización**: May 13, 2026  
**Autor**: Sistema de Gestión Académica UTAMED
