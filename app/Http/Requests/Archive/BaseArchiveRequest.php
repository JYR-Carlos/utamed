<?php

namespace App\Http\Requests\Archive;

use App\Services\Archive\FiletypeValidation\FileRequirementBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

/**
 * BaseArchiveRequest
 * 
 * Clase abstracta base para validación de archivos.
 * 
 * Define el flujo de validación común a todos los tipos de archivos,
 * permitiendo que subclases especifiquen el tipo de archivo que aceptan.
 * 
 * Uso:
 * ```php
 * class MyArchiveRequest extends BaseArchiveRequest {
 *     protected string $fileType = 'video'; // Usar config('files.video')
 * }
 * ```
 */
abstract class BaseArchiveRequest extends FormRequest
{
    /**
     * Categorías de archivo que acepta este request.
     * Las subclases deben sobrescribir este array con los casos del Enum.
     * * @var array<FileRequirementType>
     */
    protected array $fileCategories = [];

    /**
     * Campo del formulario que contiene el archivo.
     * 
     * @var string
     */
    protected string $fileField = 'archivo';

    
    private ?FileRequirementBuilder $builder = null;

    /**
     * Inicializa el Builder con las categorías definidas en la subclase.
     */
    private function getBuilder(): FileRequirementBuilder
    {
        if ($this->builder === null) {
            $this->builder = FileRequirementBuilder::make();

            foreach ($this->fileCategories as $category) {
                $this->builder->addConfig($category);
            }
        }

        return $this->builder;
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * Combina reglas base (del tipo de archivo) con reglas adicionales
     * que implementan las subclases.
     */
    final public function rules(): array
    {
        return [
            ...$this->fileRules(),
            ...$this->additionalRules()
        ];
    }

    /**
     * Reglas de validación del archivo según su tipo.
     * 
     * Obtiene extensiones y MIME types de config('files').
     */
    /**
     * Delega la construcción de reglas nativas al Builder.
     */
    final protected function fileRules(): array
    {
        if (empty($this->fileCategories)) {
            return [];
        }

        return [
            $this->fileField => $this->getBuilder()->buildLaravelRules(),
        ];
    }

    /**
     * Reglas adicionales específicas que las subclases implementan.
     * 
     * @return array
     */
    abstract protected function additionalRules(): array;

    /**
     * Combina mensajes base con los de la subclase.
     */
    final public function messages(): array
    {
        return [
            ...$this->baseMessages(),
            ...$this->builderMessages(),
            ...$this->customMessages()
        ];
    }

    final protected function baseMessages(): array
    {
        return [
            "{$this->fileField}.required" => 'El archivo es obligatorio.',
            "{$this->fileField}.file" => 'El archivo subido no es válido.',
            // `uploaded` no lo dispara ninguna regla nuestra: lo añade Laravel
            // cuando el archivo llega inválido desde PHP (UploadedFile::isValid()
            // en false). Sin mensaje propio el alumno veía el texto por defecto en
            // inglés ("The archivo failed to upload."), que no distingue entre un
            // archivo demasiado grande, una subida cortada a medias y un servidor
            // que no puede escribir su directorio temporal — causas con soluciones
            // opuestas. Aquí se traduce el código real que reportó PHP.
            "{$this->fileField}.uploaded" => $this->uploadFailureMessage(),
            'id_contexto.exists' => 'El contexto especificado no existe.',
        ];
    }

    /**
     * Traduce a un mensaje accionable el fallo de subida que reportó PHP.
     *
     * Puro a propósito: `messages()` se evalúa en toda petición, también en las
     * que terminan bien, así que aquí no puede haber efectos de lado. El registro
     * del diagnóstico vive en `failedValidation()`, que sólo corre al fallar.
     *
     * El código va dentro del mensaje a propósito: es el único dato que el
     * estudiante puede repetirle a soporte para separar un problema suyo (archivo
     * grande, red cortada) de uno del servidor (sin directorio temporal, sin
     * permiso de escritura).
     */
    private function uploadFailureMessage(): string
    {
        $file = $this->uploadedFile();

        if (!$file instanceof UploadedFile) {
            return 'No llegó ningún archivo en el envío.';
        }

        $code = $file->getError();

        $detalle = match ($code) {
            UPLOAD_ERR_INI_SIZE => 'supera el tamaño máximo por archivo que acepta este servidor ('
                . ini_get('upload_max_filesize') . 'B). Comprímelo o divídelo.',
            UPLOAD_ERR_FORM_SIZE => 'supera el tamaño máximo declarado por el formulario.',
            UPLOAD_ERR_PARTIAL => 'se subió sólo en parte: la conexión se cortó antes de terminar. '
                . 'Vuelve a intentarlo sin cambiar de página.',
            UPLOAD_ERR_NO_FILE => 'no llegó en el envío.',
            UPLOAD_ERR_NO_TMP_DIR => 'no pudo guardarse porque al servidor le falta su directorio '
                . 'temporal. Es un problema de configuración del servidor, no de tu archivo.',
            UPLOAD_ERR_CANT_WRITE => 'no pudo escribirse en el disco del servidor. Es un problema '
                . 'del servidor, no de tu archivo.',
            UPLOAD_ERR_EXTENSION => 'fue bloqueado por una extensión de PHP en el servidor.',
            // PHP reportó la subida como correcta, pero `is_uploaded_file()` ya no
            // reconoce el temporal: o se movió/borró antes de validar, o el proceso
            // que atiende la petición no es el que la recibió.
            UPLOAD_ERR_OK => 'llegó al servidor, pero su copia temporal desapareció antes de '
                . 'poder guardarla. Es un problema del servidor, no de tu archivo.',
            default => 'llegó en un estado que el servidor no pudo aceptar.',
        };

        return "El archivo {$detalle} (código {$code})";
    }

    /**
     * El archivo tal y como lo ve el validador.
     *
     * `$this->files` guarda el UploadedFile de Symfony que armó el kernel; la
     * versión de Illuminate — la que el validador compara — vive aparte, en la
     * caché que llena `allFiles()`. Hay que pedirla por `file()`: con
     * `files->get()` un `instanceof UploadedFile` de Illuminate no casa nunca.
     */
    private function uploadedFile(): ?UploadedFile
    {
        $file = $this->file($this->fileField);

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * Registra el diagnóstico de las subidas que PHP entregó rotas.
     *
     * Sólo interesa el fallo `uploaded`: el resto (tipo, tamaño por categoría)
     * son rechazos legítimos del archivo y ya viajan al usuario en el mensaje.
     * `uploaded`, en cambio, suele delatar un problema del servidor, y sin esta
     * traza no queda constancia de él en ningún lado.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        if ($validator->errors()->has($this->fileField)) {
            $file = $this->uploadedFile();
            $raw = $_FILES[$this->fileField] ?? null;

            if ($file === null || !$file->isValid()) {
                Log::warning('Subida entregada por PHP en estado inválido.', [
                    'campo' => $this->fileField,
                    'codigo_error' => $file?->getError(),
                    'nombre_cliente' => $file?->getClientOriginalName(),
                    'ruta_temporal' => $file?->getPathname(),
                    'existe_temporal' => $file !== null && @file_exists($file->getPathname()),
                    'es_subida_reconocida' => $file !== null && @is_uploaded_file($file->getPathname()),
                    'tamano_crudo' => is_array($raw) ? ($raw['size'] ?? null) : null,
                    'campos_archivo_recibidos' => array_keys($_FILES),
                    'content_length' => $this->server('CONTENT_LENGTH'),
                    'content_type' => $this->server('CONTENT_TYPE'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
                ]);
            }
        }

        parent::failedValidation($validator);
    }

    /**
     * Obtiene los mensajes dinámicos generados por el Builder.
     */
    private function builderMessages(): array
    {
        if (empty($this->fileCategories)) {
            return [];
        }

        return $this->getBuilder()->buildLaravelMessages($this->fileField);
    }

    protected function customMessages(): array
    {
        return [];
    }

    /**
     * Get the validated file.
     *
     * @return UploadedFile
     */
    public function getFile(): UploadedFile
    {
        return $this->file($this->fileField);
    }
}
