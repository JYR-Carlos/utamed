<script lang="ts">
  import type { Rubrica } from '@/types/rubrica';

  interface Props {
    rubrica: Rubrica;
    puntaje_obtenido?: number | null;
    /** Puntos obtenidos por criterio (mismo orden que rubrica.niveles). Cuando se pasa, muestra "Tu nivel". */
    puntajePorNivel?: number[] | null;
    retroalimentacion?: string;
    modoLectura?: boolean;
  }

  let {
    rubrica,
    puntaje_obtenido = null,
    puntajePorNivel = null,
    retroalimentacion,
    modoLectura = false,
  }: Props = $props();

  const maxEscalas = $derived(rubrica?.niveles?.[0]?.escalas?.length ?? 3);
</script>

<div class="rubric-wrap">
  <table class="rubric-table">
    <thead>
      <tr>
        <th>Criterio de Evaluación</th>
        <th colspan={maxEscalas} class="center">Niveles de Desempeño</th>
      </tr>
    </thead>
    <tbody>
      {#each rubrica.niveles as nivel, ci}
        <tr>
          <td class="rc-name">
            <div class="rc-name-title">{nivel.nombre}</div>
            <div class="rc-name-desc">{nivel.descripcion}</div>
            <div class="rc-name-max">Máximo: {nivel.puntaje_total} pts</div>
          </td>
          {#each nivel.escalas as escala}
            {@const isEarned = puntajePorNivel != null && puntajePorNivel[ci] === escala.puntos}
            <td class="rc-level {isEarned ? 'is-earned' : ''}">
              <span class="rc-level-pts">{escala.puntos} pts</span>
              <div class="rc-level-text">{escala.criterio}</div>
              {#if isEarned}
                <span class="rc-level-earned-tag">Tu nivel</span>
              {/if}
            </td>
          {/each}
        </tr>
      {/each}
    </tbody>
  </table>

  {#if retroalimentacion}
    <div class="rubric-fb">
      <div class="rubric-fb-label">Retroalimentación del Docente</div>
      <p class="rubric-fb-text">{retroalimentacion}</p>
    </div>
  {/if}
</div>

<style>
  /* Self-contained CSS variables so the component works regardless of parent scope */
  .rubric-wrap {
    --primary: #1b3158;
    --primary-tint: #f1f4fa;
    --accent: #7a1f2b;
    --gold: #b58a3c;
    --gold-soft: #f5ebd2;
    --gold-ink: #6e5217;
    --surface: #ffffff;
    --surface-2: #f4f6fa;
    --line: #e2dbc8;
    --ink-1: #11141b;
    --ink-2: #2c3142;
    --ink-3: #5a6075;
    --ink-4: #8a8e9c;
    --font-sans: 'Inter Tight', system-ui, sans-serif;
    --font-display: 'Fraunces', 'Inter Tight', Georgia, serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;

    background: var(--surface);
  }

  /* ── Table ─────────────────────────────────────────────── */
  .rubric-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    font-family: var(--font-sans);
    color: var(--ink-1);
  }

  .rubric-table thead th {
    background: var(--surface-2);
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: var(--ink-4);
    padding: 14px 20px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
    z-index: 1;
  }
  .rubric-table thead th.center {
    text-align: center;
  }

  .rubric-table td {
    padding: 18px 20px;
    border-bottom: 1px solid var(--line);
    vertical-align: top;
    background: var(--surface);
  }
  .rubric-table tr:last-child td {
    border-bottom: 0;
  }

  /* ── Criterion name column ──────────────────────────────── */
  .rc-name {
    width: 240px;
    min-width: 180px;
    border-right: 1px solid var(--line);
  }
  .rc-name-title {
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 600;
    letter-spacing: -0.005em;
    margin-bottom: 4px;
    color: var(--ink-1);
    line-height: 1.2;
  }
  .rc-name-desc {
    font-size: 12.5px;
    color: var(--ink-3);
    line-height: 1.4;
    font-style: italic;
    margin-bottom: 10px;
  }
  .rc-name-max {
    font-size: 10.5px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--accent);
    font-weight: 700;
  }

  /* ── Performance level cells ────────────────────────────── */
  .rc-level {
    border-right: 1px solid var(--line);
    position: relative;
    transition: background 0.15s;
  }
  .rc-level:last-child {
    border-right: 0;
  }

  .rc-level.is-earned {
    background: var(--gold-soft) !important;
    box-shadow: inset 3px 0 0 var(--gold);
  }

  .rc-level-pts {
    display: inline-block;
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--primary);
    padding: 3px 9px;
    border-radius: 4px;
    background: var(--primary-tint);
    margin-bottom: 10px;
  }
  .rc-level.is-earned .rc-level-pts {
    background: var(--gold);
    color: var(--primary);
  }

  .rc-level-text {
    font-size: 13px;
    line-height: 1.5;
    color: var(--ink-2);
  }

  .rc-level-earned-tag {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--gold-ink);
    background: var(--surface);
    padding: 3px 6px;
    border-radius: 3px;
    border: 1px solid var(--gold);
  }

  /* ── Feedback block ──────────────────────────────────────── */
  .rubric-fb {
    padding: 20px 24px;
    background: var(--surface-2);
    border-top: 1px solid var(--line);
  }
  .rubric-fb-label {
    font-size: 10.5px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--ink-4);
    font-weight: 700;
    margin-bottom: 8px;
  }
  .rubric-fb-text {
    font-size: 14px;
    line-height: 1.6;
    color: var(--ink-2);
    margin: 0;
  }
</style>
