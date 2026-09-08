<?php

namespace App\Syllabus\Secciones;

/**
 * Entrada de bibliografía (sección VIII del syllabus).
 */
final class BibliografiaSyllabus
{
    public readonly ?string $id_bibliografia;
    public readonly string $titulo;
    public readonly ?string $autor;
    public readonly ?string $cita;
    public readonly ?string $editorial;
    public readonly int $anio;
    public readonly bool $es_bibliografia_uta;
    public readonly ?string $url;
    public readonly ?string $uuid_archivo;
    public readonly ?int $id_unidad;

    public function __construct(
        string $titulo,
        int $anio,
        bool $es_bibliografia_uta,
        ?string $id_bibliografia = null,
        ?string $autor = null,
        ?string $cita = null,
        ?string $editorial = null,
        ?string $url = null,
        ?string $uuid_archivo = null,
        ?int $id_unidad = null
    ) {
        $this->titulo = $titulo;
        $this->anio = $anio;
        $this->es_bibliografia_uta = $es_bibliografia_uta;
        $this->id_bibliografia = $id_bibliografia;
        $this->autor = $autor;
        $this->cita = $cita;
        $this->editorial = $editorial;
        $this->url = $url;
        $this->uuid_archivo = $uuid_archivo;
        $this->id_unidad = $id_unidad;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            titulo: (string) ($data['titulo'] ?? ''),
            anio: (int) ($data['anio'] ?? date('Y')),
            es_bibliografia_uta: (bool) ($data['es_bibliografia_uta'] ?? false),
            id_bibliografia: !empty($data['id_bibliografia']) ? (string) $data['id_bibliografia'] : null,
            autor: !empty($data['autor']) ? (string) $data['autor'] : null,
            cita: !empty($data['cita']) ? (string) $data['cita'] : null,
            editorial: !empty($data['editorial']) ? (string) $data['editorial'] : null,
            url: !empty($data['url']) ? (string) $data['url'] : null,
            uuid_archivo: !empty($data['uuid_archivo']) ? (string) $data['uuid_archivo'] : null,
            id_unidad: isset($data['id_unidad']) && is_numeric($data['id_unidad']) ? (int) $data['id_unidad'] : null,
        );
    }

    /** @param array<int, array> $items */
    public static function listFromArray(array $items): array
    {
        return array_map(fn (array $i) => self::fromArray($i), $items);
    }

    public function toArray(): array
    {
        return [
            'id_bibliografia' => $this->id_bibliografia,
            'titulo' => $this->titulo,
            'autor' => $this->autor,
            'cita' => $this->cita,
            'editorial' => $this->editorial,
            'anio' => $this->anio,
            'es_bibliografia_uta' => $this->es_bibliografia_uta,
            'url' => $this->url,
            'uuid_archivo' => $this->uuid_archivo,
            'id_unidad' => $this->id_unidad,
        ];
    }

    /** @param BibliografiaSyllabus[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
