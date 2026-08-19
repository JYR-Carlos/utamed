<?php

namespace App\DTOs\External;

class InscripcionData
{
  public function __construct(
    public readonly int $ins_id,
    public readonly int $alum_rut,
  ) {}
}
