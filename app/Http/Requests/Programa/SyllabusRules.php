<?php

namespace App\Http\Requests\Programa;

/**
 * Reglas de validación del payload de secciones del syllabus.
 *
 * Punto único para los tres consumidores (admin, docente y ayudante), que antes
 * mantenían copias divergentes: la del ayudante era mucho más laxa, de modo que
 * el rol de menor privilegio escribía el JSONB con menos controles que el titular.
 *
 * ## Por qué no hay regla sobre la clave padre `secciones`
 *
 * Con `'secciones' => 'required|array'`, `validated()` devuelve el **subárbol
 * completo** de esa clave, no sólo las rutas con regla propia: cualquier clave
 * extra que enviara el cliente sobrevivía hasta la columna JSONB `data_syllabus`.
 * Sin la regla padre, `validated()` reconstruye `secciones` únicamente con las
 * rutas declaradas aquí, que pasan a funcionar como allowlist. La presencia del
 * bloque la siguen exigiendo las reglas `required` de la sección I.
 *
 * ## Topes de volumen
 *
 * Cada colección lleva `max:` explícito. Las reglas `nullable|array` sin tope
 * dejaban abierta la escritura de un JSONB de tamaño arbitrario —agotamiento de
 * almacenamiento— aunque la forma de los datos sí estuviera acotada por los DTO
 * de `App\Syllabus`.
 */
final class SyllabusRules
{
    /** Longitud máxima de los textos largos (presentación, estándares, normativa). */
    private const MAX_TEXTO_LARGO = 20000;

    /** Longitud máxima de títulos, ítems y resultados sueltos. */
    private const MAX_TEXTO_CORTO = 2000;

    /**
     * Reglas para el tipo de syllabus indicado.
     *
     * @param  string  $tipoSyllabus  'BASICO' o 'COMPLETO'
     * @return array<string, mixed>
     */
    public static function forTipo(string $tipoSyllabus): array
    {
        return $tipoSyllabus === 'BASICO'
            ? self::basico()
            : self::completo();
    }

    /**
     * Sección I (identificación): común a ambos tipos.
     *
     * @return array<string, mixed>
     */
    private static function seccionI(): array
    {
        return [
            'secciones.I.contenido.nombre_asignatura' => 'required|string|max:255',
            'secciones.I.contenido.codigo' => 'required|string|max:50',
            'secciones.I.contenido.creditos_sct' => 'required|integer|min:1|max:50',
            'secciones.I.contenido.horas.catedra' => 'required|integer|min:0|max:1000',
            'secciones.I.contenido.horas.taller' => 'required|integer|min:0|max:1000',
            'secciones.I.contenido.horas.laboratorio' => 'required|integer|min:0|max:1000',
            'secciones.I.contenido.categoria' => 'required|string|in:Obligatorio,Electivo,Nivelación,Complementaria',
        ];
    }

    /**
     * Sección VI (unidades): común a ambos tipos.
     *
     * @return array<string, mixed>
     */
    private static function seccionVI(): array
    {
        return [
            'secciones.VI.contenido.unidades' => 'nullable|array|max:60',
            'secciones.VI.contenido.unidades.*.numero' => 'nullable|integer|min:0|max:1000',
            'secciones.VI.contenido.unidades.*.titulo' => 'nullable|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VI.contenido.unidades.*.contenidos_items' => 'nullable|array|max:100',
            'secciones.VI.contenido.unidades.*.contenidos_items.*.item' => 'nullable|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VI.contenido.unidades.*.resultados_aprendizaje' => 'nullable|array|max:100',
            'secciones.VI.contenido.unidades.*.resultados_aprendizaje.*.resultado' => 'nullable|string|max:' . self::MAX_TEXTO_CORTO,
        ];
    }

    /**
     * Sección VIII (bibliografía / recursos): común a ambos tipos.
     *
     * @return array<string, mixed>
     */
    private static function seccionVIII(): array
    {
        return [
            'secciones.VIII.contenido.recursos' => 'nullable|array|max:300',
            'secciones.VIII.contenido.recursos.*.descripcion' => 'nullable|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VIII.contenido.recursos.*.tipo' => 'nullable|string|in:Libro,Documentación Online,Video,Herramienta Software,Base de Datos',
            'secciones.VIII.contenido.recursos.*.ubicacion' => 'nullable|string|max:' . self::MAX_TEXTO_CORTO,
        ];
    }

