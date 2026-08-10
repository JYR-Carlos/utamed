<?php

namespace App\DTOs\External;

class AlumnoIntranetData
{
    public function __construct(
        public readonly int $alum_rut,
        public readonly ?string $alum_digito,
        public readonly string $alum_nombre,
        public readonly string $alum_apellido_pat,
        public readonly ?string $alum_apellido_mat = null
    ) {}
}
