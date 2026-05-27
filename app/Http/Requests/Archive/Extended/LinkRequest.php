<?php

namespace App\Http\Requests\Archive;

use Illuminate\Validation\Rule;

/**
 * LinkRequest
 * 
 * Validación para registros de enlaces a recursos web.
 * 
 * A diferencia de otros tipos, este request NO requiere un archivo.
 * En su lugar, almacena una URL a un recurso externo.
 * 
 * Tipos permitidos: URLs a páginas web
 * Tamaño máximo: N/A (solo metadatos)
 * 
 * Uso:
 * ```php
 * class StoreActivityLinkController {
 *     public function store(LinkRequest $request) {
 *         $url = $request->input('url');
 *         // Guardar en BD sin archivo físico
 *     }
 * }
 * ```
 */
class LinkRequest extends BaseArchiveRequest
{
    protected string $fileType = 'link';
    protected string $fileField = 'url'; // Usamos 'url' en lugar de 'archivo'

    /**
     * Override authorize para permitir sin contexto (links pueden ser globales)
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Override fileRules para no validar archivo (usamos URL)
     */
    protected function fileRules(): array
    {
        return [
            'url' => 'required|url|max:2048',
            'id_contexto' => 'required|integer|exists:usuario.contexto,id_contexto',
        ];
    }

    /**
     * Reglas adicionales específicas para enlaces.
     */
    protected function additionalRules(): array
    {
        return [
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'tipo_contenido' => [
                'required',
                Rule::in(['webpage', 'video', 'image', 'document', 'other']),
            ],
            'dominio' => 'nullable|string|max:255',
        ];
    }

    /**
     * Mensajes personalizados para enlaces.
     */
    protected function customMessages(): array
    {
        return [
            'url.required' => 'La URL es requerida.',
            'url.url' => 'Debe ser una URL válida.',
            'url.max' => 'La URL no puede exceder 2048 caracteres.',
            'titulo.required' => 'El título es requerido.',
            'titulo.max' => 'El título no puede exceder 255 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 1000 caracteres.',
            'tipo_contenido.required' => 'Debe especificar el tipo de contenido.',
            'tipo_contenido.in' => 'El tipo de contenido no es válido.',
            'dominio.max' => 'El dominio no puede exceder 255 caracteres.',
        ];
    }

    /**
     * Override attributes para reflejar que es URL, no archivo
     */
    protected function customAttributes(): array
    {
        return [
            'url' => 'enlace',
            'titulo' => 'título del enlace',
        ];
    }

    /**
     * Override getFile() para retornar URL en lugar de archivo
     */
    public function getUrl(): string
    {
        return $this->input('url');
    }

    /**
     * Este request no usa getFile(), pero lo dejamos vacío para no romper la interfaz
     */
    public function getFile()
    {
        throw new \BadMethodCallException('LinkRequest no soporta getFile(). Usa getUrl() en su lugar.');
    }
}
