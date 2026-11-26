<script lang="ts">
  /**
   * Los tres pasos para recuperar el acceso: pedir el enlace, abrir el
   * correo y elegir la contraseña. Los hechos quedan en verde con un visto;
   * el actual, en el azul de acción.
   */
  import { Check } from 'lucide-svelte';

  interface Props {
    /** Índice del paso en curso (0–2); 3 = todos hechos. */
    actual: number;
  }

  let { actual }: Props = $props();

  const PASOS = ['Pide el enlace', 'Abre tu correo', 'Elige la contraseña'];
</script>

<ol aria-label="Pasos para recuperar el acceso" class="m-0 grid list-none grid-cols-3 p-0">
  {#each PASOS as texto, i (texto)}
    {@const hecho = i < actual}
    {@const enCurso = i === actual}
    <li aria-current={enCurso ? 'step' : undefined} class="flex min-w-0 flex-col gap-2">
      <div class="flex items-center gap-1.5 lg:gap-2">
        <span
          class="flex size-7 shrink-0 items-center justify-center rounded-md border-[1.5px] text-sm leading-5 font-semibold
            {hecho
            ? 'border-dm-ok bg-dm-ok-s text-dm-ok'
            : enCurso
              ? 'border-dm-accion bg-dm-accion text-dm-sobre-accion'
              : 'border-dm-control text-dm-ink-2'}"
        >
          {#if hecho}
            <Check
              size={16}
              strokeWidth={3}
              aria-hidden="true"
              class="animate-dm-pop [animation-delay:0.2s] motion-reduce:animate-none"
            />
            <span class="sr-only">{i + 1} (hecho)</span>
          {:else}
            {i + 1}
          {/if}
        </span>
        {#if i < PASOS.length - 1}
          <span aria-hidden="true" class="relative me-1.5 h-0.5 flex-1 overflow-hidden bg-dm-linea lg:me-2">
            <span
              class="absolute inset-0 origin-left bg-dm-ok transition-transform delay-[250ms] duration-[600ms] ease-[cubic-bezier(0.2,0.7,0.2,1)] motion-reduce:transition-none
                {hecho ? 'scale-x-100' : 'scale-x-0'}"
            ></span>
          </span>
        {/if}
      </div>
      <span
        class="pe-1.5 text-[13px] leading-[18px] lg:pe-0 {enCurso ? 'font-semibold text-dm-ink' : 'font-medium text-dm-ink-2'}"
        >{texto}</span
      >
    </li>
  {/each}
</ol>
