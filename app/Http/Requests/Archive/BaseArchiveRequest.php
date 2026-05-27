<?php

namespace App\Http\Requests\Archive;

use App\Models\Usuario\Contexto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

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
     * Tipo de archivo que acepta este request.
     * Debe ser una clave válida en config('files').
     * 
     * @var string
     */
    protected string $fileType = 'document';

    /**
     * Campo del formulario que contiene el archivo.
     * 
     * @var string
     */
    protected string $fileField = 'archivo';

    /**
     * Determine if the user is authorized to make this request.
     * 
     * Verifica que:
     * 1. El usuario está autenticado
     * 2. El contexto especificado existe
     * 3. El usuario tiene permiso de upload en ese contexto
     */
    public function authorize(): bool
    {
        // Usuario debe estar autenticado
        if (!$this->user()) {
            return false;
        }

        // Contexto es requerido
        $contextoId = $this->input('id_contexto');
        if (!$contextoId) {
            return false;
        }

        // Contexto debe existir
        $contexto = Contexto::find($contextoId);
        if (!$contexto) {
            return false;
        }

        // Usuario debe tener permiso de upload en el contexto
        return $this->user()->hasPermissionFor(
            config('files.required_permissions.upload', 'upload_files'),
            $contexto
        );
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * Combina reglas base (del tipo de archivo) con reglas adicionales
     * que implementan las subclases.
     */
    final public function rules(): array
    {
        return array_merge(
            $this->fileRules(),
            $this->additionalRules()
        );
    }

    /**
     * Reglas de validación del archivo según su tipo.
     * 
     * Obtiene extensiones y MIME types de config('files').
     */
    protected function fileRules(): array
    {
        $fileConfig = config("files.{$this->fileType}");

        if (!$fileConfig) {
            return [
                $this->fileField => 'required|file',
            ];
        }

        $extensions = $fileConfig['extensions'] ?? [];
        $mimes = $fileConfig['mimes'] ?? [];
        $maxSize = $fileConfig['max_size'] ?? config('files.global.max_file_size');

        $fileValidation = [
            'required',
            'file',
            File::max($maxSize),
        ];

        // Agregar validaciones si están habilitadas
        if (config('files.global.enable_extension_validation', true) && !empty($extensions)) {
            $fileValidation[] = File::extensions($extensions);
        }

        if (config('files.global.enable_mime_validation', true) && !empty($mimes)) {
            $fileValidation[] = File::mimes(...$mimes);
        }

        return [
            $this->fileField => $fileValidation,
            'id_contexto' => 'required|integer|exists:usuario.contexto,id_contexto',
        ];
    }

    /**
     * Reglas adicionales específicas que las subclases implementan.
     * 
     * @return array
     */
    abstract protected function additionalRules(): array;

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $fileConfig = config("files.{$this->fileType}");
        $extensions = implode(', ', $fileConfig['extensions'] ?? []);
        $maxSize = size_for_humans($fileConfig['max_size'] ?? config('files.global.max_file_size'));

        return array_merge([
            "{$this->fileField}.required" => "El archivo es requerido.",
            "{$this->fileField}.file" => "Debe ser un archivo válido.",
            "{$this->fileField}.max" => "El archivo no puede exceder {$maxSize}.",
            "{$this->fileField}.extensions" => "Extensiones permitidas: {$extensions}.",
            "{$this->fileField}.mimes" => "El archivo debe ser de un tipo permitido.",
            'id_contexto.required' => 'El contexto es requerido.',
            'id_contexto.exists' => 'El contexto especificado no existe.',
        ], $this->customMessages());
    }

    /**
     * Mensajes adicionales de las subclases.
     * 
     * @return array
     */
    protected function customMessages(): array
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return array_merge([
            $this->fileField => 'archivo',
            'id_contexto' => 'contexto',
        ], $this->customAttributes());
    }

    /**
     * Atributos adicionales de las subclases.
     * 
     * @return array
     */
    protected function customAttributes(): array
    {
        return [];
    }

    /**
     * Helper: Obtener archivo validado.
     */
    public function getFile(): \Illuminate\Http\UploadedFile
    {
        return $this->file($this->fileField);
    }

    /**
     * Helper: Obtener ID del contexto.
     */
    public function getContextoId(): int
    {
        return (int) $this->input('id_contexto');
    }

    /**
     * Helper: Obtener modelo del contexto.
     */
    public function getContexto(): Contexto
    {
        return Contexto::findOrFail($this->getContextoId());
    }

    /**
     * Helper: Obtener configuración del tipo de archivo.
     */
    protected function getFileConfig(): array
    {
        return config("files.{$this->fileType}", []);
    }

    /**
     * Helper: Obtener descripción del tipo de archivo.
     */
    protected function getFileTypeDescription(): string
    {
        return $this->getFileConfig()['description'] ?? $this->fileType;
    }
}
