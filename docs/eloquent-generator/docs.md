# Documentación del Generador de Eloquent Models

## Introducción

### ¿Qué es este generador?

Este es un script automatizado que genera modelos Eloquent para Laravel a partir de una base de datos PostgreSQL existente. Lee la estructura de tu base de datos (tablas, columnas, relaciones) y crea automáticamente clases PHP que representan esas tablas.

### ¿Qué problema resuelve?

**Sin el generador:**

- Crear modelos manualmente para 50+ tablas toma días
- Fácil olvidar relaciones entre tablas
- Nombres inconsistentes entre modelos y base de datos
- Perder tiempo configurando fillables, timestamps, soft deletes

**Con el generador:**

- Genera todos los modelos en segundos
- Detecta automáticamente todas las relaciones (belongsTo, hasMany, belongsToMany)
- Mantiene consistencia con la base de datos
- Configura automáticamente fillables, timestamps, soft deletes
- Preserva personalizaciones al regenerar

### ¿Cómo funciona a alto nivel?

El generador sigue un proceso de 4 pasos:

1. **Detección de Tablas** → Encuentra todas las tablas en esquemas `utamed.*`
2. **Análisis de Relaciones** → Lee Foreign Keys y detecta relaciones 1:1, 1:N, N:M
3. **Configuración de Pivots** → Procesa tablas pivot para relaciones belongsToMany
4. **Generación de Modelos** → Crea archivos PHP con Base Models y Extended Models

---

## I. Flujo General del Programa

### Vista de Ejecución

```plaintext
INICIO: php scripts/generate_models.php

  ↓

PASO 1: DETECCIÓN DE TABLAS
  • Consulta information_schema.tables
  • Filtra esquemas
  • Encuentra ~50 tablas

  ↓

PASO 2: PRE-ANÁLISIS DE RELACIONES
  • Para CADA tabla: consulta pg_constraint (FKs)
  • Construye índice global: $allForeignKeys
  • Detecta relaciones 1:1 vs 1:N con pg_index
  → Resultado: Mapa completo de quién apunta a quién

  ↓

PASO 3: PROCESAMIENTO DE PIVOTS
  • Filtra tablas configuradas en $manualPivotTables
  • Para cada pivot: detecta sus relaciones a las tablas
  • Valida que tenga ≥2 FKs (requisito para pivot)
  → Resultado: Arreglo con metadata para crear belongsToMany customizadas

  ↓

PASO 4: GENERACIÓN DE MODELOS (bucle por cada tabla)
    4.1 Extrae columnas, PK, timestamps
    4.2 Genera belongsTo (para cada FK detectada)
    4.3 Genera hasMany/hasOne (relaciones inversas)
    4.4 Genera belongsToMany (si participa en pivot)
    4.5 Crea Base Model (sobrescribible)
    4.6 Crea Extended Model (solo si NO existe)

  ↓

FIN: ~100 archivos generados en app/Models/
  • Base Models: app/Models/Base/{Schema}/Base*.php
  • Extended: app/Models/{Schema}/*.php
```

### Ejemplo Concreto

Para la tabla `utamed.Administrativo.Facultad`:

```plaintext
INPUT (Base de Datos):
  Tabla: Facultad
  Columnas: id_facultad (PK), nombre_facultad, fecha_creacion
  Relaciones: Departamento → Facultad (FK: id_facultad)

PROCESO:
  1. Detecta columnas → genera $fillable = ['nombre_facultad']
  2. Detecta PK → $primaryKey = 'id_facultad'
  3. Detecta fecha_creacion → const CREATED_AT = 'fecha_creacion'
  4. Encuentra FK desde Departamento → genera hasMany('departamentos')

OUTPUT (Archivos PHP):
  app/Models/Base/Administrativo/BaseFacultad.php
  app/Models/Administrativo/Facultad.php  (solo si no existe)
```

---

## II. Conceptos Necesarios para Entender el Generador

### Patrón Modelo Base/Extendido

**¿Por qué dos archivos por tabla?**

El problema: Si regeneras modelos, pierdes cualquier función personalizada aplicada sobre los modelos generados (scopes, accessors, métodos custom).

**Ejemplo:**

```php
// app/Models/Base/Administrativo/BaseFacultad.php (REGENERABLE)
abstract class BaseFacultad extends Model {
    protected $table = 'utamed.Administrativo.Facultad';
    protected $fillable = ['nombre_facultad'];
    // ... relaciones autogeneradas
}

// app/Models/Administrativo/Facultad.php (TUS PERSONALIZACIONES)
class Facultad extends BaseFacultad {
    // Aquí agregas tus métodos custom:
    public function scopeActivas($query) {
        return $query->whereNull('fecha_eliminacion');
    }
}
```

### Tipos de Relaciones Eloquent

**¿Por qué necesitamos entenderlas?**

El generador crea automáticamente métodos de relación. Debes saber cuál tipo se aplica en cada caso.

#### belongsTo (Muchos a Uno) (Relaciones Inversas)

**Concepto:** "Esta tabla tiene una FK que apunta a otra tabla"

```
Departamento (id_facultad FK) → Facultad
```

**Genera:**

```php
// En BaseDepartamento.php
public function facultad() {
    return $this->belongsTo(Facultad::class, 'id_facultad');
}
```

#### hasMany / hasOne (Uno a Muchos / Uno a Uno)

**Concepto:** "Otras tablas tienen FKs que apuntan a esta tabla"

**hasMany (1:N):**

```
Facultad ← Departamento (muchos departamentos por facultad)
```

**Genera:**

```php
// En BaseFacultad.php
public function departamentos() {
    return $this->hasMany(Departamento::class, 'id_facultad');
}
```

**hasOne (1:1):**

