# Generador de Modelos Eloquent

Script personalizado para generar modelos Laravel desde PostgreSQL con esquemas anidados (`utamed.*`).

## ¿Por qué un generador propio?

Las librerías existentes (`reliese/laravel`, `krlove/eloquent-model-generator`) no soportan esquemas PostgreSQL con puntos en el nombre (ej: `utamed.Administrativo`). Nuestro generador usa los catálogos nativos de PostgreSQL (`pg_constraint`, `pg_index`, `pg_class`) para manejar esta estructura correctamente.

---

## Scripts Disponibles

| Script                    | Propósito                                 |
| ------------------------- | ----------------------------------------- |
| `generate_models.php`     | **Principal** - Genera todos los modelos  |
| `detect_pivot_tables.php` | Detecta tablas pivot candidatas en la BD  |
| `show_pivot_config.php`   | Muestra la configuración actual de pivots |

---

## Uso Básico

```powershell
# Desde la raíz del proyecto
php generate_models.php
```

### Salida

```
🚀 Generando modelos desde PostgreSQL...
Encontradas 30 tablas
📊 Analizando relaciones inversas...
✓ Relaciones inversas analizadas
🔗 Cargando tablas pivot...
  ✓ utamed.Curso.Inscripcion_Curso: Curso ↔ Estudiante (2 FKs)
Generando: Administrativo\Facultad <- utamed.Administrativo.Facultad
...
✅ Modelos generados exitosamente
```

---

## Estructura de Modelos Generada

```
app/Models/
├── Base/                          ← Regenerables (NO editar)
│   ├── Administrativo/
│   │   └── BaseFacultad.php
│   └── Usuario/
│       └── BaseUsuario.php
├── Administrativo/                ← Personalizables (editar aquí)
│   └── Facultad.php
└── Usuario/
    └── Usuario.php
```

- **Base Models**: Se regeneran automáticamente. Contienen todo el código generado.
- **Extended Models**: Solo se crean si no existen. Aquí agregas tu código personalizado.

---

## Configuración

### Tablas Pivot (`$manualPivotTables`)

Define qué tablas son pivots para generar relaciones `belongsToMany`:

```php
$manualPivotTables = [
  // Formato simple: auto-detecta todas las FKs
  'utamed.Curso.Inscripcion_Curso' => true,

  // Formato avanzado: control fino
  'utamed.Usuario.Usuario_Rol_Asignación' => [
    'auto_suffix' => true,        // Agrega sufijo (URA) para evitar conflictos
    'tables' => ['Usuario', 'Rol'], // Solo genera relaciones con estas tablas
  ],
];
```

#### Opciones avanzadas

| Opción           | Descripción                                             |
| ---------------- | ------------------------------------------------------- |
| `true`           | Auto-detecta todas las FKs                              |
| `tables`         | Lista blanca de tablas a incluir                        |
| `exclude_tables` | Lista negra de tablas a excluir                         |
| `auto_suffix`    | Agrega iniciales del pivot al nombre (ej: `rolesURA()`) |
| `relation_names` | Renombrado personalizado por relación                   |

### Renombrado de Relaciones (`$relationNames`)

Renombra métodos `belongsTo` y `hasMany/hasOne` cuando los nombres automáticos son confusos:

```php
$relationNames = [
  'utamed.Usuario.Usuario_Rol_Asignación' => [
    // belongsTo: renombra métodos EN esta tabla
    '_self' => [
      'asignado_por' => 'asignadoPor',  // usuario1() → asignadoPor()
    ],
    // hasMany: renombra métodos en Usuario que apuntan a esta tabla
    'Usuario' => [
      'id_usuario' => 'asignacionesRolRecibidas',
      'asignado_por' => 'asignacionesRolRealizadas',
    ],
  ],
];
```

---

## Conceptos Clave

### Relaciones Directas vs Inversas

Cuando existe una FK, el generador crea **dos** relaciones:

| Tipo        | Método        | Ubicación           | Ejemplo                     |
| ----------- | ------------- | ------------------- | --------------------------- |
| **Directa** | `belongsTo()` | Modelo con la FK    | `Departamento->facultad()`  |
| **Inversa** | `hasMany()`   | Modelo referenciado | `Facultad->departamentos()` |

### Detección hasMany vs hasOne

- Si la FK tiene índice `UNIQUE`: genera `hasOne()`
- Si no tiene `UNIQUE`: genera `hasMany()`

### Pivots con Múltiples FKs

Un pivot con 3 FKs genera 6 relaciones (todas las combinaciones A↔B, A↔C, B↔C):

```
Usuario_Rol_Contexto (3 FKs)
  → Usuario->roles(), Usuario->contextos()
  → Rol->usuarios(), Rol->contextos()
  → Contexto->usuarios(), Contexto->roles()
```

Usa `tables` para limitar qué combinaciones generar.

### Sufijos Automáticos (`auto_suffix`)

Cuando tienes conflictos de nombres (ej: `hasMany(Rol)` y `belongsToMany(Rol)`):

```php
'auto_suffix' => true  // Usuario_Rol_Asignación → URA
```

Genera: `roles()` (hasMany) + `rolesURA()` (belongsToMany)

---

## Scripts de Apoyo

### detect_pivot_tables.php

Escanea la BD y detecta tablas que cumplen criterios de pivot:

- Tiene 2+ FKs
- PK compuesta por las FKs, o índice UNIQUE sobre ellas

```powershell
php detect_pivot_tables.php
```

Muestra configuración sugerida para copiar/pegar en `$manualPivotTables`.

### show_pivot_config.php

Muestra un resumen de la configuración actual del generador:

```powershell
php show_pivot_config.php
```

---

## Verificar Modelos

```powershell
php artisan tinker
```

```php
\App\Models\Administrativo\Facultad::count();
\App\Models\Administrativo\Facultad::active()->get();
$facultad = \App\Models\Administrativo\Facultad::with('departamentos')->first();
```

---

## Funcionalidades Generadas

- ✅ `$fillable` automático (excluye PK y timestamps)
- ✅ `$casts` para booleanos (`esta_activo`)
- ✅ Timestamps personalizados (`fecha_creacion`)
- ✅ `belongsTo()` desde FKs
- ✅ `hasMany()`/`hasOne()` inversas
- ✅ `belongsToMany()` desde pivots configurados
- ✅ `withPivot()` para columnas adicionales en pivots
- ✅ Scope `active()` para filtrar por `esta_activo`
- ✅ Pluralización en español

---

## Regenerar Modelos

```powershell
php generate_models.php
```

⚠️ **Los Base Models se sobrescriben.** Los Extended Models se preservan.
