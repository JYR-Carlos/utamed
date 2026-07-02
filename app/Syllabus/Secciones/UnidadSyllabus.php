<?php

namespace App\Syllabus\Secciones;

/** Unidad temática de la sección VI, con sus resultados de aprendizaje. */
final class UnidadSyllabus
{
    public readonly int $numero;
    public readonly string $titulo;
    /** @var string[] */
    public readonly array $contenidosItems;
    /** @var ResultadoAprendizajeItem[] */
    public readonly array $resultadosAprendizaje;

    /**
     * @param string[] $contenidosItems
     * @param ResultadoAprendizajeItem[] $resultadosAprendizaje
     */
    public function __construct(int $numero, string $titulo, array $contenidosItems, array $resultadosAprendizaje)
    {
        $this->numero = $numero;
        $this->titulo = $titulo;
        $this->contenidosItems = $contenidosItems;
        $this->resultadosAprendizaje = $resultadosAprendizaje;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            numero: (int) ($data['numero'] ?? 0),
            titulo: (string) ($data['titulo'] ?? ''),
            contenidosItems: array_map(
                fn (array $c) => (string) ($c['item'] ?? ''),
                $data['contenidos_items'] ?? []
            ),
            resultadosAprendizaje: ResultadoAprendizajeItem::listFromArray($data['resultados_aprendizaje'] ?? []),
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
            'numero' => $this->numero,
            'titulo' => $this->titulo,
            'contenidos_items' => array_map(fn (string $item) => ['item' => $item], $this->contenidosItems),
            'resultados_aprendizaje' => ResultadoAprendizajeItem::listToArray($this->resultadosAprendizaje),
        ];
    }

    /** @param UnidadSyllabus[] $items */
    public static function listToArray(array $items): array
    {
        return array_map(fn (self $i) => $i->toArray(), $items);
    }
}