```
Usuario ← Perfil (un solo perfil por usuario, FK con índice UNIQUE)
```

**Genera:**

```php
// En BaseUsuario.php
public function perfil() {
    return $this->hasOne(Perfil::class, 'id_usuario');
}
```

**¿Cómo distingue 1:1 de 1:N?** Busca índices UNIQUE en la FK.

#### belongsToMany (Muchos a Muchos)

**Concepto:** "Dos tablas conectadas por una tabla pivot intermedia"

```
Estudiante ↔ Inscripcion_Curso (pivot) ↔ Curso
```

**Genera:**

```php
// En BaseEstudiante.php
public function cursos() {
    return $this->belongsToMany(Curso::class, 'utamed.Curso.Inscripcion_Curso',
        'id_estudiante', 'id_curso');
}

// En BaseCurso.php
public function estudiantes() {
    return $this->belongsToMany(Estudiante::class, 'utamed.Curso.Inscripcion_Curso',
        'id_curso', 'id_estudiante');
}
```

**Uso:**

```php
$estudiante->cursos; // Todos los cursos del estudiante
$curso->estudiantes; // Todos los estudiantes del curso
```

### Tablas de PostgreSQL Relevantes

**¿Por qué necesitamos conocerlas?**

El generador no lee la base de datos con magia. Lee catálogos internos de PostgreSQL que almacenan metadata sobre tablas, columnas, restricciones e índices.

| Tabla             | Campo        | Descripción                               | Uso en el Generador                  |
| ----------------- | ------------ | ----------------------------------------- | ------------------------------------ |
| **pg_constraint** | oid          | ID único de la restricción                | Identificar cada FK/PK               |
|                   | contype      | Tipo: 'f' (FK), 'p' (PK), 'u' (Unique)    | Filtrar solo Foreign Keys            |
|                   | conrelid     | OID tabla origen (contiene FK)            | Saber qué tabla tiene la FK          |
|                   | confrelid    | OID tabla destino (referenciada)          | Saber a qué tabla apunta             |
|                   | conkey       | Array columnas locales                    | Nombre de la columna FK local        |
|                   | confkey      | Array columnas remotas                    | Nombre de la columna PK referenciada |
| **pg_class**      | relname      | Nombre de tabla/vista/índice              | Obtener nombres de tablas            |
|                   | relnamespace | OID del esquema                           | Asociar tabla con su esquema         |
| **pg_namespace**  | nspname      | Nombre del esquema                        | Filtrar solo esquemas `utamed.*`     |
| **pg_attribute**  | attnum       | Número de columna                         | Mapear números a nombres de columnas |
| **pg_index**      | indexrelid   | Referencia al índice en pg_class          | Encontrar índices de una tabla       |
|                   | indrelid     | Referencia a la tabla que tiene el índice | Asociar índice con su tabla          |
|                   | indkey       | Array columnas del índice                 | Qué columnas forman el índice        |
|                   | indisunique  | Si es unique constraint                   | Diferenciar hasOne (1:1) de hasMany  |

**Ejemplo Visual:**

Para la FK `Departamento.id_facultad → Facultad.id_facultad`:

```sql
-- En pg_constraint encontrarás:
contype: 'f'                    -- Es una Foreign Key
conrelid: OID(Departamento)     -- La tabla que tiene la FK
confrelid: OID(Facultad)        -- La tabla referenciada
conkey: {2}                     -- Columna #2 de Departamento
confkey: {1}                    -- Columna #1 de Facultad

-- Luego en pg_attribute:
attrelid=Departamento, attnum=2 → attname='id_facultad'
attrelid=Facultad, attnum=1     → attname='id_facultad'

-- Resultado: Departamento.id_facultad → Facultad.id_facultad
```

---

## III. Paso 1: Detección de Tablas

### ¿Qué hace este paso?

Encuentra todas las tablas PostgreSQL en los esquemas `utamed.*` del catálogo `utamed_1ra_fase`.

### Query Utilizada

```sql
SELECT table_schema, table_name
FROM information_schema.tables
WHERE table_catalog = 'utamed_1ra_fase'
  AND table_schema LIKE 'utamed.%'
ORDER BY table_schema, table_name
```

PostgreSQL permite esquemas con puntos en sus nombres. En este proyecto los esquemas son:

- `utamed.Administrativo`
- `utamed.Usuario`
- `utamed.Curso`
- `utamed.Actividad`

### Resultado

Genera un array de objetos:

```php
[
    {table_schema: 'utamed.Administrativo', table_name: 'Facultad'},
    {table_schema: 'utamed.Administrativo', table_name: 'Departamento'},
    {table_schema: 'utamed.Usuario', table_name: 'Usuario'},
    // ... ~50 tablas
]
```

Este array se usa en los siguientes pasos.

---

## IV. Paso 2: Análisis de Relaciones

### Objetivo General

Construir un índice global de **todas** las Foreign Keys del sistema ANTES de generar modelos. Esto permite:

1. Detectar relaciones inversas (hasMany/hasOne)
2. Diferenciar entre relaciones 1:1 y 1:N
3. Evitar recalcular FKs múltiples veces

### Proceso de Alto Nivel

```plaintext
Por cada tabla del sistema:
  1. Consultar pg_constraint para obtener sus FKs
  2. Para cada FK:
     - Extraer tabla origen, tabla destino
     - Extraer columnas locales y remotas
     - Verificar si tiene índice UNIQUE (1:1 vs 1:N)
  3. Guardar en $allForeignKeys[tabla_destino][]

Resultado: Array indexado por tabla destino
```

**Ejemplo del resultado:**

