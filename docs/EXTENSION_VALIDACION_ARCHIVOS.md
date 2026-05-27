# Guía de Extensión: Crear Nuevos FormRequests para Tipos de Archivo

**Archivo de referencia**: Extensión del sistema de validación de archivos
**Versión**: 1.0
**Última actualización**: Abril 2026

---

## Introducción

El sistema de validación de archivos de UTAMED está diseñado para ser altamente extensible. Esta guía explica cómo:

1. **Crear nuevos tipos de archivo** en `config/files.php`
2. **Crear FormRequests especializados** para esos tipos
3. **Usar validadores personalizados** con `FileValidationHelpers`
4. **Integrar con el sistema de contextos** de UTAMED

---

## Parte 1: Crear un Nuevo Tipo de Archivo

### Paso 1: Definir en `config/files.php`

Abre `config/files.php` y agrega una nueva entrada en el array retornado:

```php
'mi_tipo_personalizado' => [
    'extensions' => explode(',', env(
        'FILES_MI_TIPO_EXTENSIONS',
        'ext1,ext2,ext3'  // Extensiones permitidas
    )),
    'mimes' => explode(',', env(
        'FILES_MI_TIPO_MIMES',
        'application/type1,application/type2'  // MIME types
    )),
    'max_size' => (int) env('FILES_MI_TIPO_MAX_SIZE', 52428800),  // Tamaño máximo en bytes
    'description' => 'Descripción de mi tipo personalizado',
],
```

### Paso 2: Agregar Variables al `.env`

En `.env` y `.env.example`, agrega:

```bash
# Mi Tipo Personalizado
FILES_MI_TIPO_EXTENSIONS=ext1,ext2,ext3
FILES_MI_TIPO_MIMES=application/type1,application/type2
FILES_MI_TIPO_MAX_SIZE=52428800
```

### Paso 3: Actualizar Aliases (Opcional)

Si deseas que las extensiones se mapeen automáticamente a tu tipo, agrega en `config/files.php`:

```php
'aliases' => [
    'ext1' => 'mi_tipo_personalizado',
    'ext2' => 'mi_tipo_personalizado',
    // ...
],
```

---

## Parte 2: Crear un FormRequest Personalizado

### Opción A: Crear desde Cero

Crea un archivo `app/Http/Requests/Archive/MyCustomTypeRequest.php`:

```php
<?php

namespace App\Http\Requests\Archive;

use Illuminate\Validation\Rule;

/**
 * MyCustomTypeRequest
 *
 * Validación para mi tipo personalizado.
 */
class MyCustomTypeRequest extends BaseArchiveRequest
{
    // Especificar el tipo que acepta este request
    protected string $fileType = 'mi_tipo_personalizado';

    /**
     * Reglas adicionales específicas para mi tipo.
     */
    protected function additionalRules(): array
    {
        return [
            'campo_personalizado_1' => 'required|string|max:255',
            'campo_personalizado_2' => 'nullable|integer',
            'estado' => [
                'nullable',
                Rule::in(['draft', 'published', 'archived']),
            ],
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    protected function customMessages(): array
    {
        return [
            'campo_personalizado_1.required' => 'Este campo es requerido.',
            'campo_personalizado_1.max' => 'No puede exceder 255 caracteres.',
            'estado.in' => 'El estado debe ser: draft, published, o archived.',
        ];
    }

    /**
     * Nombres de atributos personalizados.
     */
    protected function customAttributes(): array
    {
        return [
            'campo_personalizado_1' => 'nombre del campo',
            'campo_personalizado_2' => 'identificador',
        ];
    }
}
```

### Opción B: Heredar de un Type Existente

Si tu tipo es similar a uno existente, hereda de su request:

```php
<?php

namespace App\Http\Requests\Archive;

/**
 * MyVariationOfVideoRequest
 *
 * Validación para una variación de videos (ej: solo cortos, < 5min).
 */
class MyVariationOfVideoRequest extends VideoRequest
{
    protected string $fileType = 'mi_variacion_video';

    protected function additionalRules(): array
    {
        // Combinar reglas del padre con nuevas reglas
        return array_merge(parent::additionalRules(), [
            'duracion_maxima_minutos' => 'required|integer|max:5',
        ]);
    }
}
```

---

## Parte 3: Usar el Nuevo FormRequest

### En un Controlador

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Archive\MyCustomTypeRequest;
use App\Services\Archive\ActivitySubmissionArchiveService;

