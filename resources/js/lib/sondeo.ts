/**
 * Repite una petición mientras la pestaña está a la vista, para lo que debe
 * llegar solo (mensajes nuevos, «Visto por»). Hace lo que `router.poll` de
 * Inertia no: espera la respuesta antes de pedir otra vez, así un servidor lento
 * no acumula una cola, y en segundo plano no pide nada. Al volver a la pestaña
 * pide de inmediato.
 *
 * Devuelve la función que lo detiene.
 */
export function sondear(
  pedir: () => Promise<unknown>,
  { intervalo = 10_000, alEmpezar = false }: { intervalo?: number; alEmpezar?: boolean } = {},
): () => void {
  let espera: ReturnType<typeof setTimeout> | undefined;
  let pidiendo = false;
  let detenido = false;

  function programar() {
    clearTimeout(espera);
    if (!detenido && !document.hidden) espera = setTimeout(turno, intervalo);
  }

  async function turno() {
    if (detenido || pidiendo || document.hidden) return;
    clearTimeout(espera);
    pidiendo = true;
    try {
      await pedir();
    } catch {
      // Un refresco que falla no interrumpe la lectura: se reintenta en el siguiente.
    } finally {
      pidiendo = false;
      programar();
    }
  }

  document.addEventListener('visibilitychange', turno);
  if (alEmpezar) turno();
  else programar();

  return () => {
    detenido = true;
    clearTimeout(espera);
    document.removeEventListener('visibilitychange', turno);
  };
}