```php
$allForeignKeys = [
    'utamed.Administrativo.Facultad' => [
        [
            'source_table' => 'Departamento',
            'source_column' => 'id_facultad',
            'foreign_column' => 'id_facultad',
            'is_unique' => false  // hasMany
        ]
    ],
    'utamed.Usuario.Usuario' => [
        [
            'source_table' => 'Perfil',
            'source_column' => 'id_usuario',
            'foreign_column' => 'id_usuario',
            'is_unique' => true  // hasOne
        ]
    ]
];
```

### Detalle Técnico: Las Queries SQL

#### Query 1: Extracción de Foreign Keys

```php
$fks = DB::select("
        SELECT
            string_agg(att.attname, ',' ORDER BY ordinality) AS column_names,
            min(fn.nspname) AS foreign_table_schema,
            min(fc.relname) AS foreign_table_name,
            string_agg(fatt.attname, ',' ORDER BY ordinality) AS foreign_column_names,
            min(c.relname) as source_table,
            con.oid as constraint_oid
        FROM pg_constraint con
        JOIN pg_class c ON con.conrelid = c.oid
        JOIN pg_namespace n ON c.relnamespace = n.oid
        JOIN LATERAL unnest(con.conkey) WITH ORDINALITY AS u(attnum, ordinality) ON true
        JOIN pg_attribute att ON att.attrelid = c.oid AND att.attnum = u.attnum
        JOIN pg_class fc ON con.confrelid = fc.oid
        JOIN pg_namespace fn ON fc.relnamespace = fn.oid
        JOIN LATERAL unnest(con.confkey) WITH ORDINALITY AS uf(fattnum, ordinality2) ON uf.ordinality2 = u.ordinality
        JOIN pg_attribute fatt ON fatt.attrelid = fc.oid AND fatt.attnum = uf.fattnum
        WHERE con.contype = 'f'
            AND c.relname = ?
            AND n.nspname = ?
        GROUP BY con.oid
    ", [$t->table_name, $t->table_schema]);
```

#### Query 2: Detección de Índices Únicos

```php
$hasUniqueIndex = DB::select("
      SELECT COUNT(*) as count
      FROM pg_index i
      JOIN pg_class c ON i.indrelid = c.oid
      JOIN pg_namespace n ON c.relnamespace = n.oid
      JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS u(attnum, ordinality) ON true
      JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = u.attnum
      WHERE n.nspname = ?
        AND c.relname = ?
        AND i.indisunique = true
      GROUP BY i.indexrelid
      HAVING array_to_string(array_agg(a.attname ORDER BY u.ordinality), ',') = ?
    ", [$t->table_schema, $t->table_name, $fk->column_names]);
```

### Explicación Paso a Paso de las Queries

#### Query 1: Extracción de FKs

1. Consigue las llaves de las tablas origen (donde esta la llave foránea (FK)) y destino (a quien apunta)
2. Joinea las tablas (con `pg_class` y `pg_namespace`)
3. Hace un lateral join para parear las `conkeys` con las `confkeys`
    - Desglosa cada `conkey` (arreglo de llaves) en filas individuales y las concatena a las filas de llave foránea (FK) originales. Usa `WITH ORDINALITY` para enumerar las columnas.

    **Ejemplo:**

    ```
    fk, conkey{1,2}, confkey{3,4}
    ```

    Después del lateral:

    ```
    A. fk, attnum=1, ordinality=1, confkey{3,4}
    B. fk, attnum=2, ordinality=1, confkey{3,4}
    ```

4. Luego joinea con la info del atributo (basándose en la posición de la columna `attnum`)
5. Hace otro lateral join para desglosar la `confkey` y asociarla a la columna correcta por cada columna de la tabla original (usando `ordinality`)

    **Ejemplo:**

    ```
    fk, attnum=1, ordinality=1, confkey{3,4}
    fk, attnum=2, ordinality=1, confkey{3,4}
    ```

    Después del lateral on `uf.ordinality2 = u.ordinality`: (u lateral original, uf segundo lateral)

    ```
    A. fk, attnum=1, ordinality=1, fattnum=3, ordinality=1 (se descarta la otra combinación ord:1 != ord:2)
    B. fk, attnum=2, ordinality=1, fattnum=4, ordinality=2
    ```

6. Joinea de nuevo con la `pg_attribute` para sacar la info de la columna de la segunda pk
7. Usa un where `contype = 'f'` para constraints de llave foránea (FK) solamente. Los '?' son wildcards para extraer la lógica de esquemas/tablas cambiantes
8. Luego agrupa los nombres de las columnas concatenándolos con comas (',') y rescata la referencia a las tablas de origen y destino de la llave foránea (FK). Guarda también la id de la constraint.

#### Query 2: Detección de Índices Únicos

9. Finalmente busca los índices tipo 'unique' dentro de la bd (para diferenciar entre 1:1 y 1:N)

    9.1. Busca info en la tabla `pg_index` y busca los números de las columnas (`attnum`) que tienen índices por constraints unique - Hace un join lateral unnest, con el mismo motivo de aparear los `attnum` de los índices con los de la llave foránea (FK) obtenidos previamente.

10. Hace el mismo where y filtra por `indisunique` (si el índice es por una constraint unique)
11. Agrupa por el índice del índice (en `pg_table`)
12. Filtra la agrupación (having) buscando que se cumpla:

    ```php
    array_to_string(array_agg(a.attname ORDER BY u.ordinality), ',') = $fk->column_names
    ```

    **Que significa:**
    El arreglo de `attnums` (números de columnas) ordenados de la tabla de índice es igual al arreglo de `attnums` (números de columnas) ordenados de la tabla de constraints

    **Que logra que:**
    Encuentre los índices unique que correspondan a llaves foráneas (FK) definidas en la tabla, lo que detecta finalmente una relación 1:1

13. Selecciona 1 por cada instancia que se cumple
14. Guarda finalmente en un arreglo (`$allForeignKeys`) los detalles de las llaves foráneas (FK) encontradas.

