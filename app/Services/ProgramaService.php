<?php

namespace App\Services;

use App\Models\Curso\Programa;
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
     * Soporta dos tipos: BASICO y COMPLETO
     * 
     * Rellena:
     * - Atributos de tabla (version, estado, creado_por, etc)
     * - data_syllabus con estructura completa (metadata + secciones)
     * - tipo_syllabus en metadata (BASICO o COMPLETO)
     */
    public static function generateProgramaWithSyllabus(
        Curso $curso,
        ?Usuario $createdBy = null,
        ?array $overrides = null
    ): Programa {
        $createdBy = $createdBy ?? Auth::user();
        $tipoSyllabus = $overrides['tipo_syllabus'] ?? 'BASICO';
        $estadoInicial = $overrides['estado'] ?? ($tipoSyllabus === 'BASICO' ? 'BASICO_COMPLETO' : 'COMPLETO');

        return DB::transaction(function () use ($curso, $createdBy, $overrides, $tipoSyllabus, $estadoInicial) {
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
            
            // Agregar tipo_syllabus a metadata
            $syllabus['metadata']['tipo_syllabus'] = $tipoSyllabus;

            // Aplicar overrides si se proporcionan (ej: secciones customizadas)
            if ($overrides && isset($overrides['secciones'])) {
                // Reemplazar secciones customizadas con los datos del override
                $syllabus['secciones'] = $overrides['secciones'];
            }

            // Crear el programa con atributos de tabla + JSONB
            $programa = Programa::create([
                'id_curso' => $curso->id_curso,
                'version_programa' => $newVersion,
                'estado' => $estadoInicial,
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
     * Actualiza una sección específica por ID (I, II, III, etc.)
     * 
     * Implementa lógica de conversión automática:
     * - Si estado es BASICO_COMPLETO y se agrega sección III/IV/V/IX
     * - Cambia automáticamente a COMPLETO
     * 
     * @param Programa $programa
     * @param string $seccionId (I, II, III, IV, V, VI, VII, VIII, IX)
     * @param array $contenido
     * @param bool $triggerConversion (flag para conversión automática)
     * @return Programa
     */
    public static function updateSeccion(
        Programa $programa,
        string $seccionId,
        array $contenido,
        bool $triggerConversion = false
    ): Programa {
        return DB::transaction(function () use ($programa, $seccionId, $contenido, $triggerConversion) {
            $data = $programa->data_syllabus ?? [];
            $secciones = $data['secciones'] ?? [];

            // Encontrar y actualizar la sección por ID
            $found = false;
            foreach ($secciones as &$seccion) {
                if ($seccion['id'] === $seccionId) {
                    $seccion['contenido'] = $contenido;
                    $seccion['ultima_modificacion'] = now()->toIso8601String();
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new \Exception("Sección {$seccionId} no encontrada en la estructura del programa");
            }

            $data['secciones'] = $secciones;
            $data['timestamp'] = now()->toIso8601String();

            // Determinar si realizar conversión automática
            $updates = [
                'data_syllabus' => $data,
                'fecha_modificacion' => now(),
            ];

            // Lógica de conversión: BASICO -> COMPLETO
            if ($triggerConversion && $programa->isBasicoCompleto() && $programa->isBasico()) {
                // Cambiar tipo_syllabus a COMPLETO
                $data['metadata']['tipo_syllabus'] = 'COMPLETO';
                $updates['data_syllabus'] = $data;
                $updates['estado'] = 'COMPLETO';  // Cambiar estado también
            }

            $programa->update($updates);

            return $programa->fresh();
        });
    }

    /**
     * Actualiza una sección específica por orden (antiguo método - mantener para compatibilidad)
     */
    public static function updateSeccionByOrden(
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
     * Estados válidos: BORRADOR, BASICO_COMPLETO, COMPLETO, APROBADO, PUBLICADO
     */
    public static function changeStatus(
        Programa $programa,
        string $newStatus
    ): Programa {
        $validStatuses = ['BORRADOR', 'BASICO_COMPLETO', 'COMPLETO', 'APROBADO', 'PUBLICADO'];

        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException(
                "Estado '$newStatus' no válido. Opciones: " . implode(', ', $validStatuses)
            );
        }

        $programa->update(['estado' => $newStatus]);
        return $programa->fresh();
    }

    /**
     * Instancia un programa vacío (shell) para un curso.
     * 
     * El administrador (o docente) crea el "contenedor" del programa en estado BORRADOR.
     * El docente luego lo completa sección por sección y lo marca como listo.
     *
     * @param Curso    $curso        Curso al que pertenece el programa
     * @param Usuario  $createdBy    Usuario que instancia (generalmente el administrador)
     * @param string   $tipoSyllabus 'BASICO' | 'COMPLETO'
     * @throws \Exception Si ya existe un programa activo que no permite nueva versión
     * @return Programa
     */
    public static function instantiate(
        Curso $curso,
        Usuario $createdBy,
        string $tipoSyllabus = 'BASICO'
    ): Programa {
        if (!in_array($tipoSyllabus, ['BASICO', 'COMPLETO'])) {
            throw new \InvalidArgumentException("tipo_syllabus debe ser 'BASICO' o 'COMPLETO'");
        }

        return DB::transaction(function () use ($curso, $createdBy, $tipoSyllabus) {
            $existing = Programa::where('id_curso', $curso->id_curso)
                ->where('es_actual', true)
                ->first();

            // No permitir nueva instanciación si ya hay un programa activo pendiente de completar
            if ($existing && in_array($existing->estado, ['BORRADOR', 'BASICO_COMPLETO', 'COMPLETO'])) {
                throw new \Exception(
                    'Ya existe un programa activo en estado "' . $existing->estado . '" para este curso. ' .
                    'Aprueba o elimina el programa existente antes de instanciar uno nuevo.'
                );
            }

            $newVersion = $existing ? $existing->version_programa + 1 : 1;

            if ($existing) {
                $existing->update(['es_actual' => false]);
            }

            // Estructura base generada desde los datos del curso (Content vacío por sección)
            $syllabus = SyllabusStructure::for($curso);
            $syllabus['metadata']['tipo_syllabus'] = $tipoSyllabus;

            return Programa::create([
                'id_curso'         => $curso->id_curso,
                'version_programa' => $newVersion,
                'estado'           => 'BORRADOR',
                'data_syllabus'    => $syllabus,
                'creado_por'       => $createdBy->id_usuario,
                'es_actual'        => true,
                'fecha_creacion'   => now(),
            ]);
        });
    }

    /**
     * Marca la versión básica del programa como completada (BORRADOR → BASICO_COMPLETO).
     * 
     * Una vez en BASICO_COMPLETO el programa es visible para alumnos, docentes y administradores.
     * No requiere aprobación.
     *
     * @throws \Exception Si el programa no puede transicionar
     */
    public static function marcarBasicoCompleto(Programa $programa): Programa
    {
        if (!in_array($programa->estado, ['BORRADOR'])) {
            throw new \Exception(
                "Solo se puede completar la versión básica desde estado BORRADOR. " .
                "Estado actual: {$programa->estado}"
            );
        }

        if ($programa->getTipoSyllabus() !== 'BASICO') {
            throw new \Exception(
                "Este programa es de tipo " . $programa->getTipoSyllabus() . ". " .
                "Use 'enviarParaRevision' para programas COMPLETO."
            );
        }

        $programa->update([
            'estado'         => 'BASICO_COMPLETO',
            'fecha_entrega'  => now(),
        ]);

        return $programa->fresh();
    }

    /**
     * Envía el programa completo para revisión (BORRADOR|BASICO_COMPLETO → COMPLETO).
     * 
     * Solo el programa COMPLETO requiere aprobación por parte del administrador/jefe.
     * La fecha de entrega queda registrada para control de plazos.
     *
     * @throws \Exception Si el programa no puede transicionar
     */
    public static function enviarParaRevision(Programa $programa): Programa
    {
        if (!in_array($programa->estado, ['BORRADOR', 'BASICO_COMPLETO'])) {
            throw new \Exception(
                "Solo se puede enviar para revisión desde estado BORRADOR o BASICO_COMPLETO. " .
                "Estado actual: {$programa->estado}"
            );
        }

        if ($programa->getTipoSyllabus() !== 'COMPLETO') {
            // Actualizar tipo cuando se promueve desde BASICO
            DB::transaction(function () use ($programa) {
                $data = $programa->data_syllabus ?? [];
                $data['metadata']['tipo_syllabus'] = 'COMPLETO';
                $programa->update([
                    'estado'         => 'COMPLETO',
                    'data_syllabus'  => $data,
                    'fecha_entrega'  => now(),
                ]);
            });
        } else {
            $programa->update([
                'estado'        => 'COMPLETO',
                'fecha_entrega' => now(),
            ]);
        }

        return $programa->fresh();
    }

    /**
     * Obtiene las secciones requeridas según el tipo de syllabus
     * 
     * @param Programa $programa
     * @return array
     */
    public static function getRequiredSecciones(Programa $programa): array
    {
        $tipoSyllabus = $programa->getTipoSyllabus();
        
        return $tipoSyllabus === 'BASICO'
            ? ['I', 'II', 'VI', 'VII', 'VIII']
            : ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];
    }

    /**
     * Valida si el programa tiene todas las secciones requeridas con contenido
     * 
     * @param Programa $programa
     * @return bool
     */
    public static function isComplete(Programa $programa): bool
    {
        $secciones = self::getSecciones($programa);
        $required = self::getRequiredSecciones($programa);

        foreach ($required as $seccionId) {
            if (!isset($secciones[$seccionId]) || empty($secciones[$seccionId]['contenido'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcula el porcentaje de completitud según secciones requeridas
     * 
     * @param Programa $programa
     * @return int (0-100)
     */
    public static function calculateCompletenessPercentage(Programa $programa): int
    {
        $secciones = self::getSecciones($programa);
        $required = self::getRequiredSecciones($programa);
        $completed = 0;

        foreach ($required as $seccionId) {
            if (isset($secciones[$seccionId]) && !empty($secciones[$seccionId]['contenido'])) {
                $completed++;
            }
        }

        return count($required) > 0 ? (int)(($completed / count($required)) * 100) : 0;
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
            'tipo_syllabus' => $programa->getTipoSyllabus(),
            'fecha_creacion' => $programa->fecha_creacion,
            'completud' => self::calculateCompletenessPercentage($programa),
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
