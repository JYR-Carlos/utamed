<?php

namespace App\Syllabus\Shell;

/**
 * Sección del formato transitorio de 6 secciones que produce
 * SyllabusStructure::build() antes de que el wizard guarde contenido real.
 */
final class SeccionSecuencial
{
    public readonly string $nombreSeccion;
    public readonly string $numeralRomano;
    public readonly int $orden;
    /** @var ContenidoSecuencial[] */
    public readonly array $contenidos;

    /** @param ContenidoSecuencial[] $contenidos */
    public function __construct(string $nombreSeccion, string $numeralRomano, int $orden, array $contenidos)
    {
        $this->nombreSeccion = $nombreSeccion;
        $this->numeralRomano = $numeralRomano;
        $this->orden = $orden;
        $this->contenidos = $contenidos;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            nombreSeccion: (string) ($data['nombre_seccion'] ?? ''),
            numeralRomano: (string) ($data['numeral_romano'] ?? ''),
            orden: (int) ($data['orden'] ?? 0),
            contenidos: ContenidoSecuencial::listFromArray($data['contenidos'] ?? []),
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
            'nombre_seccion' => $this->nombreSeccion,
            'numeral_romano' => $this->numeralRomano,
            'orden' => $this->orden,
            'contenidos' => ContenidoSecuencial::listToArray($this->contenidos),
        ];
    }

    /** @param SeccionSecuencial[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
