<script lang="ts">
  import type { Nivel, Rubrica } from '@/types/rubrica';

  interface Props {
    rubrica: Rubrica;
    puntaje_obtenido?: number;
    retroalimentacion?: string;
    modoLectura?: boolean;
    resultado?: Record<string, string> | null;
  }

  let {
    rubrica,
    puntaje_obtenido = 0,
    retroalimentacion,
    modoLectura: modoRevision = false,
    resultado = null,
  }: Props = $props();

  

  //const maxEscalas = rubrica?.niveles?.[0]?.escalas.length || 0;

  // para evitar que la tabla se rompa horizontalmente.
  //const mostrarVertical = maxEscalas >= 4;
</script>

<div
  class="flex justify-center gap-20 text-center p-6 rounded-3xl border-2 border-primary/10"
>
  <div>
    <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
      Criterios
    </p>
    <p class="text-3xl font-black text-primary">
      {rubrica.niveles.length}
    </p>
  </div>

  <div>
    <p class="text-xs font-bold uppercase text-gray-500 tracking-widest">
      Puntaje Máximo
    </p>
    <p class="text-3xl font-black text-primary">
      {0} pts
    </p>
  </div>
</div>