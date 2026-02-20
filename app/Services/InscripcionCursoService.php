<?php

namespace App\Services;

use App\Models\Curso\Curso;
use App\Models\Curso\InscripcionCurso;
use App\Models\Usuario\Estudiante;
use App\Models\Usuario\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para gestionar inscripciones de cursos.
 * Encapsula la lógica de negocio relacionada con inscripciones de estudiantes.
 */
class InscripcionCursoService
{
    /**
     * Crea una nueva inscripción de estudiante en un curso.
     */
    public function create(array $data): InscripcionCurso
    {
        return DB::transaction(function () use ($data) {
            // Set default values
            $data['fecha_inscripcion'] ??= now()->toDateString();
            $data['estado_inscripcion'] ??= 'INSCRITO';
            $data['num_intento'] ??= 1;

            return InscripcionCurso::create($data);
        });
    }

    /**
     * Actualiza una inscripción existente.
     */
    public function update(InscripcionCurso $inscripcion, array $data): InscripcionCurso
    {
        return DB::transaction(function () use ($inscripcion, $data) {
            $inscripcion->update($data);
            return $inscripcion;
        });
    }

    /**
     * Elimina una inscripción.
     */
    public function delete(InscripcionCurso $inscripcion): bool
    {
        return DB::transaction(function () use ($inscripcion) {
            return $inscripcion->delete();
        });
    }

    /**
     * Obtiene estudiantes disponibles (no inscritos) en un curso.
     * Auto-crea perfiles de Estudiante para usuarios con rol estudiante que no tengan perfil.
     */
    public function getEstudiantesDisponibles(int $idCurso): Collection
    {
        // Get estudiante ids already inscribed in this course
        $inscritosIds = InscripcionCurso::where('id_curso', $idCurso)
            ->pluck('id_estudiante')
            ->toArray();

        // Get available estudiantes (with existing profiles)
        $estudiantes = Estudiante::query()
            ->with('usuario:id_usuario,nombre1,apellido1,username')
            ->whereNotIn('id_estudiante', $inscritosIds)
            ->orderBy('id_estudiante')
            ->get();

        // Find users with 'estudiante' role but no Estudiante profile and auto-create
        $usuariosConRolEstudiante = $this->findUsersWithStudentRoleWithoutProfile();

        // Auto-create Estudiante profiles for users with student role
        foreach ($usuariosConRolEstudiante as $usuario) {
            try {
                $nuevoEstudiante = Estudiante::create([
                    'id_usuario' => $usuario->id_usuario,
                    'rut' => $usuario->rut,
                    'nombre_completo' => trim(($usuario->nombre1 ?? '') . ' ' . ($usuario->apellido1 ?? '')),
                    'agno_ingreso' => now()->year,
                    'id_carrera' => null,
                    'id_contexto' => null
                ]);

                // Add to collection if not already inscribed
                if (!in_array($nuevoEstudiante->id_estudiante, $inscritosIds)) {
                    $nuevoEstudiante->load('usuario:id_usuario,nombre1,apellido1,username');
                    $estudiantes->push($nuevoEstudiante);
                }

                Log::info('Auto-created Estudiante profile', [
                    'id_usuario' => $usuario->id_usuario,
                    'id_estudiante' => $nuevoEstudiante->id_estudiante
                ]);
            } catch (\Exception $e) {
                Log::error('Error auto-creating Estudiante profile: ' . $e->getMessage(), [
                    'id_usuario' => $usuario->id_usuario
                ]);
            }
        }

        return $estudiantes;
    }

    /**
     * Obtiene inscripciones de un curso específico.
     */
    public function getInscripcionesByCurso(int $idCurso): Collection
    {
        return InscripcionCurso::where('id_curso', $idCurso)
            ->with(['estudiante.usuario', 'curso'])
            ->orderBy('fecha_inscripcion', 'desc')
            ->get();
    }

    /**
     * Obtiene los cursos en los que un docente enseña.
     */
    public function getCursosByDocente(int $idDocente): Collection
    {
        return Curso::query()
            ->whereHas('secciones', function ($query) use ($idDocente) {
                $query->where('id_docente', $idDocente);
            })
            ->orderBy('cod_curso')
            ->get();
    }

    /**
     * Obtiene inscripciones filtradas según criterios.
     */
    public function getFiltered(array $filters, ?int $idDocente = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = InscripcionCurso::query();

        // Si es docente, filtrar solo sus cursos
        if ($idDocente) {
            $cursoIds = Curso::whereHas('secciones', function ($q) use ($idDocente) {
                $q->where('id_docente', $idDocente);
            })->pluck('id_curso');

            $query->whereIn('id_curso', $cursoIds);
        }

        // Buscar por nombre de estudiante o curso
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('estudiante.usuario', function ($q) use ($search) {
                $q->where(DB::raw("CONCAT(nombre1, ' ', COALESCE(apellido1, ''))"), 'ilike', "%{$search}%")
                    ->orWhere('username', 'ilike', "%{$search}%");
            })
                ->orWhereHas('curso', function ($q) use ($search) {
                    $q->where('nombre', 'ilike', "%{$search}%")
                        ->orWhere('cod_curso', 'ilike', "%{$search}%");
                });
        }

        // Filtrar por curso
        if (!empty($filters['id_curso'])) {
            $query->where('id_curso', $filters['id_curso']);
        }

        // Filtrar por estado
        if (!empty($filters['estado_inscripcion'])) {
            $query->where('estado_inscripcion', $filters['estado_inscripcion']);
        }

        return $query
            ->with(['curso', 'estudiante.usuario'])
            ->orderBy('fecha_inscripcion', 'desc')
            ->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1);
    }

    /**
     * Encuentra usuarios con rol 'Estudiante' que no tienen perfil de Estudiante.
     */
    private function findUsersWithStudentRoleWithoutProfile(): Collection
    {
        return Usuario::whereHas('usuarioRolAsignaciones', function ($query) {
                $query->whereHas('rol', function ($roleQuery) {
                    $roleQuery->where('nombre', 'Estudiante');
                })
                ->where('esta_activo', true)
                ->where('fue_eliminado', false);
            })
            ->whereDoesntHave('estudiante')
            ->select('id_usuario', 'nombre1', 'apellido1', 'username', 'rut')
            ->get();
    }
}
