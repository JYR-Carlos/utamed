# Changelog - Generador de Modelos Eloquent

## v3.0 - Enero 2026

### Funcionalidades

- **Patrón Base Models**: Separación entre modelos regenerables (`Base/`) y personalizables
- **Detección automática de relaciones inversas**: `hasMany()` y `hasOne()` generados automáticamente
- **Detección hasOne vs hasMany**: Usa índices `UNIQUE` para determinar cardinalidad
- **Soporte pivots con N FKs**: Soporta pivots con 2, 3, 4+ foreign keys
- **Sufijos automáticos** (`auto_suffix`): Evita conflictos de nombres con iniciales del pivot
- **Renombrado unificado** (`$relationNames`): Un solo lugar para renombrar `belongsTo` y `hasMany/hasOne`
- **Pluralización en español**: Reglas para -ción/-sión, -z, vocales con tilde
- **`withPivot()` automático**: Detecta columnas adicionales en tablas pivot

### Scripts

| Script                    | Descripción                          |
| ------------------------- | ------------------------------------ |
| `generate_models.php`     | Generador principal                  |
| `detect_pivot_tables.php` | Detector de tablas pivot candidatas  |
| `show_pivot_config.php`   | Visualizador de configuración actual |

### Documentación

| Archivo                      | Contenido                          |
| ---------------------------- | ---------------------------------- |
| `README.md`                  | Guía principal consolidada         |
| `CHANGELOG.md`               | Este archivo                       |
| `COMPARACION_GENERADORES.md` | Justificación del generador propio |

### Consideraciones Futuras

- [ ] Comando Artisan (`php artisan models:generate`)
- [ ] Detección automática de pivots (sin configuración manual)
- [ ] Soporte para relaciones polimórficas
- [ ] Validación de configuración con mensajes de error claros
- [ ] Modo dry-run para preview sin generar archivos

---

## v2.0 - Enero 2026

- Soporte inicial para tablas pivot (`belongsToMany`)
- Configuración manual de pivots en `$manualPivotTables`
- Scripts de detección y visualización de pivots

---

## v1.0 - Enero 2026

- Generador inicial con soporte para esquemas PostgreSQL anidados
- Detección de columnas, PKs y FKs desde catálogos nativos
- Generación de `belongsTo()` automática
- Timestamps personalizados (`fecha_creacion`)
- Scope `active()` para `esta_activo`

#### 3. **Detección de Conflictos**

El generador ahora detecta automáticamente cuando:

- Un `belongsToMany()` colisiona con `hasMany()` o `hasOne()`
- Múltiples pivots generan el mismo nombre de método
- Se necesita diferenciación

**Antes:**

```php
// BaseUsuario.php
public function roles() { ... }  // hasMany
public function roles() { ... }  // belongsToMany ← ERROR!
```

**Después (con auto_suffix):**

```php
public function roles() { ... }      // hasMany (sin cambios)
public function rolesURA() { ... }   // belongsToMany (sin conflicto)
```

---

#### 4. **Configuración Híbrida**

Combina lo mejor de ambos mundos:

```php
'utamed.Usuario.Usuario_Rol_Asignación' => [
  'auto_suffix' => true,               // Para la mayoría
  'relation_names' => [                // Personaliza solo las importantes
    'Usuario' => [
      'Rol' => 'rolesAsignados',
    ],
  ],
],
```

**Genera:**

- `rolesAsignados()` - Personalizado
- `contextosURA()` - Auto sufijo
- `usuariosURA()` - Auto sufijo

---

### 🐛 Correcciones

1. **Métodos duplicados eliminados**
    - Antes: `roles()`, `roles()` (error fatal)
    - Ahora: `roles()`, `rolesURA()` (sin conflicto)

2. **Nombres no descriptivos mejorados**
    - Antes: `contextos()`, `contextos1()`, `contextos2()`
    - Ahora: `contextosURA()`, `contextosUPE()` (o personalizados)

3. **Conflictos entre relaciones**
    - Ahora verifica contra `hasMany`, `hasOne`, `belongsTo` existentes
    - Agrega sufijos solo cuando es necesario

---

### 📝 Documentación Nueva

1. **GUIA_PIVOTS.md** actualizada con:
    - Ejemplos de configuración híbrida
    - Caso real de Usuario con múltiples pivots
    - Comparación de estrategias de nomenclatura

2. **EJEMPLO_USUARIO_PIVOTS.md** (NUEVO)
    - Tutorial paso a paso para caso complejo
    - 3 soluciones comparadas
    - Código generado completo
    - Mejores prácticas

3. **show_pivot_config.php** mejorado
    - Muestra configuraciones auto_suffix
    - Muestra configuraciones custom_names
    - Ejemplos de uso mejorados

---

### 🔄 Cambios de Comportamiento

#### Antes (v2.0)

