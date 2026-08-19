<?php

namespace App\Syllabus;

/** Docente titular reflejado en metadata.curso.docente_principal. */
final class SyllabusDocentePrincipal
{
    public readonly int $idDocente;
    public readonly ?string $nombre;
    public readonly ?string $titulo;
    public readonly ?string $grado;
    public readonly ?string $cargo;

    public function __construct(int $idDocente, ?string $nombre, ?string $titulo, ?string $grado, ?string $cargo)
    {
        $this->idDocente = $idDocente;
        $this->nombre = $nombre;
        $this->titulo = $titulo;
        $this->grado = $grado;
        $this->cargo = $cargo;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            idDocente: (int) ($data['id_docente'] ?? 0),
            nombre: $data['nombre'] ?? null,
            titulo: $data['titulo'] ?? null,
            grado: $data['grado'] ?? null,
            cargo: $data['cargo'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id_docente' => $this->idDocente,
            'nombre' => $this->nombre,
            'titulo' => $this->titulo,
            'grado' => $this->grado,
            'cargo' => $this->cargo,
        ];
    }
}
