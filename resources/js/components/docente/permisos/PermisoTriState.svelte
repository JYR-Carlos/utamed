<script lang="ts">
  /**
   * Control tri-estado (✕ Revocado | — Neutro | ✓ Asignado) en HTML + CSS puro
   * siguiendo la paleta institucional UTAMED (#002F6C navy, #DC2626 rojo, #FFFFFF superficie).
   *
   * Estados bloqueados diferenciados:
   * - 'heredado': bloqueado gris con candado (asignado en Curso o nivel superior)
   * - 'revocado': bloqueado claramente en rojo con atributo `title` nativo
   * - 'no_delegable': deshabilitado opaco con `title` explicativo
   */
  import { Check, AlertTriangle, Lock, X, Minus } from 'lucide-svelte';

  export type ValorPermiso = 'revocado' | 'neutro' | 'asignado';
  export type EstadoTransitorio = 'reposo' | 'guardando' | 'confirmado' | 'error';
  export type TipoBloqueo = 'heredado' | 'revocado' | 'no_delegable' | null;

  interface Props {
    valor: ValorPermiso;
    estado?: EstadoTransitorio;
    bloqueo?: TipoBloqueo;
    motivoBloqueo?: string | null;
    activoPorRol?: boolean;
    disabled?: boolean;
    compact?: boolean;
    onChange: (next: ValorPermiso) => void;
    label?: string;
  }

  let {
    valor,
    estado = 'reposo',
    bloqueo = null,
    motivoBloqueo = null,
    activoPorRol = false,
    disabled = false,
    compact = false,
    onChange,
    label = '',
  }: Props = $props();

  const estaBloqueado = $derived(disabled || estado === 'guardando' || bloqueo !== null);

  const tooltipText = $derived.by(() => {
    if (motivoBloqueo) return motivoBloqueo;
    if (bloqueo === 'heredado') return 'Heredado desde nivel superior (Curso) y no modificable en este componente.';
    if (bloqueo === 'revocado') return 'Inhabilitado por revocación en nivel superior (Curso).';
    if (bloqueo === 'no_delegable') return 'No tienes capacidad delegable sobre este permiso en este ámbito.';
    if (valor === 'neutro' && activoPorRol) {
      return 'Asignado por rol base — se puede modificar (✓ para fijar o ✕ para revocar)';
    }
    return label;
  });

  function seleccionar(next: ValorPermiso) {
    if (estaBloqueado || next === valor) return;
    onChange(next);
  }
</script>

