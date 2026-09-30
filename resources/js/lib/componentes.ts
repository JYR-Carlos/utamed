/**
 * Orden CTL de los componentes de un curso: Cátedra, Taller, Laboratorio.
 * Espejo de App\Models\Curso\TipoComponente::PRIORIDAD (FEAT-01); los tipos
 * desconocidos van al final.
 */
const PRIORIDAD: Record<string, number> = {
  CATEDRA: 1,
  CÁTEDRA: 1,
  TALLER: 2,
  LABORATORIO: 3,
};

export function prioridadComponente(tipo: string | null | undefined): number {
  return PRIORIDAD[(tipo ?? '').trim().toUpperCase()] ?? 99;
}

/** Copia ordenada por prioridad CTL y, a igual tipo, por id_componente. */
export function ordenarComponentesCTL<T extends { id_componente: number }>(
  componentes: readonly T[],
  tipoDe: (c: T) => string | null | undefined,
): T[] {
  return [...componentes].sort(
    (a, b) =>
      prioridadComponente(tipoDe(a)) - prioridadComponente(tipoDe(b)) ||
      a.id_componente - b.id_componente,
  );
}
