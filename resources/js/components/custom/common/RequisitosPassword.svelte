<script lang="ts">
  /**
   * Lista reactiva de requisitos de la contraseña: mientras se escribe, cada
   * requisito pasa de gris a verde. Si se pasa `confirmacion`, agrega una
   * línea que dice si ambas coinciden. Las reglas viven en `@/lib/password`,
   * en espejo de la política del backend.
   */
  import { Check, Circle, CircleCheck, X } from 'lucide-svelte';
  import { REQUISITOS_PASSWORD } from '@/lib/password';

  interface Props {
    password: string;
    /** Valor del campo de confirmación; undefined = no mostrar esa línea. */
    confirmacion?: string;
    /**
     * 'oscuro' para fondos oscuros (p. ej. el cambio obligatorio). 'dm' para
     * la vista del alumno: lo que falta queda neutro mientras se escribe, el
     * rojo es sólo para el error del servidor.
     */
    tono?: 'claro' | 'oscuro' | 'dm';
  }

  let { password, confirmacion = undefined, tono = 'claro' }: Props = $props();

  type Estado = 'ok' | 'falta' | 'neutro';

  const COLORES: Record<typeof tono, Record<Estado, string>> = {
    claro: { ok: 'text-emerald-700', falta: 'text-red-600', neutro: 'text-slate-500' },
    // Fondo oscuro fijo (no cambia con el tema): hex en vez de la paleta, que el modo oscuro remapea.
    oscuro: { ok: 'text-[#5EE9B5]', falta: 'text-[#FFA2A2]', neutro: 'text-[#C4BFE0]/70' },
    dm: { ok: 'font-semibold text-dm-ink', falta: 'text-dm-ink-2', neutro: 'text-dm-ink-2' },
  };
  const c = $derived(COLORES[tono]);
  const dm = $derived(tono === 'dm');

  function estado(ok: boolean, escrito: boolean): Estado {
    return ok ? 'ok' : escrito && !dm ? 'falta' : 'neutro';
  }

  const escribio = $derived((password ?? '').length > 0);
  const lineas = $derived([
    ...REQUISITOS_PASSWORD.map((r) => ({ id: r.id as string, texto: r.texto, estado: estado(r.cumple(password ?? ''), escribio) })),
    ...(confirmacion === undefined
      ? []
      : [{ id: 'confirmacion', texto: 'La confirmación coincide', estado: estado(confirmacion.length > 0 && confirmacion === password, confirmacion.length > 0) }]),
  ]);
</script>

<ul
  class={dm ? 'grid gap-x-4 gap-y-2 text-sm leading-5 sm:grid-cols-2' : 'grid gap-1 text-xs sm:grid-cols-2'}
  aria-live="polite"
  aria-label="Requisitos de la contraseña"
>
  {#each lineas as l (l.id)}
    <li class="flex items-center {dm ? 'gap-2' : 'gap-1.5'} {c[l.estado]}">
      {#if dm}
        {#if l.estado === 'ok'}
          <CircleCheck class="size-4 shrink-0 text-dm-ok" aria-hidden="true" />
        {:else}
          <Circle class="size-4 shrink-0 text-dm-ink-3" aria-hidden="true" />
        {/if}
      {:else if l.estado === 'ok'}
        <Check class="h-3.5 w-3.5 shrink-0" />
      {:else if l.estado === 'falta'}
        <X class="h-3.5 w-3.5 shrink-0" />
      {:else}
        <Circle class="h-3 w-3 shrink-0" />
      {/if}
      <span>{l.texto}</span>
      <span class="sr-only">{l.estado === 'ok' ? '(cumple)' : '(falta)'}</span>
    </li>
  {/each}
</ul>
