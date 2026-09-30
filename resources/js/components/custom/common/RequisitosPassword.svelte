<script lang="ts">
  /**
   * Lista reactiva de requisitos de la contraseña: mientras se escribe, cada
   * requisito pasa de gris a verde. Si se pasa `confirmacion`, agrega una
   * línea que dice si ambas coinciden. Las reglas viven en `@/lib/password`,
   * en espejo de la política del backend.
   */
  import { Check, Circle, X } from 'lucide-svelte';
  import { REQUISITOS_PASSWORD } from '@/lib/password';

  interface Props {
    password: string;
    /** Valor del campo de confirmación; undefined = no mostrar esa línea. */
    confirmacion?: string;
    /** 'oscuro' para fondos oscuros (p. ej. el cambio obligatorio). */
    tono?: 'claro' | 'oscuro';
  }

  let { password, confirmacion = undefined, tono = 'claro' }: Props = $props();

  const COLORES = {
    claro: { ok: 'text-emerald-700', falta: 'text-red-600', neutro: 'text-slate-500' },
    oscuro: { ok: 'text-emerald-300', falta: 'text-red-300', neutro: 'text-[#C4BFE0]/70' },
  };
  const c = $derived(COLORES[tono]);

  const estados = $derived(REQUISITOS_PASSWORD.map((r) => ({ ...r, ok: r.cumple(password ?? '') })));
  const escribio = $derived((password ?? '').length > 0);
  const coinciden = $derived(confirmacion !== undefined && confirmacion.length > 0 && confirmacion === password);
</script>

<ul class="grid gap-1 text-xs sm:grid-cols-2" aria-live="polite" aria-label="Requisitos de la contraseña">
  {#each estados as r (r.id)}
    <li class="flex items-center gap-1.5 {r.ok ? c.ok : escribio ? c.falta : c.neutro}">
      {#if r.ok}
        <Check class="h-3.5 w-3.5 shrink-0" />
      {:else if escribio}
        <X class="h-3.5 w-3.5 shrink-0" />
      {:else}
        <Circle class="h-3 w-3 shrink-0" />
      {/if}
      <span>{r.texto}</span>
      <span class="sr-only">{r.ok ? '(cumple)' : '(falta)'}</span>
    </li>
  {/each}
  {#if confirmacion !== undefined}
    <li
      class="flex items-center gap-1.5 {coinciden ? c.ok : confirmacion.length > 0 ? c.falta : c.neutro}"
    >
      {#if coinciden}
        <Check class="h-3.5 w-3.5 shrink-0" />
      {:else if confirmacion.length > 0}
        <X class="h-3.5 w-3.5 shrink-0" />
      {:else}
        <Circle class="h-3 w-3 shrink-0" />
      {/if}
      <span>La confirmación coincide</span>
      <span class="sr-only">{coinciden ? '(cumple)' : '(falta)'}</span>
    </li>
  {/if}
</ul>
