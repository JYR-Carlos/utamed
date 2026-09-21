<?php

namespace App\Http\Requests\Archive;

use App\Services\Archive\FiletypeValidation\FileRequirementType;

/**
 * BibliografiaFileRequest
 *
 * Validación para uploads de archivos físicos de bibliografía de Syllabus.
 *
 * Tipos permitidos:
 * - PDFs
 * - Documentos de Word (doc, docx, odt, rtf, txt)
 * - Presentaciones (ppt, pptx, odp)
 * - Documentos genéricos (epub, etc.)
 */
class BibliografiaFileRequest extends BaseArchiveRequest
{
    /**
     * Categorías de archivo permitidas para bibliografía.
     */
    protected array $fileCategories = [
        FileRequirementType::PDF,
        FileRequirementType::WORD_DOCUMENT,
        FileRequirementType::PRESENTATION,
        FileRequirementType::DOCUMENT,
    ];

    /**
     * Campo del formulario que contiene el archivo.
     */
    protected string $fileField = 'archivo';

    /**
     * Reglas adicionales de contexto para la bibliografía.
     */
    protected function additionalRules(): array
    {
        return [
            'id_curso' => 'required|integer|exists:curso,id_curso',
            'id_unidad' => 'nullable|integer',
            'id_programa' => 'nullable|integer|exists:programa,id_programa',
            'titulo' => 'nullable|string|max:255',
            'autor' => 'nullable|string|max:255',
            'nombre_archivo' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[\pL\pN\s._\-]+$/u',
            ],
        ];
    }

    /**
     * Mensajes personalizados.
     */
    public function customMessages(): array
    {
        return [
            'id_curso.required' => 'El curso es requerido para asociar la bibliografía.',
            'id_curso.exists' => 'El curso especificado no existe.',
            'id_programa.exists' => 'El programa especificado no existe.',
            'nombre_archivo.regex' => 'El nombre del archivo solo puede contener letras, números, espacios, guiones y puntos.',
        ];
    }

    public function getCursoId(): int
    {
        return (int) $this->input('id_curso');
    }

    public function getUnidadId(): ?int
    {
        return $this->filled('id_unidad') ? (int) $this->input('id_unidad') : null;
    }

    public function getProgramaId(): ?int
    {
        return $this->filled('id_programa') ? (int) $this->input('id_programa') : null;
    }

    public function getFileName(): ?string
    {
        return $this->input('nombre_archivo');
    }
}
