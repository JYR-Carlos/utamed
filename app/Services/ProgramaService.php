<?php

namespace App\Services;

use App\Models\Administrativo\Programa;
use App\Models\Curso\Curso;
use App\Models\Usuario\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ProgramaService
 * 
 * Servicio para gestionar la creación, actualización y lectura de Programas
 * con estructura JSONB en el campo data_syllabus.
 */
class ProgramaService
{
    /**
     * Genera un programa con estructura JSONB completa para un curso
     * 
     * Rellena:
     * - Atributos de tabla (version, estado, creado_por, etc)
     * - data_syllabus con estructura completa (metadata + secciones)
     */
    public static function generateProgramaWithSyllabus(
        Curso $curso,
        ?Usuario $createdBy = null,
        ?array $overrides = null
    ): Programa {
        $createdBy = $createdBy ?? Auth::user();

        return DB::transaction(function () use ($curso, $createdBy, $overrides) {
            // Marcar programa anterior como no actual si existe
            $existing = Programa::where('id_curso', $curso->id_curso)
                ->where('es_actual', true)
                ->first();

            if ($existing) {
                $existing->update(['es_actual' => false]);
                $newVersion = $existing->version_programa + 1;
            } else {
                $newVersion = 1;
            }

            // Construir estructura JSONB base
            $syllabus = SyllabusStructure::for($curso);

            // Aplicar overrides si se proporcionan (ej: secciones customizadas)
            if ($overrides && isset($overrides['secciones'])) {
                // Reemplazar secciones customizadas con los datos del override
                $syllabus['secciones'] = $overrides['secciones'];
            }

            // Crear el programa con atributos de tabla + JSONB
            $programa = Programa::create([
                'id_curso' => $curso->id_curso,
                'version_programa' => $newVersion,
                'estado' => 'ABIERTO',
                'data_syllabus' => $syllabus,
                'creado_por' => $createdBy->id_usuario,
                'es_actual' => true,
                'fecha_creacion' => now(),
            ]);

            return $programa;
        });
    }

    /**
     * Actualiza el contenido JSONB de un programa
     */
    public static function updateSyllabusContent(
        Programa $programa,
        array $updates
    ): Programa {
        return DB::transaction(function () use ($programa, $updates) {
            $currentData = $programa->data_syllabus ?? [];

            // Aplicar actualizaciones
            $updatedData = self::deepMerge($currentData, $updates);

            $programa->update([
                'data_syllabus' => $updatedData,
                'fecha_modificacion' => now(),
                // No incrementar versión al actualizar, solo al regenerar
            ]);

            return $programa;
        });
    }

    /**
     * Obtiene la estructura del syllabus desde JSONB
     */
    public static function getSyllabusStructure(Programa $programa): ?array
    {
        return $programa->data_syllabus;
    }

    /**
     * Obtiene solo las secciones del syllabus
     */
    public static function getSecciones(Programa $programa): array
    {
        return $programa->data_syllabus['secciones'] ?? [];
    }

    /**
     * Obtiene la metadata del syllabus
     */
    public static function getMetadata(Programa $programa): array
    {
        return $programa->data_syllabus['metadata'] ?? [];
    }

    /**
     * Actualiza una sección específica
     */
    public static function updateSeccion(
        Programa $programa,
        int $orden,
        array $contenidos
    ): Programa {
        $data = $programa->data_syllabus ?? [];
        $secciones = $data['secciones'] ?? [];

        // Encontrar y actualizar la sección
        foreach ($secciones as &$seccion) {
            if ($seccion['orden'] === $orden) {
                $seccion['contenidos'] = $contenidos;
                break;
            }
        }

        $data['secciones'] = $secciones;
        $programa->update(['data_syllabus' => $data]);

        return $programa;
    }

    /**
     * Agrega un contenido a una sección
     */
    public static function addContentToSeccion(
        Programa $programa,
        int $orden,
        string $contenido,
        int $orden_item = null
    ): Programa {
        $data = $programa->data_syllabus ?? [];
        $secciones = $data['secciones'] ?? [];

        foreach ($secciones as &$seccion) {
            if ($seccion['orden'] === $orden) {
                $orden_item = $orden_item ?? (count($seccion['contenidos']) + 1);
                
                $seccion['contenidos'][] = [
                    'texto_contenido' => $contenido,
                    'orden_item' => $orden_item,
                ];
                break;
            }
        }

        $data['secciones'] = $secciones;
        $programa->update(['data_syllabus' => $data]);

        return $programa;
    }

    /**
     * Cambia el estado del programa
     */
    public static function changeStatus(
        Programa $programa,
        string $newStatus
    ): Programa {
        $validStatuses = ['ABIERTO', 'EN_REVISION', 'APROBADO', 'PUBLICADO'];

        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException(
                "Estado '$newStatus' no válido. Opciones: " . implode(', ', $validStatuses)
            );
        }

        return tap($programa)->update(['estado' => $newStatus]);
    }

    /**
     * Exporta el programa a array con formato legible
     */
    public static function export(Programa $programa): array
    {
        $data = $programa->data_syllabus ?? [];

        return [
            'programa_id' => $programa->id_programa,
            'version' => $programa->version_programa,
            'estado' => $programa->estado,
            'fecha_creacion' => $programa->fecha_creacion,
            'metadata' => $data['metadata'] ?? [],
            'secciones' => $data['secciones'] ?? [],
        ];
    }

    /**
     * Aplica overrides a la estructura del syllabus
     */
    private static function applySyllabusOverrides(array $syllabus, array $overrides): array
    {
        return self::deepMerge($syllabus, $overrides);
    }

    /**
     * Realiza un merge profundo de arrays
     */
    private static function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
