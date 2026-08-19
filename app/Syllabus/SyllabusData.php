<?php

namespace App\Syllabus;

/**
 * Contenedor raíz de `programa.data_syllabus` cuando ya está en formato
 * asociativo (post-wizard). El formato transitorio de 6 secciones secuenciales
 * (ver App\Syllabus\Shell) se maneja aparte, no a través de esta clase.
 */
final class SyllabusData
{
    public readonly ?SyllabusMetadata $metadata;
    public readonly SyllabusSecciones $secciones;
    public readonly ?string $timestamp;

    public function __construct(?SyllabusMetadata $metadata, SyllabusSecciones $secciones, ?string $timestamp = null)
    {
        $this->metadata = $metadata;
        $this->secciones = $secciones;
        $this->timestamp = $timestamp;
    }

    public static function fromArray(array $data): self
    {
        $metadata = !empty($data['metadata']) ? SyllabusMetadata::fromArray($data['metadata']) : null;

        return new self(
            metadata: $metadata,
            secciones: SyllabusSecciones::fromArray($data['secciones'] ?? [], $metadata?->tipoSyllabus),
            timestamp: $data['timestamp'] ?? null,
        );
    }

    public function withMetadata(SyllabusMetadata $metadata): self
    {
        return new self($metadata, $this->secciones, $this->timestamp);
    }

    public function withSecciones(SyllabusSecciones $secciones): self
    {
        return new self($this->metadata, $secciones, $this->timestamp);
    }

    public function withTimestamp(string $timestamp): self
    {
        return new self($this->metadata, $this->secciones, $timestamp);
    }

    public function toArray(): array
    {
        return array_filter([
            'metadata' => $this->metadata?->toArray(),
            'secciones' => $this->secciones->toArray(),
            'timestamp' => $this->timestamp,
        ], fn ($v) => $v !== null);
    }
}
