<?php

namespace App\Http\Requests\Archive;

use App\Services\Archive\FiletypeValidation\FileRequirementBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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
            'id_contexto.exists' => 'El contexto especificado no existe.',
        ];
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