---

## V. Configurador de Relaciones

### Propósito

Personalizar nombres de métodos de relaciones (belongsTo, hasMany, hasOne) cuando los autogenerados no son semánticamente claros.

### Formato de Configuración

```php
$relationNames = [
  'utamed.{Schema}.{Tabla}' => [
    '_self' => [                           // ← belongsTo: Relaciones (métodos) EN esta tabla
      '{columna_fk}' => '{nuevo_nombre}',
    ],
    '{OtraTabla}' => [                     // ← hasMany/hasOne: Relaciones (métodos) DESDE otra tabla
      '{columna_fk}' => '{nuevo_nombre}',
    ],
  ],
];
```

### Ejemplo Completo: Usuario_Rol_Asignación

**Problema:**

```php
// BaseUsuarioRolAsignación.php (autogenerado)
public function usuario() { ... }   // FK: id_usuario      ← OK
public function usuario1() { ... }  // FK: asignado_por    ← ❌ Confuso

// BaseUsuario.php (autogenerado)
public function usuarioRolAsignaciones() { ... }   // FK: id_usuario     ← ❌ No descriptivo
public function usuarioRolAsignaciones1() { ... }  // FK: asignado_por   ← ❌ Duplicado
```

**Solución:**

```php
'utamed.Usuario.Usuario_Rol_Asignación' => [
  '_self' => [
    'asignado_por' => 'asignadoPor',  // belongsTo en UsuarioRolAsignación
  ],
  'Usuario' => [
    'id_usuario' => 'asignacionesRolRecibidas',      // hasMany en Usuario
    'asignado_por' => 'asignacionesRolRealizadas',   // hasMany en Usuario
  ],
],
```

**Resultado:**

```php
// BaseUsuarioRolAsignación.php
public function usuario() { ... }         // FK: id_usuario     ← Sin cambios
public function asignadoPor() { ... }     // FK: asignado_por   ← ✅ Renombrado

// BaseUsuario.php
public function asignacionesRolRecibidas() { ... }    // ✅ Renombrado
public function asignacionesRolRealizadas() { ... }   // ✅ Renombrado
```

### Casos de Uso

| Caso                            | Problema                          | Solución                    |
| ------------------------------- | --------------------------------- | --------------------------- |
| Múltiples FKs a misma tabla     | `usuario()`, `usuario1()`         | Usar `_self` para renombrar |
| Relaciones semánticamente vagas | `roles()` (¿creados o asignados?) | Renombrar en ambos lados    |
| Evitar conflictos con pivots    | `roles()` existe dos veces        | Diferenciar claramente      |

---

## VI. Configurador de Tablas Pivot

### ¿Qué es una tabla pivot?

Una tabla que conecta dos (o más) tablas en una relación muchos-a-muchos.

**Ejemplo:**

```plaintext
Estudiante ↔ Inscripcion_Curso ↔ Curso
```

- Un estudiante puede tener muchos cursos
- Un curso puede tener muchos estudiantes
- `Inscripcion_Curso` es la tabla pivot que los conecta

### ¿Por qué configuración manual?

**⚠️ IMPORTANTE:** El generador **NO detecta automáticamente** qué tablas son pivots debido a limitantes técnicas y semánticas.

La decisión de usar `belongsToMany` no depende solo de la estructura, sino de **cómo planeas usar** la relación en tu aplicación.

#### 1. Relaciones asimétricas (sentido en una dirección, no en la otra)

Este es un ejemplo sobre cómo algunas relaciones carecen de sentido semántico en una dirección y, por ende, se preferiría no configurarlas.

```php
// EstadoActividad -> actividades  ✅ Tiene sentido
$estado->actividades;  // "Todas las actividades con este estado"

// Actividad -> estados  ❌ No tiene sentido semántico
$actividad->estados;   // "¿Todos los estados que tiene la actividad?"
```

#### 2. Relaciones semánticamente confusas o complejas

Este es un ejemplo sobre cómo una tabla pivot puede tener múltiples relaciones hacia la misma tabla, causando confusión en los nombres si se autogeneran.

```php
// Usuario -> Rol vía Usuario_Rol_Asignación
$usuario->roles();     // Autogenerado

// Usuario -> Rol vía Usuario_Permiso_Especial
$usuario->roles1();    // ❌ Duplicado confuso
```

La configuración manual existe específicamente para lidiar con estos problemas, porque solo tú sabes cómo necesitas acceder a tus datos.

### Propósito del configurador

Crear relaciones `belongsToMany` altamente configurables para acceder directamente a tablas relacionadas sin necesidad de pasar por la tabla intermedia.

**Sin pivot configurado:**

```php
// Acceso manual (tedioso):
$estudiante->inscripcionCursos()->get()->pluck('curso');
```

**Con pivot configurado:**

```php
// Acceso directo (limpio):
$estudiante->cursos;  // ✅ Automático
```

### ¿Cuándo usar cada nivel de configuración?

El configurador ofrece 4 niveles de control según tu situación:

#### Situación 1: "No me importa cómo queden los nombres"

**Tu caso:**

- Pivot simple con 2 tablas
- No hay conflictos con otras relaciones
- Los nombres autogenerados están bien

**Ejemplo:** Estudiante ↔ Curso (vía Inscripcion_Curso)

**Solución:** Configuración automática completa (nivel A)

---

#### Situación 2: "Tengo múltiples pivots hacia las mismas tablas"

**Tu caso:**

- Varias tablas pivot conectan las mismas entidades
- Autogenera nombres duplicados (`tablaDestino()`, `tablaDestino1()`)
- Necesitas diferenciar el origen de cada relación
- No es necesario renombrar manualmente todas las relaciones