class MyCustomArchiveController extends Controller
{
    public function store(MyCustomTypeRequest $request)
    {
        // El FormRequest ya validó automáticamente
        $file = $request->getFile();
        $contexto = $request->getContexto();

        // Usar el servicio de almacenamiento
        $service = new ActivitySubmissionArchiveService();
        $archivo = $service->processAndStore($file, $contexto);

        return response()->json([
            'success' => true,
            'uuid' => $archivo->uuid_archivo,
            'tipo' => $request->input('campo_personalizado_1'),
        ]);
    }
}
```

### En una Ruta

```php
Route::post('/mi-tipo/upload', [MyCustomArchiveController::class, 'store'])
    ->middleware('auth')
    ->name('mi_tipo.upload');
```

---

## Parte 4: Usar FileValidationHelpers

El helper `FileValidationHelpers` ofrece métodos útiles para validación manual:

### Obtener Configuración

```php
use App\Support\FileValidationHelpers;

// Obtener todas las extensiones permitidas para un tipo
$extensions = FileValidationHelpers::getAllowedExtensions('mi_tipo_personalizado');
// Retorna: ['ext1', 'ext2', 'ext3']

// Obtener MIME types permitidos
$mimes = FileValidationHelpers::getAllowedMimes('mi_tipo_personalizado');
// Retorna: ['application/type1', 'application/type2']

// Obtener tamaño máximo
$maxSize = FileValidationHelpers::getMaxFileSize('mi_tipo_personalizado');
// Retorna: 52428800 (bytes)

// Obtener descripción
$desc = FileValidationHelpers::getFileTypeDescription('mi_tipo_personalizado');
// Retorna: "Descripción de mi tipo personalizado"
```

### Detectar Tipo por Extensión

```php
$extension = 'ext1';
$detectedType = FileValidationHelpers::detectFileTypeByExtension($extension);
// Retorna: 'mi_tipo_personalizado' (si está en aliases)
```

### Validar Archivo Manualmente

```php
$file = $request->file('archivo');
$validation = FileValidationHelpers::validateFile($file, 'mi_tipo_personalizado');

if (!$validation['valid']) {
    foreach ($validation['errors'] as $error) {
        // Manejar error
    }
}
```

### Obtener FormRequest Recomendado

```php
$requestClass = FileValidationHelpers::getFormRequestClass('mi_tipo_personalizado');
// Retorna: "App\Http\Requests\Archive\MyCustomTypeRequest"
```

---

## Parte 5: Ejemplos Prácticos

### Ejemplo 1: Tipo de Archivo para Códigos Fuente

**En `config/files.php`**:

```php
'source_code' => [
    'extensions' => explode(',', env(
        'FILES_SOURCE_CODE_EXTENSIONS',
        'php,js,py,java,cpp,c,cs,rb,go,rs,ts,jsx,tsx,vue,html,css,sql'
    )),
    'mimes' => explode(',', env(
        'FILES_SOURCE_CODE_MIMES',
        'text/plain,application/x-php,application/javascript,text/x-python'
    )),
    'max_size' => (int) env('FILES_SOURCE_CODE_MAX_SIZE', 10485760), // 10MB
    'description' => 'Archivos de código fuente',
],
```

**En `.env.example`**:

```bash
FILES_SOURCE_CODE_EXTENSIONS=php,js,py,java,cpp,c,cs,rb,go,rs,ts,jsx,tsx,vue,html,css,sql
FILES_SOURCE_CODE_MIMES=text/plain,application/x-php,application/javascript,text/x-python
FILES_SOURCE_CODE_MAX_SIZE=10485760
```

**FormRequest**:

```php
<?php

namespace App\Http\Requests\Archive;

use Illuminate\Validation\Rule;

class SourceCodeRequest extends BaseArchiveRequest
{
    protected string $fileType = 'source_code';

    protected function additionalRules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'lenguaje' => [
                'required',
                Rule::in(['php', 'javascript', 'python', 'java', 'cpp', 'other']),
            ],
            'descripcion' => 'nullable|string|max:2000',
            'lineas_codigo' => 'nullable|integer|min:1',
            'licencia' => 'nullable|string|max:50',
        ];
    }

    protected function customMessages(): array
    {
        return [
            'lenguaje.required' => 'Debes especificar el lenguaje de programación.',
            'lineas_codigo.min' => 'El código debe tener al menos 1 línea.',
        ];
    }
}
```

---

### Ejemplo 2: Tipo de Archivo para 3D Models

**En `config/files.php`**:

```php
'model_3d' => [
    'extensions' => explode(',', env(
        'FILES_MODEL_3D_EXTENSIONS',
        'obj,fbx,gltf,glb,usdz,ply,stl,blend,maya,max'
    )),
    'mimes' => explode(',', env(
        'FILES_MODEL_3D_MIMES',
        'model/obj,model/gltf+json,model/gltf-binary,model/stl,application/octet-stream'
    )),
    'max_size' => (int) env('FILES_MODEL_3D_MAX_SIZE', 524288000), // 500MB
    'description' => 'Modelos 3D',
],
```

**FormRequest**:

```php
<?php

