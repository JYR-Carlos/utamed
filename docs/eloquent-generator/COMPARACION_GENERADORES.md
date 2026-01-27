# Comparación: Generadores de Modelos

## 🎯 Probado: krlove/eloquent-model-generator

### ❌ Resultado: NO Compatible

```
composer require --dev krlove/eloquent-model-generator
```

**Error:**

```
requires laravel/framework ^5.0
conflicts with your root composer.json require (^12.34.0)
```

**Conclusión:** No es compatible con Laravel 12. Última actualización hace 3+ años.

---

## 🎯 Probado: reliese/laravel

### ⚠️ Resultado: NO Funciona con Nuestra Estructura

**Ya instalado:**

```
reliese/laravel 1.4.0
```

**Comando probado:**

```bash
php artisan code:models --table="Facultad"
```

**Error:**

```
InvalidArgumentException
Table [Facultad] does not belong to schema [utamed_1ra_fase]
```

**Problema:** No puede manejar esquemas PostgreSQL con puntos como `utamed.Administrativo`.

**Comando general probado:**

```bash
php artisan code:models
```

**Resultado:**

```
Check out your models for utamed_1ra_fase
```

Dice que lo hizo pero **no genera ningún archivo**.

---

## ✅ Solución: Generador Personalizado

### Por Qué Funciona

1. **Usa catálogo nativo de PostgreSQL** en lugar de `information_schema`
2. **Maneja esquemas con puntos** (`utamed.Administrativo`)
3. **Configura `search_path`** correctamente
4. **Genera modelos específicos** para la estructura de utamed

### Comparación de Queries

**reliese/laravel (NO funciona):**

```sql
-- Usa information_schema que no maneja bien esquemas con puntos
SELECT * FROM information_schema.tables
WHERE table_schema = 'utamed.Administrativo'
```

**Nuestro generador (✅ funciona):**

```sql
-- Usa catálogo pg_* nativo
SELECT n.nspname, c.relname
FROM pg_class c
JOIN pg_namespace n ON c.relnamespace = n.oid
WHERE n.nspname = 'utamed.Administrativo'
```

---

## 📊 Tabla Comparativa

| Característica             | krlove | reliese | **Personalizado** |
| -------------------------- | ------ | ------- | ----------------- |
| Laravel 12                 | ❌     | ✅      | ✅                |
| Esquemas con punto         | ❌     | ❌      | ✅                |
| Detecta FK                 | ✅\*   | ✅\*    | ✅                |
| Genera relaciones          | ✅\*   | ✅\*    | ✅                |
| Detecta PK                 | ✅\*   | ✅\*    | ✅                |
| Timestamps custom          | ✅\*   | ✅\*    | ✅                |
| Scopes                     | ❌     | ❌      | ✅                |
| Organizados por esquema    | ❌     | ❌      | ✅                |
| Mantiene personalizaciones | ❌     | ❌      | ⚠️\*\*            |

_\* En teoría sí, pero no funciona con nuestra estructura_  
_\*\* Sobrescribe todo, pero puedes personalizar el script_

---

## 🏆 Recomendación Final

**Usar el generador personalizado** (`generate_models.php`) porque:

1. ✅ **Funciona** con tu estructura específica
2. ✅ **Es mantenible** - puedes modificar el script
3. ✅ **Genera todo lo necesario**
4. ✅ **No requiere dependencias adicionales**
5. ✅ **Está documentado** para tu proyecto

---

## 🔧 Alternativa: Generar Base Models

Si quieres mantener personalizaciones, modifica `generate_models.php` para generar:

```
app/Models/Base/
  BaseAsignatura.php  <- Generado automáticamente
app/Models/Administrativo/
  Asignatura.php      <- Extiende BaseAsignatura, personalizas aquí
```

**Ventaja:** Regeneras `Base` sin perder personalizaciones.

**Ejemplo de modificación en el script:**

```php
// Generar en Base/
$modelDir = __DIR__ . "/app/Models/Base/{$schemaName}";

// Clase como BaseAsignatura
$className = 'Base' . Str::studly($tableName);

// En el modelo final:
class Asignatura extends BaseAsignatura
{
    // Tus personalizaciones aquí
}
```

---

## 📝 Conclusión

**No instales librerías adicionales.** El generador personalizado es la mejor solución para este proyecto específico con esquemas PostgreSQL anidados.

Si necesitas funcionalidad adicional (validación, observers, etc.), usa las herramientas nativas de Laravel en lugar de depender de generadores automáticos.