**Ejemplo:**

- Usuario tiene permisos vía `Usuario_Rol_Asignación` → genera `permisos()`
- Usuario tiene permisos vía `Usuario_Permiso_Especial` → genera `permisos1()` ❌ Confuso

**Solución:** Auto-sufijo por tabla pivot (nivel B)  
Genera: `permisosURA()`, `permisosUPE()` ✅

---

#### Situación 3: "Mis relaciones necesitan explicación"

**Tu caso:**

- Los nombres autogenerados no expresan la semántica real
- Quieres nombres descriptivos del negocio
- No todos los métodos necesitan renombrarse

**Ejemplo:**

- `permisos()` → ¿Asignados? ¿Especiales? ¿Heredados?
- Prefieres: `permisosEspeciales()`, `contextosPermisos()`

**Solución:** Nombres personalizados por tabla (nivel C)

---

#### Situación 4: "Mi pivot tiene múltiples relaciones hacia la misma tabla con roles diferentes"

**Tu caso:**

- Tabla pivot tiene 2+ FKs hacia la misma tabla
- Cada FK representa un rol distinto (asignador/recipiente, creador/ejecutor)
- Autogenera `tablaDestino()`, `tablaDestino1()` ❌ sin distinguir los roles de cada relacion

**Ejemplo:** `Usuario_Permiso_Especial`

- `id_usuario_asignador` → quien otorga el permiso
- `id_usuario_recipiente` → quien recibe el permiso
- Ambos apuntan a la tabla `Usuario`

**Solución:** Nombres con FKs específicas (nivel D)  
Genera: `usuariosQueRecibenMisPermisos()`, `usuariosQueAsignanMisPermisos()` ✅

---

### 4 Niveles de Granularidad

#### A) Configuración Automática Completa

**Uso:** Pivots sencillos y autoexplicables

```php
'utamed.Curso.Inscripcion_Curso' => true,
```

**¿Qué genera?**

Para `Estudiante ↔ Inscripcion_Curso ↔ Curso`:

```php
// BaseEstudiante.php
public function cursos() {
    return $this->belongsToMany(Curso::class, 'utamed.Curso.Inscripcion_Curso',
        'id_estudiante', 'id_curso');
}

// BaseCurso.php
public function estudiantes() {
    return $this->belongsToMany(Estudiante::class, 'utamed.Curso.Inscripcion_Curso',
        'id_curso', 'id_estudiante');
}
```

**⚠️ NOTA:** Puede generar conflictos si ya existe `cursos()` por otra relación (ej: hasMany).

#### B) Auto-Sufijo (evitar conflictos)

**Uso:** Cuando hay múltiples relaciones hacia las mismas tablas

```php
'utamed.Usuario.Usuario_Rol_Asignación' => [
  'auto_suffix' => true,
],
```

**¿Qué genera?**

Para `Usuario ↔ Usuario_Rol_Asignación ↔ Rol`:

```php
// BaseUsuario.php
public function rolesURA() {  // ← Agrega sufijo URA (iniciales del pivot)
    return $this->belongsToMany(Rol::class, ...);
}

public function contextosURA() {
    return $this->belongsToMany(Contexto::class, ...);
}
```

**Ventaja:** Evita duplicados como `roles()` y `roles1()` en casos comunes.

**⚠️ NOTA:** Puede seguir generando conflictos si hay varias relaciones que terminan en la misma tabla.

#### C) Nombres Personalizados por Tabla

**Uso:** Control fino sobre nombres de métodos

```php
'utamed.Usuario.Usuario_Permiso_Especial' => [
  'relation_names' => [
    'Usuario' => [
      'Contexto' => 'contextosPermisos',
      'Permiso' => 'permisosEspeciales',
    ],
  ],
],
```

**Se lee:**

- `Usuario.contextosPermisos()` → retorna `Contextos` (a través de Usuario_Permiso_Especial)
- `Usuario.permisosEspeciales()` → retorna `Permisos` (a través de Usuario_Permiso_Especial)

**Uso:**

```php
$usuario->permisosEspeciales;  // Permisos via UPE
$usuario->contextosPermisos;   // Contextos via UPE
```

**Ventaja:** Nombres claros y útiles semánticamente.
**Desventaja:** No permite diferenciar múltiples relaciones a la misma tabla.

#### D) Nombres con FKs Específicas (máximo control)

**Uso:** Pivots con múltiples FKs a la misma tabla (diferentes roles)

**Problema:** `Usuario_Permiso_Especial` tiene 2 FKs a `Usuario`:

- `id_usuario_recipiente` (quien recibe el permiso)
- `id_usuario_asignador` (quien asigna el permiso)

**Autogenerado (confuso):**

```php
public function usuarios() { ... }   // ¿Cuál FK usa?
public function usuarios1() { ... }  // ❌ No semántico
```

**Solución:**

```php
'utamed.Usuario.Usuario_Permiso_Especial' => [
  'relation_names' => [
    'Usuario' => [
      'Usuario' => [
        [
          'method_name' => 'usuariosQueRecibenMisPermisos',
          'local_key' => 'id_usuario_asignador',      // Yo soy el asignador
          'foreign_key' => 'id_usuario_recipiente',   // Ellos son recipientes
        ],
        [
          'method_name' => 'usuariosQueAsignanMisPermisos',
          'local_key' => 'id_usuario_recipiente',     // Yo soy el recipiente
          'foreign_key' => 'id_usuario_asignador',    // Ellos son asignadores
        ],
      ],
      'Permiso' => 'permisosEspeciales',
      'Contexto' => 'contextosPermisos',
    ],
  ],
],
```

**Resultado:**

