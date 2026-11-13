<script lang="ts">
  /**
   * Campo de contraseña de las pantallas de ingreso: 48 px de alto, botón de
   * 44 px para mostrar u ocultar lo escrito y, debajo, el error o la ayuda.
   */
  import { campo } from '@/lib/dm';
  import { CircleX, Eye, EyeOff } from 'lucide-svelte';

  interface Props {
    id: string;
    name: string;
    etiqueta: string;
    /** Cómo nombra el lector de pantalla al campo en el botón del ojo. */
    nombreLector: string;
    autocomplete: 'current-password' | 'new-password';
    value: string;
    error?: string | null;
    ayuda?: string;
    /** Otros elementos que describen el campo (p. ej. la lista de requisitos). */
    describe?: string;
    disabled?: boolean;
  }

  let {
    id,
    name,
    etiqueta,
    nombreLector,
    autocomplete,
    value = $bindable(),
    error = null,
    ayuda,
    describe,
    disabled = false,
  }: Props = $props();

  let ver = $state(false);

  const describedby = $derived(
    [error ? `${id}-error` : ayuda ? `${id}-ayuda` : null, describe].filter(Boolean).join(' ') || undefined,
  );
</script>

<div class="flex flex-col gap-1.5">
  <label for={id} class="text-sm leading-5 font-medium">{etiqueta}</label>
  <div class="relative">
    <input
      {id}
      {name}
      type={ver ? 'text' : 'password'}
      required
      {autocomplete}
      {disabled}
      bind:value
      aria-invalid={error ? true : undefined}
      aria-describedby={describedby}
      class="{campo} min-h-12! pe-[52px]! text-base! aria-invalid:shadow-[0_0_0_1px_var(--color-dm-error)]"
    />
    <button
      type="button"
      aria-label={ver ? `Ocultar ${nombreLector}` : `Mostrar ${nombreLector}`}
      aria-pressed={ver}
      {disabled}
      onclick={() => (ver = !ver)}
      class="absolute end-0.5 top-0.5 flex size-11 cursor-pointer items-center justify-center rounded-md text-dm-ink-2 hover:text-dm-ink disabled:cursor-not-allowed"
    >
      {#if ver}
        <EyeOff size={18} aria-hidden="true" />
      {:else}
        <Eye size={18} aria-hidden="true" />
      {/if}
    </button>
  </div>
  {#if error}
    <span id="{id}-error" class="flex items-start gap-1.5 text-sm leading-5 text-dm-error">
      <CircleX size={16} aria-hidden="true" class="mt-0.5 shrink-0" />
      {error}
    </span>
  {:else if ayuda}
    <span id="{id}-ayuda" class="text-[13px] leading-[18px] text-dm-ink-2">{ayuda}</span>
  {/if}
</div>
