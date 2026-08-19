<?php

namespace App\Policies;

use App\Policies\Base\BaseProgramaPolicy;
use App\Models\Usuario\Usuario;
use App\Models\Curso\Curso;
use App\Models\Curso\Programa;
use App\Services\Authorization\JefaturaCarreraResolver;

/**
 * Policy personalizada para Programa.
 * 
 * Valida que:
 * 1. Usuario es Administrador, O
 * 2. Usuario es docente asignado al curso (tiene sección en ese curso), O
 * 3. Usuario tiene permisos específicos de programa
 *
 * Patrones disponibles:
 *   1. Sobrescribir customXXX()       → base corre primero, hook como fallback
 *   2. Sobrescribir método + parent:: → tu lógica primero, base como fallback
 *   3. Sobrescribir sin parent::      → reemplaza la base completamente
 */
class ProgramaPolicy extends BaseProgramaPolicy
{
    /**
     * Verifica si el usuario es administrador
     * 
     * @param Usuario $user
     * @return bool
     */
    private function isAdmin(Usuario $user): bool
    {
        return $user->rolesAsignados()
            ->where('esta_activo', true)
            ->where('fue_eliminado', false)
            ->whereIn('nombre', ['Administrador', 'SuperAdmin', 'Super Admin', 'Admin'])
            ->exists();
    }

    /**
     * Verifica si el usuario es docente asignado al curso (titular o componente)
     *
     * @param Usuario $user
     * @param Programa $programa
     * @return bool
     */
    private function isAssignedDocente(Usuario $user, Programa $programa): bool
    {
        if (!$user->docente) {
            return false;
        }

        // Titular del curso
        $esTitular = \App\Models\Curso\Curso::where('id_curso', $programa->id_curso)
            ->where('id_docente_titular', $user->docente->id_docente)
            ->exists();

        if ($esTitular) {
            return true;
        }

        // Docente de componente del curso
        return $this->isComponenteDocente($user, $programa->id_curso);
    }

    /**
     * Verifica si el usuario es docente asignado a un curso (titular o componente)
     *
     * @param Usuario $user
     * @param \App\Models\Curso\Curso $curso
     * @return bool
     */
    private function isAssignedDocenteToCurso(Usuario $user, $curso): bool
    {
        if (!$user->docente) {
            return false;
        }

        $esTitular = \App\Models\Curso\Curso::where('id_curso', $curso->id_curso)
            ->where('id_docente_titular', $user->docente->id_docente)
            ->exists();

        if ($esTitular) {
            return true;
        }

        return $this->isComponenteDocente($user, $curso->id_curso);
    }

    /**
     * Verifica si el docente está asignado a algún componente del curso
     */
    private function isComponenteDocente(Usuario $user, int $cursoId): bool
    {
        return \App\Models\Curso\Componente::where('id_curso', $cursoId)
            ->whereHas('docenteComponentes', function ($q) use ($user) {
                $q->where('id_docente', $user->docente->id_docente);
            })
            ->exists();
    }

    /**
     * Verifica si el usuario puede ver el programa de un curso
     * Usado por el controlador para autorizar la vista inicial
     * 
     * Acepta un Curso en lugar de un Programa (para vistas sin programa creado aún)
     * 
     * @param Usuario $user Usuario intentando ver
     * @param mixed $curso Curso model
     * @return bool
     */
    public function viewPrograma(Usuario $user, $curso): bool
    {
        // Administrador siempre puede ver
        if ($this->isAdmin($user)) {
            return true;
        }

        // Docente asignado al curso puede ver su programa
        if ($this->isAssignedDocenteToCurso($user, $curso)) {
            return true;
        }

        return false;
    }

    /**
     * Sobrescribir view para añadir validación de docente asignado
     */
    public function view(Usuario $user, Programa $model): bool
    {
        // Administrador siempre puede ver
        if ($this->isAdmin($user)) {
            return true;
        }

        // Docente asignado al curso puede ver su programa
        if ($this->isAssignedDocente($user, $model)) {
            return true;
        }

        // El modelo Programa no tiene mapping de contexto registrado,
        // por lo que no se puede delegar a la base (lanzaría RuntimeException).
        return false;
    }

    /**
     * Sobrescribir update para añadir validación de docente asignado + permisos
     * 
     * Reglas:
     * - Administrador: Puede editar cualquier programa
     * - Docente: Solo si:
     *   1. Tiene sección en el curso, Y
     *   2. Tiene permiso 'cursos/programas:editar' en el contexto del curso
     */
    public function update(Usuario $user, Programa $model): bool
    {
        // Administrador siempre puede editar
        if ($this->isAdmin($user)) {
            return true;
        }

        // Para docentes: debe estar asignado al curso Y tener permiso para editar
        if ($this->isAssignedDocente($user, $model)) {
            // Validar que tiene el permiso específico para editar programas en este curso
            return parent::update($user, $model);
        }

        // Docente no asignado al curso no puede editar
        return false;
    }

