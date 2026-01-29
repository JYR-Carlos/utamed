<?php
use App\Models\Usuario\UsuarioRolAsignación;
use App\Models\Curso\Curso;

$cursos = Curso::all();
echo "Cursos count: " . $cursos->count() . "\n";
foreach ($cursos as $c) {
    echo "Curso ID: {$c->id_curso}, Contexto: {$c->id_contexto}\n";
}

$assigns = UsuarioRolAsignación::all();
echo "Total Assignments: " . $assigns->count() . "\n";

foreach ($assigns as $a) {
    echo "Assign: User {$a->id_usuario_recipiente} to Role {$a->id_rol} in Context {$a->id_contexto}. Active: " . ($a->esta_activo ? 'Yes' : 'No') . "\n";
}