```php
// BaseUsuario.php
public function usuariosQueRecibenMisPermisos() {
    // Usuarios que reciben permisos que YO asigno
    return $this->belongsToMany(Usuario::class, 'utamed.Usuario.Usuario_Permiso_Especial',
        'id_usuario_asignador', 'id_usuario_recipiente');
}

public function usuariosQueAsignanMisPermisos() {
    // Usuarios que me asignan permisos a MÍ
    return $this->belongsToMany(Usuario::class, 'utamed.Usuario.Usuario_Permiso_Especial',
        'id_usuario_recipiente', 'id_usuario_asignador');
}
```

**Ventaja:** Control total máximo sobre nombres y llaves usadas. Útil para pivots complejos.
**Desventaja:** Configuración más extensa y detallada, puede llegar a confundir.

### Formato General

```php
'utamed.Schema.NombreTabla' => <bool> | [
  'auto_suffix' => <bool, default: false>,
  'relation_names' => [
    'TablaOrigen' => [
      'TablaDestino' => 'nombre_metodo' | [
        [
          'method_name' => 'nombre_metodo',
          'local_key' => 'columna_local',
          'foreign_key' => 'columna_remota',
        ],
      ],
    ],
  ],
]
```

**Notas:**

- `auto_suffix` y `relation_names` se pueden usar juntos
- `relation_names` actúa como **whitelist**: solo genera relaciones explícitamente listadas
- Si `auto_suffix` está activo sin `relation_names`, genera todas las relaciones con sufijo automático
- Si una relación no está en `relation_names` pero `auto_suffix` está activo, se genera con sufijo y **deja de funcionar como whitelist**

### Procesamiento Interno del Configurador

**Flujo:**

1. **Filtrado:** Itera sobre cada tabla y filtra solo las configuradas en `$manualPivotTables`

2. **Extracción de FKs:** Para cada pivot, recupera TODAS sus FKs usando `pg_constraint`
    - Estructura: tabla origen, tabla destino, columnas locales/remotas

3. **Validación:** Verifica que tenga ≥2 FKs válidas
    - Si tiene <2 FKs: Descarta (no puede ser pivot)
    - Si tiene ≥2 FKs: Guarda en `$pivotTables` con metadata

4. **Resultado:** Array `$pivotTables` con información estructurada para generar `belongsToMany`

**Ejemplo de `$pivotTables`:**

```php
$pivotTables = [
    'utamed.Curso.Inscripcion_Curso' => [
        'fks' => [
            'Estudiante' => [
              'schema' => 'utamed.Usuario',
              'table' => 'Estudiante',
              'local_key' => 'id_estudiante',
              'foreign_key' => 'id_estudiante'
            ],
            'Curso' => [
              'schema' => 'utamed.Curso',
              'table' => 'Curso',
              'local_key' => 'id_curso',
              'foreign_key' => 'id_curso'
            ],
        ],
        'config' => ['auto_suffix' => false, 'relation_names' => [...]],
    ],
];
```

### Generación de belongsToMany (Paso 4)

**¿Cuándo se usa `$pivotTables`?**

Durante la generación de modelos (Paso 4), para cada tabla:

1. **Verifica participación:** ¿Esta tabla participa en algún pivot?
2. **Itera otras FKs:** Para cada OTRA tabla conectada en el mismo pivot
3. **Aplica restricciones:** Respeta `relation_names` (whitelist), `auto_suffix`
4. **Determina nombre:** Según prioridad (ver niveles A-D arriba)
5. **Detecta conflictos:** Si ya existe método, agrega sufijo
6. **Genera código:** Crea método `belongsToMany` con parámetros correctos

**Ejemplo de código generado:**

```php
// En BaseEstudiante.php
public function cursos()
{
    return $this->belongsToMany(
        \App\Models\Curso\Curso::class,
        'utamed.Curso.Inscripcion_Curso',  // Tabla pivot
        'id_estudiante',                    // FK local en pivot
        'id_curso'                          // FK remota en pivot
    )->withPivot('fecha_inscripcion', 'estado');  // Columnas extra del pivot
}
```

---

## VII. Generador de Modelos (Paso 4)

### Resumen del Proceso

Para cada tabla detectada en Paso 1:

1. **Preparación:** Extrae schema, nombre, crea directorios
2. **Metadata:** Consulta columnas, PK, timestamps, soft deletes
3. **Relaciones:** Genera belongsTo, hasMany/hasOne, belongsToMany
4. **Generación:** Crea Base Model y Extended Model

### 4.1 Extracción de Metadata

#### Columnas

**Query:**

```sql
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'utamed.Administrativo'
  AND table_name = 'Facultad'
```

**Genera:**

```php
protected $fillable = [
    'nombre_facultad',
    'codigo_facultad',
    // ... excluye PK y timestamps
];
```

#### Primary Key

**Query:**

```sql
SELECT a.attname
FROM pg_index i
JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
WHERE i.indrelid = 'utamed.Administrativo.Facultad'::regclass
  AND i.indisprimary
```

**Genera:**

```php
protected $primaryKey = 'id_facultad';
```

#### Timestamps

**Detección:**

- Si existe `fecha_creacion` → `const CREATED_AT = 'fecha_creacion'`
- Si existe `fecha_actualizacion` → `const UPDATED_AT = 'fecha_actualizacion'`
- Si ninguno existe → `public $timestamps = false`

#### Soft Deletes

**Detección:**

- Si existe `fecha_eliminacion` (timestamp nullable):
    ```php
    use SoftDeletes;
    const DELETED_AT = 'fecha_eliminacion';
    ```

### 4.2 Generación de belongsTo

Para cada FK detectada en esta tabla:

```php
// Departamento tiene FK: id_facultad → Facultad
public function facultad()
{
    return $this->belongsTo(\App\Models\Administrativo\Facultad::class, 'id_facultad', 'id_facultad');
}
```

