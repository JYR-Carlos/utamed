<?php

namespace App\Syllabus;

use App\Syllabus\Secciones\SeccionIContenido;
use App\Syllabus\Secciones\SeccionIVContenido;
use App\Syllabus\Secciones\SeccionIXContenido;
use App\Syllabus\Secciones\SeccionTextoContenido;
use App\Syllabus\Secciones\SeccionVContenido;
use App\Syllabus\Secciones\SeccionVIContenido;
use App\Syllabus\Secciones\SeccionVIIBasico;
use App\Syllabus\Secciones\SeccionVIICompleto;
use App\Syllabus\Secciones\SeccionVIIIContenido;

/**
 * Contenedor tipado de `data_syllabus.secciones` en su forma asociativa
 * (I..IX, cada una con `{contenido, ultima_modificacion?}`).
 *
 * No implementa ArrayAccess a propósito: cada consumidor accede vía la API
 * tipada (has/get/hasContenido/with/seccionI()..seccionIX()) en vez de indexar
 * un array crudo, para evitar la ambigüedad que causaba el bug de
 * ProgramaService::updateSeccion() (buscaba una clave 'id' que nunca existe
 * en este formato asociativo).
 */
final class SyllabusSecciones
{
    public const ROMANOS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'];

    /** @var array<string, array{contenido: object, ultima_modificacion: ?string}> */
    private readonly array $secciones;

    /** @param array<string, array{contenido: object, ultima_modificacion: ?string}> $secciones */
    private function __construct(array $secciones)
    {
        $this->secciones = $secciones;
    }

    public static function fromArray(array $data, ?SyllabusTipo $tipo = null): self
    {
        $secciones = [];
        foreach ($data as $seccionId => $envoltorio) {
            if (!is_array($envoltorio) || !isset($envoltorio['contenido'])) {
                continue;
            }
            $secciones[$seccionId] = [
                'contenido' => self::contenidoFromArray((string) $seccionId, $envoltorio['contenido'], $tipo),
                'ultima_modificacion' => $envoltorio['ultima_modificacion'] ?? null,
            ];
        }

        return new self($secciones);
    }

    public static function contenidoFromArray(string $seccionId, array $contenido, ?SyllabusTipo $tipo = null): object
    {
        return match ($seccionId) {
            'I' => SeccionIContenido::fromArray($contenido),
            'II', 'III' => SeccionTextoContenido::fromArray($contenido),
            'IV' => SeccionIVContenido::fromArray($contenido),
            'V' => SeccionVContenido::fromArray($contenido),
            'VI' => SeccionVIContenido::fromArray($contenido),
            // La forma real del contenido (claves presentes) es más confiable que
            // metadata.tipo_syllabus: algunos consumidores llaman esto con datos
            // parciales donde la metadata puede faltar o estar desactualizada.
            'VII' => (isset($contenido['metodologia']) || isset($contenido['resultados_aprendizaje']) || isset($contenido['evaluacion'])) && $tipo !== SyllabusTipo::Basico
                ? SeccionVIICompleto::fromArray($contenido)
                : SeccionVIIBasico::fromArray($contenido),
            'VIII' => SeccionVIIIContenido::fromArray($contenido),
            'IX' => SeccionIXContenido::fromArray($contenido),
            default => throw new \InvalidArgumentException("Sección desconocida: {$seccionId}"),
        };
    }

    public function has(string $seccionId): bool
    {
        return isset($this->secciones[$seccionId]);
    }

    public function get(string $seccionId): ?object
    {
        return $this->secciones[$seccionId]['contenido'] ?? null;
    }

    public function ultimaModificacion(string $seccionId): ?string
    {
        return $this->secciones[$seccionId]['ultima_modificacion'] ?? null;
    }

    /** True si la sección existe y su contenido no está vacío. */
    public function hasContenido(string $seccionId): bool
    {
        return $this->has($seccionId) && !empty((array) $this->get($seccionId));
    }

    /** Copia inmutable con una sección reemplazada (agregada si no existía). */
    public function with(string $seccionId, object $contenido, ?string $ultimaModificacion = null): self
    {
        $secciones = $this->secciones;
        $secciones[$seccionId] = ['contenido' => $contenido, 'ultima_modificacion' => $ultimaModificacion];

        return new self($secciones);
    }

    public function seccionI(): ?SeccionIContenido
    {
        return $this->get('I');
    }

    public function seccionII(): ?SeccionTextoContenido
    {
        return $this->get('II');
    }

    public function seccionIII(): ?SeccionTextoContenido
    {
        return $this->get('III');
    }

    public function seccionIV(): ?SeccionIVContenido
    {
        return $this->get('IV');
    }

    public function seccionV(): ?SeccionVContenido
    {
        return $this->get('V');
    }

    public function seccionVI(): ?SeccionVIContenido
    {
        return $this->get('VI');
    }

    /** @return SeccionVIIBasico|SeccionVIICompleto|null */
    public function seccionVII(): ?object
    {
        return $this->get('VII');
    }

    public function seccionVIII(): ?SeccionVIIIContenido
    {
        return $this->get('VIII');
    }

    public function seccionIX(): ?SeccionIXContenido
    {
        return $this->get('IX');
    }

    public function toArray(): array
    {
        $out = [];
        foreach ($this->secciones as $seccionId => $envoltorio) {
            $out[$seccionId] = array_filter([
                'contenido' => $envoltorio['contenido']->toArray(),
                'ultima_modificacion' => $envoltorio['ultima_modificacion'],
            ], fn ($v) => $v !== null);
        }

        return $out;
    }
}