<span class="utamed-tristate-wrap" title={tooltipText}>
  {#if bloqueo === 'revocado'}
    <span class="utamed-tristate-locked utamed-tristate-locked--revoked" class:utamed-tristate--compact={compact} aria-label={tooltipText}>
      <X class="h-3 w-3 shrink-0" />
      <Lock class="h-2.5 w-2.5 shrink-0" />
    </span>
  {:else if bloqueo === 'heredado'}
    <span class="utamed-tristate-locked utamed-tristate-locked--inherited" class:utamed-tristate--compact={compact} aria-label={tooltipText}>
      <Check class="h-3 w-3 shrink-0" />
      <Lock class="h-2.5 w-2.5 shrink-0" />
    </span>
  {:else}
    <div
      class="utamed-tristate"
      class:utamed-tristate--compact={compact}
      class:utamed-tristate--saving={estado === 'guardando'}
      class:utamed-tristate--error={estado === 'error'}
      class:utamed-tristate--disabled={estaBloqueado}
      role="radiogroup"
      aria-label={label}
    >
      <button
        type="button"
        role="radio"
        aria-checked={valor === 'revocado'}
        aria-label={`Revocar: ${label}`}
        disabled={estaBloqueado}
        class="utamed-tristate__btn utamed-tristate__btn--revoke"
        class:is-active={valor === 'revocado'}
        onclick={() => seleccionar('revocado')}
      >
        <X class="h-3 w-3" />
      </button>

      <button
        type="button"
        role="radio"
        aria-checked={valor === 'neutro'}
        aria-label={`Neutro: ${label}`}
        title={valor === 'neutro' && activoPorRol ? 'Asignado por rol base — se puede modificar (✓ para fijar o ✕ para revocar)' : undefined}
        disabled={estaBloqueado}
        class="utamed-tristate__btn utamed-tristate__btn--neutral"
        class:is-active={valor === 'neutro'}
        class:has-role-base={valor === 'neutro' && activoPorRol}
        onclick={() => seleccionar('neutro')}
      >
        <Minus class="h-3 w-3" />
        {#if valor === 'neutro' && activoPorRol}
          <span class="utamed-tristate__roledot" aria-hidden="true"></span>
        {/if}
      </button>

      <button
        type="button"
        role="radio"
        aria-checked={valor === 'asignado'}
        aria-label={`Asignar: ${label}`}
        disabled={estaBloqueado}
        class="utamed-tristate__btn utamed-tristate__btn--grant"
        class:is-active={valor === 'asignado'}
        onclick={() => seleccionar('asignado')}
      >
        <Check class="h-3 w-3" />
      </button>
    </div>
  {/if}

  {#if estado === 'guardando'}
    <span class="utamed-tristate-spinner" aria-label="Guardando"></span>
  {:else if estado === 'confirmado'}
    <Check class="h-3.5 w-3.5 shrink-0 text-emerald-600" />
  {:else if estado === 'error'}
    <AlertTriangle class="h-3.5 w-3.5 shrink-0 text-red-600" />
  {/if}
</span>

<style>
  .utamed-tristate-wrap {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    vertical-align: middle;
  }

  .utamed-tristate {
    display: inline-flex;
    align-items: stretch;
    border: 1px solid #d6d9e0;
    border-radius: 6px;
    background: #ffffff;
    overflow: hidden;
    height: 24px;
  }

  .utamed-tristate--compact {
    height: 22px;
  }

  .utamed-tristate--saving {
    opacity: 0.65;
  }

  .utamed-tristate--error {
    border-color: #ef4444;
    box-shadow: 0 0 0 1px #fecaca;
  }

  .utamed-tristate--disabled {
    opacity: 0.55;
  }

  .utamed-tristate__btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    padding: 0;
    border: 0;
    border-right: 1px solid #e5e7eb;
    background: #ffffff;
    color: #64748b;
    cursor: pointer;
    transition: background-color 0.12s ease, color 0.12s ease;
  }

  .utamed-tristate--compact .utamed-tristate__btn {
    width: 22px;
  }

  .utamed-tristate__btn:last-child {
    border-right: 0;
  }

  .utamed-tristate__btn:disabled {
    cursor: not-allowed;
  }

  .utamed-tristate__btn--revoke:hover:not(:disabled):not(.is-active) {
    background: #fef2f2;
    color: #b91c1c;
  }

  .utamed-tristate__btn--revoke.is-active {
    background: #dc2626;
    color: #ffffff;
  }

  .utamed-tristate__btn--neutral:hover:not(:disabled):not(.is-active) {
    background: #f1f5f9;
    color: #334155;
  }

  .utamed-tristate__btn--neutral.is-active {
    background: #f1f5f9;
    color: #1e293b;
  }

  .utamed-tristate__btn--neutral.has-role-base {
    background: #dbeafe;
    color: #1d4ed8;
    cursor: help;
    box-shadow: inset 0 0 0 1px #93c5fd;
  }

  .utamed-tristate__roledot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 5px;
    height: 5px;
    border-radius: 9999px;
    background: #1d4ed8;
  }

  .utamed-tristate__btn--grant:hover:not(:disabled):not(.is-active) {
    background: #eff6ff;
    color: #002f6c;
  }

  .utamed-tristate__btn--grant.is-active {
    background: #002f6c;
    color: #ffffff;
  }

  .utamed-tristate-locked {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    height: 24px;
    min-width: 54px;
    padding: 0 7px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: not-allowed;
  }

  .utamed-tristate-locked--inherited {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #475569;
  }

  .utamed-tristate-locked--revoked {
    background: #fef2f2;
    border: 1px solid #f87171;
    color: #b91c1c;
  }

  .utamed-tristate-spinner {
    width: 12px;
    height: 12px;
    border-radius: 9999px;
    border: 2px solid #cbd5e1;
    border-top-color: #002f6c;
    animation: utamed-tristate-spin 0.6s linear infinite;
    flex-shrink: 0;
  }

  @keyframes utamed-tristate-spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