**Renombrado:** Respeta configuración en `$relationNames['_self']`.

### 4.3 Generación de hasMany / hasOne

Consulta `$allForeignKeys` (construido en Paso 2) para ver qué tablas apuntan a esta.

**hasMany (1:N):**

```php
// Facultad ← Departamento (sin índice unique en FK)
public function departamentos()
{
    return $this->hasMany(\App\Models\Administrativo\Departamento::class, 'id_facultad', 'id_facultad');
}
```

**hasOne (1:1):**

```php
// Usuario ← Perfil (con índice unique en FK)
public function perfil()
{
    return $this->hasOne(\App\Models\Usuario\Perfil::class, 'id_usuario', 'id_usuario');
}
```

**Renombrado:** Respeta configuración en `$relationNames[tabla][columna_fk]`.

### 4.4 Generación de belongsToMany

Si esta tabla participa en un pivot configurado:

```php
// Estudiante ↔ Inscripcion_Curso ↔ Curso
public function cursos()
{
    return $this->belongsToMany(
        \App\Models\Curso\Curso::class,
        'utamed.Curso.Inscripcion_Curso',
        'id_estudiante',
        'id_curso'
    )->withPivot('fecha_inscripcion', 'estado', 'nota_final');
}
```

**withPivot:** Incluye automáticamente columnas extra del pivot (excluyendo FKs y timestamps).

### 4.5 Estructura de Archivos Generada

```
app/Models/
  ├── Base/
  │   ├── Administrativo/
  │   │   ├── BaseFacultad.php      ← REGENERABLE (sobrescribe)
  │   │   └── BaseDepartamento.php
  │   ├── Usuario/
  │   │   └── BaseUsuario.php
  │   └── Curso/
  │       └── BaseCurso.php
  │
  ├── Administrativo/
  │   ├── Facultad.php               ← PRESERVADO (solo crea si no existe)
  │   └── Departamento.php
  ├── Usuario/
  │   └── Usuario.php
  └── Curso/
      └── Curso.php
```

### 4.6 Plantilla de Base Model

```php
<?php

namespace App\Models\Base\Administrativo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ADVERTENCIA: NO EDITAR - Autogenerado
 *
 * Este archivo se regenera automáticamente.
 * Personalizaciones en App\Models\Administrativo\Facultad
 */
abstract class BaseFacultad extends Model
{
    use SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'utamed.Administrativo.Facultad';
    protected $primaryKey = 'id_facultad';
    public $incrementing = true;

    const CREATED_AT = 'fecha_creacion';
    const UPDATED_AT = null;  // No existe en BD
    const DELETED_AT = 'fecha_eliminacion';

    protected $fillable = [
        'nombre_facultad',
        'codigo_facultad',
    ];

    // ========== RELACIONES ==========

    public function departamentos()
    {
        return $this->hasMany(\App\Models\Administrativo\Departamento::class, 'id_facultad', 'id_facultad');
    }

    public function usuarioCreador()
    {
        return $this->belongsTo(\App\Models\Usuario\Usuario::class, 'id_usuario_creacion', 'id_usuario');
    }
}
```

### 4.7 Plantilla de Extended Model

```php
<?php

namespace App\Models\Administrativo;

use App\Models\Base\Administrativo\BaseFacultad;

/**
 * Modelo Facultad
 *
 * Aquí puedes agregar:
 * - Scopes personalizados
 * - Accessors/Mutators
 * - Métodos de negocio
 * - Eventos
 */
class Facultad extends BaseFacultad
{
    // Tus personalizaciones aquí

    // Ejemplo de scope:
    // public function scopeActivas($query) {
    //     return $query->whereNull('fecha_eliminacion');
    // }
}
```

### Variables de Configuración Específicas de BD

En `generate_models.php` hay múltiples variables que definen el comportamiento del generador según tu estructura de base de datos. **Todas estas están definidas al inicio del script (más o menos líneas 140-170)** y pueden adaptarse a otros proyectos o bases de datos PostgreSQL.

#### 1. Rutas de Salida de Modelos

Modifica estas variables si deseas cambiar dónde se generan los modelos. Por defecto, están en `app/Models` y `app/Models/Base`. Los modelos generados usan el namespace `App\\Models` y `App\\Models\\Base`.

```php
// Directorio para Models
$modelDir = app_path(path: 'Models');
// Derivados del base de Models
$baseModelDir = $modelDir . '/Base';

// Namespace base para plantilla de Models
$extendedModelNamespace = 'App\\Models';
$baseModelNamespace = $extendedModelNamespace . '\\Base';
```

---

#### 2. Catálogo y Esquemas PostgreSQL

Configura estas variables para apuntar a tu base de datos específica y los esquemas que deseas incluir en la generación. `$schemaPrefix` usa un patrón LIKE para filtrar esquemas jerárquicos, como el actual (utamed.%).

```php
$catalogName = 'utamed_1ra_fase';    // Nombre del catálogo (BD)
$schemaPrefix = 'utamed.%';          // Patrón LIKE de esquemas a incluir
```

---

#### 3. Columnas de Auditoría (Timestamps y Soft Deletes)

```php
$createdAtColumn = 'fecha_creacion';        // Columna CREATED_AT
$updatedAtColumn = 'fecha_modificacion';    // Columna UPDATED_AT
$softDeleteColumnName = 'fecha_eliminacion'; // Columna DELETED_AT
```

El generador detecta automáticamente estas columnas por cada tabla y configura los modelos

---

#### 4. Columnas Excluidas de `$fillable`

Agrega aquí las columnas que no deseas incluir en el arreglo `$fillable` de los modelos generados. Por defecto, incluye las columnas de auditoría y puedes agregar más según tu esquema.

