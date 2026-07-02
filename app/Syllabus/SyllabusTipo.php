<?php

namespace App\Syllabus;

/**
 * Tipo de syllabus: BASICO (5 secciones) o COMPLETO (9 secciones).
 */
enum SyllabusTipo: string
{
    case Basico = 'BASICO';
    case Completo = 'COMPLETO';
}
