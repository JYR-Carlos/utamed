<?php

namespace App\Api\V1\Requests\Student\Agenda;

use App\Http\Requests\Archive\AgendaFileRequest;

/**
 * Validación para la subida de archivos de entrega en la API estudiantil.
 * Extiende AgendaFileRequest garantizando tamaño, extensiones permitidas y campos opcionales.
 */
class StoreActivitySubmissionRequest extends AgendaFileRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function additionalRules(): array
    {
        return array_merge(parent::additionalRules(), [
            'mensaje' => 'nullable|string|max:1000',
        ]);
    }

    public function customMessages(): array
    {
        return array_merge(parent::customMessages(), [
            'mensaje.max' => 'El mensaje no puede exceder los 1000 caracteres.',
        ]);
    }

    public function getSubmissionMessage(): ?string
    {
        return $this->input('mensaje');
    }
}