```php
$notFillableColumns = [
  $createdAtColumn,
  $updatedAtColumn,
  $softDeleteColumnName,
  // Columnas adicionales específicas de tu BD
  'esta_activo'
];
```

**Nota:** La clave primaria (`$primaryKey`) se excluye automáticamente en el código (línea ~915)

---

#### 5. Renombrado de Relaciones

```php
$relationNames = [
  'utamed.Usuario.Rol' => [
    'Usuario' => [
      'id_usuario_autor' => 'rolesCreados',
    ],
  ],
  // ... más configuraciones
];
```

**Estructura:**

```php
[
  'utamed.{Schema}.{Tabla}' => [
    '_self' => [                              // belongsTo: en la misma tabla
      '{columna_fk}' => '{nombre_metodo}',
    ],
    '{OtraTabla}' => [                        // hasMany/hasOne: desde otra tabla
      '{columna_fk}' => '{nombre_metodo}',
    ],
  ],
]
```

Ver sección **V. Configurador de Relaciones** para detalles completos.

---

#### 6. Configuración de Tablas Pivot

```php
$manualPivotTables = [
  // A través de esta tabla pivot, crea belongsToMany entre Asignatura y Plan
  'utamed.Administrativo.Asignacion_Plan' => [
    'relation_names' => [
      // A -> B
      'Asignatura' => [
        'Plan' => 'planes',
      ],
      // B -> A
      'Plan' => [
        'Asignatura' => 'asignaturas',
      ],
    ],
  ],
  // ... más pivots
];
```

**Estructura:**

```php
[
  'utamed.{Schema}.{TablaPivot}' => <bool> | [
    'auto_suffix' => <bool, default: false>,
    'relation_names' => [
      'TablaOrigen' => [
        'TablaDestino' => 'nombre_metodo' | [
          [
            'method_name' => 'nombre_metodo',
            'local_key' => 'columna_local',
            'foreign_key' => 'columna_remota',
          ],
        ],
      ],
    ],
  ],
]
```

Ver sección **VI. Configurador de Tablas Pivot** para detalles completos.

---

### Resumen: Variables Clave a Modificar por BD

| Variable                | Propósito                                  |
| ----------------------- | ------------------------------------------ |
| `$catalogName`          | Nombre de la BD (catálogo PostgreSQL)      |
| `$schemaPrefix`         | Patrón LIKE de esquemas (ej: `utamed.%`)   |
| `$createdAtColumn`      | Nombre de columna CREATED_AT               |
| `$updatedAtColumn`      | Nombre de columna UPDATED_AT               |
| `$softDeleteColumnName` | Nombre de columna DELETED_AT               |
| `$notFillableColumns`   | Columnas excluidas de `$fillable`          |
| `$baseModelDir`         | Directorio de Base Models                  |
| `$modelDir`             | Directorio de Extended Models              |
| `$relationNames`        | Renombrado de relaciones belongsTo/hasMany |
| `$manualPivotTables`    | Configuración de pivots (belongsToMany)    |

---

## Ejecución y Comandos

### Generar Modelos

```bash
# Generación completa
php scripts/generate_models.php

# Dry-run (simula sin crear archivos)
php scripts/generate_models.php --dry-run

# Verbose (muestra tiempos de ejecución)
php scripts/generate_models.php --verbose
```

### Ver Configuración de Pivots

> TODO: ARREGLAR O QUITAR ESTE SCRIPT

```bash
php scripts/show_pivot_config.php
```

### Probar Modelos

```bash
php artisan tinker

>>> $facultad = App\Models\Administrativo\Facultad::first();
>>> $facultad->departamentos;  // hasMany

>>> $estudiante = App\Models\Usuario\Estudiante::first();
>>> $estudiante->cursos;       // belongsToMany
```

---

## Flujo Completo Resumido

```plaintext
1. DETECCIÓN DE TABLAS
   ↓ (information_schema.tables)
   ~50 tablas encontradas

2. PRE-ANÁLISIS DE RELACIONES
   ↓ (pg_constraint + pg_index)
   $allForeignKeys construido

3. PROCESAMIENTO DE PIVOTS
   ↓ ($manualPivotTables)
   $pivotTables construido

4. GENERACIÓN DE MODELOS (bucle)
   ├─ Extrae metadata (columnas, PK, timestamps)
   ├─ Genera belongsTo (FKs de esta tabla)
   ├─ Genera hasMany/hasOne (FKs hacia esta tabla)
   ├─ Genera belongsToMany (pivots configurados)
   ├─ Crea Base Model (sobrescribe)
   └─ Crea Extended Model (preserva)

RESULTADO:
   ~100 archivos PHP generados
   Listo para usar en Laravel
```

---

## Próximos Pasos

Después de generar modelos:

1. **Revisar relaciones generadas** - Verificar que los nombres sean semánticos
2. **Agregar scopes personalizados** - En modelos extendidos
3. **Crear Form Requests** - Para validación
4. **Escribir tests** - Asegurar funcionalidad
5. **Regenerar cuando cambies BD** - Mantendrá personalizaciones

---

## Solución de Problemas

### Relaciones duplicadas (roles(), roles1())

**Causa:** Múltiples FKs a la misma tabla sin configuración.

**Solución:** Usa `$relationNames` para renombrar.

### Pivots no generan belongsToMany

**Causa:** Tabla no está en `$manualPivotTables`.

**Solución:** Agrega configuración explícita.

### Modelo no detecta soft deletes

**Causa:** Columna no se llama `fecha_eliminacion`.

**Solución:** Ajusta lógica de detección en script o renombra columna.

### Errores al regenerar

**Causa:** Archivos Base en uso o permisos.

**Solución:**

```bash
composer dump-autoload
php artisan cache:clear
```
