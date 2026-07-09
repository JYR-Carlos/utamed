<?php

namespace App\DTOs\External;

use App\Enums\External\TipoAsignatura;

class ComponenteCursoData
{
  public function __construct(
    public readonly int $cur_codigo,
    public readonly TipoAsignatura $curso_tipo_asig,
    public readonly string $curso_grupo_asig,
  ) {}
}