    /**
     * Sobrescribir delete para añadir validación de docente asignado + permisos
     * 
     * Reglas:
     * - Administrador: Puede eliminar cualquier programa
     * - Docente: Solo si:
     *   1. Tiene sección en el curso, Y
     *   2. Tiene permiso 'cursos/programas:eliminar' en el contexto del curso
     */
    public function delete(Usuario $user, Programa $model): bool
    {
        // Administrador siempre puede eliminar
        if ($this->isAdmin($user)) {
            return true;
        }

        // Para docentes: debe estar asignado al curso Y tener permiso para eliminar
        if ($this->isAssignedDocente($user, $model)) {
            // Validar que tiene el permiso específico para eliminar programas en este curso
            return parent::delete($user, $model);
        }

        // Docente no asignado al curso no puede eliminar
        return false;
    }

    /**
     * Sobrescribir create para validar docente asignado + permisos
     * 
     * Reglas:
     * - Administrador: Puede crear programa en cualquier curso
     * - Docente: Solo si:
     *   1. Tiene sección en el curso, Y
     *   2. Tiene permiso 'cursos/programas:crear' en el contexto del curso
     * 
     * @param Usuario $user Usuario intentando crear programa
     * @param \App\Models\Curso\Curso|null $parent Curso donde se crea el programa
     * @return bool
     */
    public function create(Usuario $user, $parent = null): bool
    {
        // Administrador siempre puede crear
        if ($this->isAdmin($user)) {
            return true;
        }

        // **Ayudante NO puede crear programas** (solo puede editar si autorizado)
        if ($user->hasRole('Ayudante')) {
            return false;
        }

        // Para docentes: debe estar asignado al curso Y tener permiso para crear
        if ($parent && $this->isAssignedDocenteToCurso($user, $parent)) {
            // Validar que el docente titular tiene permiso 'cursos/programas:agregar' en el contexto del curso
            return $user->hasPermissionFor(
                \App\Support\Permissions::CURSOS_PROGRAMAS_AGREGAR,
                $parent
            );
        }

        // Docente no asignado al curso no puede crear
        return false;
    }

    /**
     * Método custom para hook adicional si es necesario
     * Se ejecuta después de la validación base
     */
    protected function customCreate(Usuario $user, $parent = null): bool
    {
        return false;
    }

    protected function customView(Usuario $user, $model): bool
    {
        return false;
    }

    protected function customUpdate(Usuario $user, $model): bool
    {
        return false;
    }

    protected function customDelete(Usuario $user, $model): bool
    {
        return false;
    }

    /**
     * Determina si el usuario puede aprobar un programa.
     */
    public function approve(Usuario $user, Programa $model): bool
    {
        return $this->puedeResolverPrograma($user, $model);
    }

    /**
     * Determina si el usuario puede rechazar un programa.
     */
    public function reject(Usuario $user, Programa $model): bool
    {
        return $this->puedeResolverPrograma($user, $model);
    }

    /**
     * Competencia para sellar el estado de un programa (aprobar o rechazar).
     *
     * Ambas operaciones recibían el `Programa` y no lo miraban: bastaba tener el
     * rol en cualquier contexto, así que un Jefe de Carrera de Medicina aprobaba
     * el syllabus de cualquier carrera y la firma `revisado_por` quedaba a nombre
     * de quien no tenía competencia sobre ella (E-1).
     */
    private function puedeResolverPrograma(Usuario $user, Programa $programa): bool
    {
        // SuperAdmin: permiso wildcard global, sin ámbito.
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Administrador **del contexto global**: competencia sobre toda la
        // universidad. Un "Administrador" acotado a un departamento no la tiene.
        if ($user->hasAnyRoleGlobally(Usuario::ROLES_ADMINISTRATIVOS)) {
            return true;
        }

        // Jefe de Carrera: sólo los programas de cursos de SU carrera.
        $carreraId = app(JefaturaCarreraResolver::class)->carreraId($user);

        if ($carreraId === null) {
            return false;
        }

        return Curso::where('id_curso', $programa->id_curso)
            ->whereHas('asignacionPlan.plan', fn($q) => $q->where('id_carrera', $carreraId))
            ->exists();
    }
}