```php
// Sin configuración especial
$manualPivotTables = [
  'utamed.Usuario.Usuario_Rol_Asignación' => true,
];

// Generaba:
public function roles() { ... }     // hasMany
public function roles() { ... }     // ERROR: duplicado
public function contextos() { ... }
public function contextos1() { ... }  // No descriptivo
```

#### Ahora (v2.1)

```php
// Opción 1: Auto sufijo (rápido)
$manualPivotTables = [
  'utamed.Usuario.Usuario_Rol_Asignación' => [
    'auto_suffix' => true,
  ],
];

// Genera:
public function roles() { ... }       // hasMany (sin cambios)
public function rolesURA() { ... }    // belongsToMany (sin conflicto)
public function contextosURA() { ... } // Descriptivo

// Opción 2: Personalizado (semántico)
$manualPivotTables = [
  'utamed.Usuario.Usuario_Rol_Asignación' => [
    'relation_names' => [
      'Usuario' => [
        'Rol' => 'rolesAsignados',
        'Contexto' => 'contextosRol',
      ],
    ],
  ],
];

// Genera:
public function roles() { ... }          // hasMany (sin cambios)
public function rolesAsignados() { ... } // belongsToMany personalizado
public function contextosRol() { ... }   // Muy descriptivo
```

---

### 🎯 Impacto en Código Existente

#### ⚠️ BREAKING CHANGES

Si ya tenías pivots configurados con `true`:

**Antes:**

```php
'utamed.Usuario.Usuario_Rol_Asignación' => true,
```

Generaba métodos con conflictos y nombres genéricos.

**Ahora:**

- Mismo comportamiento por defecto
- Pero **RECOMENDADO** agregar `auto_suffix: true` o `relation_names`

#### ✅ Retrocompatibilidad

La configuración `=> true` sigue funcionando igual, pero ahora:

1. Detecta conflictos y avisa
2. Genera nombres únicos automáticamente (agrega números si es necesario)
3. No rompe código existente

---

### 📊 Ejemplos de Migración

#### Caso 1: Tienes conflictos

**Tu código actual:**

```php
$manualPivotTables = [
  'utamed.Usuario.Usuario_Rol_Asignación' => true,
  'utamed.Usuario.Usuario_Permiso_Especial' => true,
];
```

**Problema:** Genera `roles()` duplicado, `contextos()` y `contextos1()`

**Solución rápida (2 minutos):**

```php
$manualPivotTables = [
  'utamed.Usuario.Usuario_Rol_Asignación' => ['auto_suffix' => true],
  'utamed.Usuario.Usuario_Permiso_Especial' => ['auto_suffix' => true],
];
```

**Solución óptima (10 minutos):**

```php
$manualPivotTables = [
  'utamed.Usuario.Usuario_Rol_Asignación' => [
    'auto_suffix' => true,
    'relation_names' => [
      'Usuario' => ['Rol' => 'rolesAsignados'],
    ],
  ],
  'utamed.Usuario.Usuario_Permiso_Especial' => [
    'relation_names' => [
      'Usuario' => [
        'Permiso' => 'permisosEspeciales',
        'Contexto' => 'contextosPermiso',
      ],
    ],
  ],
];
```

---

#### Caso 2: No tienes conflictos

**Tu código actual funciona bien:**

```php
$manualPivotTables = [
  'utamed.Curso.Inscripcion_Curso' => true,
];
```

**Acción:** Ninguna necesaria, sigue funcionando igual.

---

### 🚀 Próximos Pasos Recomendados

1. **Revisa tus modelos Base generados**

    ```bash
    grep -r "public function" app/Models/Base/Usuario/
    ```

2. **Busca duplicados**

    ```bash
    grep -r "public function roles()" app/Models/Base/
    ```

3. **Si encuentras conflictos:**
    - Agrega `'auto_suffix' => true` como mínimo
    - O personaliza con `'relation_names'`

4. **Regenera modelos**

    ```bash
    php generate_models.php
    ```

5. **Prueba en Tinker**

    ```bash
    php artisan tinker
    > $usuario = Usuario::first();
    > $usuario->rolesAsignados;  // Verifica nuevos nombres
    ```

6. **Actualiza controladores/servicios** si usabas relaciones con conflictos

---

### 📚 Recursos

- [GUIA_PIVOTS.md](GUIA_PIVOTS.md) - Guía completa
- [EJEMPLO_USUARIO_PIVOTS.md](EJEMPLO_USUARIO_PIVOTS.md) - Caso de uso real
- `php show_pivot_config.php` - Ver configuración actual
- `php detect_pivot_tables.php` - Detectar pivots automáticamente

---

### 💬 Feedback

¿Problemas o sugerencias? Abre un issue o consulta la documentación.

---

## Versiones Anteriores

### v2.0 (26 Enero 2026)

- Soporte para pivots con N FKs
- Configuración avanzada básica
- Primera versión con filtros

### v1.0

- Soporte básico para pivots 2 FKs
- Sin detección de conflictos
