<?php

namespace App\Syllabus;

/** metadata de data_syllabus: asignatura, curso, categoría y tipo de syllabus. */
final class SyllabusMetadata
{
    public readonly ?SyllabusAsignatura $asignatura;
    public readonly ?SyllabusCurso $curso;
    public readonly ?SyllabusCategoria $categoria;
    public readonly ?SyllabusTipo $tipoSyllabus;

    public function __construct(
        ?SyllabusAsignatura $asignatura,
        ?SyllabusCurso $curso,
        ?SyllabusCategoria $categoria,
        ?SyllabusTipo $tipoSyllabus = null
    ) {
        $this->asignatura = $asignatura;
        $this->curso = $curso;
        $this->categoria = $categoria;
        $this->tipoSyllabus = $tipoSyllabus;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            asignatura: !empty($data['asignatura']) ? SyllabusAsignatura::fromArray($data['asignatura']) : null,
            curso: !empty($data['curso']) ? SyllabusCurso::fromArray($data['curso']) : null,
            categoria: !empty($data['categoria']) ? SyllabusCategoria::fromArray($data['categoria']) : null,
            tipoSyllabus: !empty($data['tipo_syllabus']) ? SyllabusTipo::tryFrom($data['tipo_syllabus']) : null,
        );
    }

    public function withTipoSyllabus(SyllabusTipo $tipo): self
    {
        return new self($this->asignatura, $this->curso, $this->categoria, $tipo);
    }

    public function toArray(): array
    {
        // asignatura/curso/categoria siempre están presentes (aunque vacíos), para
        // no cambiar el contrato original de SyllabusStructure::buildMetadata().
        // tipo_syllabus sí se omite cuando es null (antes de asignarlo explícitamente
        // no existía la clave en el array).
        $out = [
            'asignatura' => $this->asignatura?->toArray() ?? [],
            'curso' => $this->curso?->toArray() ?? [],
            'categoria' => $this->categoria?->toArray() ?? [],
        ];

        if ($this->tipoSyllabus !== null) {
            $out['tipo_syllabus'] = $this->tipoSyllabus->value;
        }

        return $out;
    }
}
