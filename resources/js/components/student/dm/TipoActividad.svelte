<script lang="ts">
  /**
   * Tipo y modalidad de una actividad, cada uno con su ícono: sumativa (lleva
   * nota, en azul) o formativa, y grupal o individual. El texto va siempre: el
   * ícono ayuda a encontrarlo, no lo reemplaza.
   */
  import { Award, Sprout, User, Users } from 'lucide-svelte';

  interface Props {
    esSumativa: boolean;
    esGrupal: boolean;
    class?: string;
  }

  let { esSumativa, esGrupal, class: clase = '' }: Props = $props();

  const Tipo = $derived(esSumativa ? Award : Sprout);
  const Modalidad = $derived(esGrupal ? Users : User);
</script>

<span class="inline-flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[13px] leading-[18px] text-dm-ink-2 {clase}">
  <span class="inline-flex items-center gap-1 {esSumativa ? 'font-semibold text-dm-accion-texto' : ''}">
    <Tipo size={14} aria-hidden="true" class="shrink-0" />{esSumativa ? 'Sumativa' : 'Formativa'}
  </span>
  <span class="inline-flex items-center gap-1">
    <Modalidad size={14} aria-hidden="true" class="shrink-0" />{esGrupal ? 'Grupal' : 'Individual'}
  </span>
</span>