    /**
     * BASICO: secciones I, II, VI, VII (actividades) y VIII.
     *
     * @return array<string, mixed>
     */
    private static function basico(): array
    {
        return [
            ...self::seccionI(),

            // Sección II: Presentación - Permitir enviar vacío para rellenar después
            'secciones.II.contenido.texto' => 'nullable|string|max:' . self::MAX_TEXTO_LARGO,

            ...self::seccionVI(),

            // Sección VII: Actividades de Aprendizaje - Permitir enviar vacío para rellenar después
            'secciones.VII.contenido.actividades' => 'nullable|array|max:200',
            'secciones.VII.contenido.actividades.*.id_actividad' => 'nullable|integer',
            'secciones.VII.contenido.actividades.*.nombre' => 'nullable|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VII.contenido.actividades.*.tipo' => 'nullable|string|max:100',
            // El wizard envía además la unidad a la que pertenece la actividad y el
            // DTO ActividadSyllabus la conserva: sin regla propia se perdería al
            // reconstruir `secciones` desde las rutas validadas.
            'secciones.VII.contenido.actividades.*.nombre_unidad' => 'nullable|string|max:' . self::MAX_TEXTO_CORTO,

            ...self::seccionVIII(),
        ];
    }

    /**
     * COMPLETO: las nueve secciones.
     *
     * @return array<string, mixed>
     */
    private static function completo(): array
    {
        return [
            ...self::seccionI(),

            // Sección II: Presentación - Permitir enviar vacío para rellenar después
            'secciones.II.contenido.texto' => 'nullable|string|max:' . self::MAX_TEXTO_LARGO,

            // Sección III: Estándares
            'secciones.III.contenido.texto' => 'nullable|string|max:' . self::MAX_TEXTO_LARGO,

            // Sección IV: Competencias
            'secciones.IV.contenido.competencias_especificas' => 'required|array|min:1|max:100',
            'secciones.IV.contenido.competencias_especificas.*.titulo' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.IV.contenido.competencias_genericas' => 'required|array|min:1|max:100',
            'secciones.IV.contenido.competencias_genericas.*.titulo' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.IV.contenido.subcompetencias' => 'nullable|array|max:100',
            'secciones.IV.contenido.subcompetencias.*.titulo' => 'required_if:secciones.IV.contenido.subcompetencias,!null|string|max:' . self::MAX_TEXTO_CORTO,

            // Sección V: Evaluación Diagnóstica
            'secciones.V.contenido.items' => 'required|array|min:1|max:100',
            'secciones.V.contenido.items.*.titulo' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.V.contenido.items.*.descripcion' => 'nullable|string|max:' . self::MAX_TEXTO_LARGO,

            ...self::seccionVI(),

            // Sección VII: Planificación
            'secciones.VII.contenido.resultados_aprendizaje.titulo' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VII.contenido.resultados_aprendizaje.items' => 'required|array|min:1|max:200',
            'secciones.VII.contenido.resultados_aprendizaje.items.*.resultado' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VII.contenido.metodologia.titulo' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VII.contenido.metodologia.tipo_estrategia' => 'required|string|max:' . self::MAX_TEXTO_LARGO,
            'secciones.VII.contenido.evaluacion.titulo' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.VII.contenido.evaluacion.tipo_evaluacion' => 'required|string|max:' . self::MAX_TEXTO_LARGO,

            ...self::seccionVIII(),

            // Sección IX: Aspectos Administrativos
            'secciones.IX.contenido.descripcion' => 'required|string|max:' . self::MAX_TEXTO_LARGO,
            'secciones.IX.contenido.ponderacion_optativa.porcentaje' => 'required|numeric|min:0|max:100',
            'secciones.IX.contenido.tabla_componentes' => 'required|array|min:1|max:50',
            'secciones.IX.contenido.tabla_componentes.*.componente' => 'required|string|max:' . self::MAX_TEXTO_CORTO,
            'secciones.IX.contenido.tabla_componentes.*.porcentaje' => 'required|numeric|min:0|max:100',
            'secciones.IX.contenido.tabla_componentes.*.genera_acta' => 'required|boolean',
            'secciones.IX.contenido.tabla_componentes.*.aprobacion_obligatoria' => 'nullable|boolean',
            'secciones.IX.contenido.tabla_componentes.*.asistencia_obligatoria' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
