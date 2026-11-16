<?php

namespace App\DTOs\External;

class AlumnoIntranetData
{
    public readonly ?string $alum_email;

    public function __construct(
        public readonly int $alum_rut,
        public readonly ?string $alum_digito,
        public readonly string $alum_nombre,
        public readonly string $alum_apellido_pat,
        public readonly ?string $alum_apellido_mat = null,
        ?string $alum_email = null,
        ?string $email = null
    ) {
        $raw = $alum_email ?? $email;
        $this->alum_email = (!is_null($raw) && trim((string)$raw) !== '')
            ? mb_strtolower(trim((string)$raw))
            : null;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'email') {
            return $this->alum_email;
        }
        return null;
    }
}