namespace App\Http\Requests\Archive;

use Illuminate\Validation\Rule;

class Model3DRequest extends BaseArchiveRequest
{
    protected string $fileType = 'model_3d';

    protected function additionalRules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'formato_3d' => [
                'required',
                Rule::in(['obj', 'fbx', 'gltf', 'glb', 'usdz', 'ply', 'stl']),
            ],
            'autor' => 'nullable|string|max:255',
            'triangulos' => 'nullable|integer|min:1',
            'texturas_incluidas' => 'nullable|boolean',
            'rigged' => 'nullable|boolean',
            'licencia_uso' => 'nullable|string|max:255',
        ];
    }
}
```

---

### Ejemplo 3: Tipo de Archivo con Validación Dinámica

```php
<?php

namespace App\Http\Requests\Archive;

class DynamicValidationRequest extends BaseArchiveRequest
{
    protected string $fileType = 'document';

    protected function additionalRules(): array
    {
        // Las reglas cambian según el tipo de documento
        $tipo = $this->input('tipo_documento');

        $rules = [
            'tipo_documento' => 'required|in:contrato,informe,propuesta',
        ];

        if ($tipo === 'contrato') {
            $rules['fecha_vencimiento'] = 'required|date|after:today';
            $rules['partes_involucradas'] = 'required|string|max:500';
        } elseif ($tipo === 'informe') {
            $rules['numero_paginas'] = 'required|integer|min:1';
            $rules['departamento'] = 'required|string|max:100';
        } elseif ($tipo === 'propuesta') {
            $rules['monto'] = 'required|numeric|min:0';
            $rules['vigencia_dias'] = 'required|integer|min:1|max:365';
        }

        return $rules;
    }
}
```

---

## Parte 6: Validación de FormRequest Personalizado

### Test Unitario

```php
<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Archive\MyCustomTypeRequest;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MyCustomTypeRequestTest extends TestCase
{
    public function test_validates_required_fields()
    {
        $request = new MyCustomTypeRequest();

        $data = [
            'archivo' => null,
            'id_contexto' => null,
            'campo_personalizado_1' => null,
        ];

        // Aquí va lógica de validación
        $this->assertFalse($request->validator($data)->passes());
    }

    public function test_accepts_valid_file()
    {
        $file = UploadedFile::fake()->create('test.ext1', 100);

        $request = new MyCustomTypeRequest();
        // Validar que pasa
    }
}
```

---

## Checklist de Implementación

- [ ] Definir nuevo tipo en `config/files.php`
- [ ] Agregar variables al `.env` y `.env.example`
- [ ] Agregar aliases si es necesario
- [ ] Crear FormRequest extendiendo `BaseArchiveRequest`
- [ ] Implementar `additionalRules()`
- [ ] Implementar `customMessages()` (opcional)
- [ ] Implementar `customAttributes()` (opcional)
- [ ] Crear controlador que use el nuevo FormRequest
- [ ] Agregar rutas en `routes/web.php`
- [ ] Crear tests unitarios
- [ ] Documentar en comentarios del código
- [ ] Probar manualmente con archivos válidos e inválidos

---

## Troubleshooting

### "Type 'xxx' not found in files config"

**Solución**: Asegúrate de que la clave coincida exactamente en `config/files.php`.

### "File validation failed"

**Solución**: Verifica que las extensiones y MIME types en `.env` coincidan con los del archivo real.

### "Custom validation rule not working"

**Solución**: Asegúrate de que las reglas en `additionalRules()` sean válidas para Laravel.

---

## Mejores Prácticas

1. **Siempre usa `BaseArchiveRequest`**: No crees FormRequests desde cero
2. **Documentación clara**: Agrega docblocks con ejemplos de uso
3. **Validación sensible**: No permitas todos los tipos, sé específico
4. **Tamaños razonables**: Considera el contexto de uso
5. **MIME type validation**: Siempre valida tanto extensión como MIME type
6. **Context-aware**: Siempre verifica permisos del usuario en el contexto
7. **Logging**: Registra intentos de validación que fallan
8. **Testing**: Crea tests para cada nuevo FormRequest

---

## Referencias

- [Configuración de archivos](../config/files.php)
- [BaseArchiveRequest](../app/Http/Requests/Archive/BaseArchiveRequest.php)
- [FileValidationHelpers](../app/Support/FileValidationHelpers.php)
- [Laravel FormRequest Documentation](https://laravel.com/docs/requests#form-request-validation)
- [MIME Types List](https://www.iana.org/assignments/media-types/media-types.xhtml)
