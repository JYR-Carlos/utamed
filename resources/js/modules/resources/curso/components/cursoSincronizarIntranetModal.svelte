<script lang="ts">
  /**
   * Modal de sincronización de un curso con Intranet: "mirar antes de tocar".
   *
   * No escribe nada al abrirse. Flujo:
   *   1. Aviso preventivo (riesgo de duplicar si ya se sincronizó antes).
   *   2. Preview (GET): arma el reporte de componentes detectadas + advertencias,
   *      sin tocar la base de datos.
   *   3. La persona revisa, puede des-marcar componentes puntuales, y decide:
   *      Cancelar, o Confirmar (ejecuta sólo lo que quedó marcado).
   *   4. Resultado final con lo creado, lo que ya existía y las advertencias.
   */
  import type { Curso } from '../types/curso.types';
  import type { ComponenteDetectada, ResultadoSincronizacionComponentes } from '@/types/admin.types';

  interface Props {
    isOpen: boolean;
    curso: Curso | null;
    onClose: () => void;
    onSuccess: (mensaje: string) => void;
  }

  let { isOpen, curso, onClose, onSuccess }: Props = $props();

  type Paso = 'aviso' | 'cargando' | 'revisar' | 'ejecutando' | 'resultado' | 'error';

  let paso = $state<Paso>('aviso');
  let origen = $state<'INTRANET' | 'PLAN' | null>(null);
  let componentes = $state<ComponenteDetectada[]>([]);
  let advertencias = $state<string[]>([]);
  let aceptados = $state<Set<number>>(new Set());
  let inscribirAlumnos = $state(false);
  let errorMsg = $state('');
  let resultado = $state<ResultadoSincronizacionComponentes | null>(null);

  $effect(() => {
    if (isOpen) {
      paso = 'aviso';
      origen = null;
      componentes = [];
      advertencias = [];
      aceptados = new Set();
      inscribirAlumnos = false;
      errorMsg = '';
      resultado = null;
    }
  });

  async function cargarPreview() {
    if (!curso) return;
    paso = 'cargando';
    try {
      const res = await fetch(`/admin/cursos/${curso.id_curso}/sincronizar-intranet/preview`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error('No se pudo consultar la Intranet.');
      const json = await res.json();
      origen = json.origen;
      componentes = json.componentes ?? [];
      advertencias = json.advertencias ?? [];
      aceptados = new Set(componentes.map((c) => c.id_tipo_componente));
      paso = 'revisar';
    } catch (e: any) {
      errorMsg = e?.message ?? 'Error al consultar la Intranet.';
      paso = 'error';
    }
  }

  function toggleAceptado(id: number) {
    const next = new Set(aceptados);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    aceptados = next;
  }

  async function confirmar() {
    if (!curso || aceptados.size === 0) return;
    paso = 'ejecutando';
    try {
      const res = await fetch(`/admin/cursos/${curso.id_curso}/sincronizar-intranet`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
          tipos_componente_ids: [...aceptados],
          inscribir_automaticamente: inscribirAlumnos,
        }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json?.error ?? 'No se pudo sincronizar.');
      resultado = json as ResultadoSincronizacionComponentes;
      paso = 'resultado';
    } catch (e: any) {
      errorMsg = e?.message ?? 'Error al sincronizar con la Intranet.';
      paso = 'error';
    }
  }

  function cerrarConExito() {
    const creadas = resultado?.componentes_creadas.length ?? 0;
    const existentes = resultado?.componentes_existentes.length ?? 0;
    let msg = `Sincronización completada: ${creadas} componente${creadas === 1 ? '' : 's'} creada${creadas === 1 ? '' : 's'}`;
    if (existentes > 0) msg += `, ${existentes} ya existían`;
    msg += '.';
    if ((resultado?.advertencias.length ?? 0) > 0) {
      msg += ` Advertencias: ${resultado!.advertencias.join(' | ')}`;
    }
    onSuccess(msg);
  }
</script>

{#if isOpen && curso}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div class="sync-backdrop" onclick={onClose} role="presentation"></div>

  <div class="sync-dialog" role="dialog" aria-modal="true" aria-label="Sincronizar con Intranet">
    <div class="sync-header">
      <h2 class="sync-title">Sincronizar con Intranet</h2>
      <p class="sync-subtitle">Curso #{curso.cod_curso}</p>
    </div>

    <div class="sync-body">
      {#if paso === 'aviso'}
        <div class="sync-warning">
          <p>
            <strong>Antes de continuar:</strong> si usted ya presionó este botón antes para este curso,
            corre el riesgo de duplicar información. Revise los últimos cursos y componentes para
            asegurar que el registro no exista ya.
          </p>
          <p class="sync-warning-note">
            Este paso sólo consulta la Intranet — no se guarda nada todavía. Podrá revisar el
            resultado antes de confirmar.
          </p>
        </div>
      {:else if paso === 'cargando'}
        <div class="sync-loading">
          <div class="inline-spinner"></div>
          <span>Consultando Intranet…</span>
        </div>
      {:else if paso === 'revisar'}
        <p class="sync-origen">
          {#if origen === 'INTRANET'}
            🟢 Detectado en Intranet
          {:else if origen === 'PLAN'}
            🔵 Derivado del Plan de Estudios (Intranet no tiene oferta para este periodo)
          {/if}
        </p>

        {#if componentes.length === 0}
          <p class="sync-empty">No se detectó ninguna componente nueva para sincronizar.</p>
        {:else}
          <div class="sync-comp-list">
            {#each componentes as c (c.id_tipo_componente)}
              <label class="sync-comp-row">
                <input
                  type="checkbox"
                  checked={aceptados.has(c.id_tipo_componente)}
                  onchange={() => toggleAceptado(c.id_tipo_componente)}
                />
                <span class="sync-comp-tipo">{c.tipo}</span>
                <span class="sync-comp-origen">{c.origen === 'INTRANET' ? '🟢 Intranet' : '🔵 Plan'}</span>
              </label>
            {/each}
          </div>

          <label class="sync-inscribir-row">
            <input type="checkbox" bind:checked={inscribirAlumnos} />
            <span>Inscribir automáticamente a los alumnos desde la Intranet</span>
          </label>
        {/if}

        {#if advertencias.length > 0}
          <div class="sync-advertencias">
            <p class="sync-advertencias-title">⚠️ Advertencias</p>
            <ul>
              {#each advertencias as a}
                <li>{a}</li>
              {/each}
            </ul>
          </div>
        {/if}
      {:else if paso === 'ejecutando'}
        <div class="sync-loading">
          <div class="inline-spinner"></div>
          <span>Sincronizando…</span>
        </div>
      {:else if paso === 'resultado' && resultado}
        <div class="sync-resultado">
          <p>✅ {resultado.componentes_creadas.length} componente(s) creada(s).</p>
          {#if resultado.componentes_existentes.length > 0}
            <p>ℹ️ {resultado.componentes_existentes.length} ya existían (no se duplicaron).</p>
          {/if}
        </div>
        {#if resultado.advertencias.length > 0}
          <div class="sync-advertencias">
            <p class="sync-advertencias-title">⚠️ Advertencias</p>
            <ul>
              {#each resultado.advertencias as a}
                <li>{a}</li>
              {/each}
            </ul>
          </div>
        {/if}
      {:else if paso === 'error'}
        <p class="sync-error">{errorMsg}</p>
      {/if}
    </div>

    <div class="sync-footer">
      {#if paso === 'aviso'}
        <button type="button" class="btn-cancel" onclick={onClose}>Cancelar</button>
        <button type="button" class="btn-submit" onclick={cargarPreview}>Continuar</button>
      {:else if paso === 'revisar'}
        <button type="button" class="btn-cancel" onclick={onClose}>Cancelar</button>
        <button
          type="button"
          class="btn-submit"
          disabled={aceptados.size === 0}
          onclick={confirmar}
        >
          Aceptar y sincronizar ({aceptados.size})
        </button>
      {:else if paso === 'resultado'}
        <button
          type="button"
          class="btn-submit"
          onclick={() => {
            cerrarConExito();
            onClose();
          }}
        >
          Cerrar
        </button>
      {:else if paso === 'error'}
        <button type="button" class="btn-cancel" onclick={onClose}>Cerrar</button>
        <button type="button" class="btn-submit" onclick={cargarPreview}>Reintentar</button>
      {/if}
    </div>
  </div>
{/if}

<style>
  .sync-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    z-index: 60;
  }

  .sync-dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 61;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
    width: min(480px, calc(100vw - 2rem));
    max-height: 85dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .sync-header {
    padding: 1.25rem 1.5rem 1rem;
    border-bottom: 1px solid #f1f5f9;
  }
  .sync-title {
    font-size: 1.0625rem;
    font-weight: 700;
    color: #111827;
    margin: 0;
  }
  .sync-subtitle {
    font-size: 0.8125rem;
    color: #6b7280;
    margin: 0.2rem 0 0;
  }

  .sync-body {
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
    padding: 1.25rem 1.5rem;
  }

  .sync-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 0.875rem 1rem;
    font-size: 0.8125rem;
    color: #92400e;
    line-height: 1.5;
  }
  .sync-warning-note {
    margin: 0.5rem 0 0;
    color: #a16207;
  }

  .sync-loading {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    color: #6b7280;
    font-size: 0.875rem;
    padding: 1rem 0;
  }

  .sync-origen {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #374151;
    margin: 0 0 0.75rem;
  }

  .sync-empty {
    color: #9ca3af;
    font-size: 0.875rem;
  }

  .sync-comp-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .sync-comp-row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.75rem;
    border: 1.5px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
  }
  .sync-comp-tipo {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
    flex: 1;
  }
  .sync-comp-origen {
    font-size: 0.75rem;
    color: #6b7280;
  }

  .sync-inscribir-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.875rem;
    font-size: 0.8125rem;
    color: #374151;
    cursor: pointer;
  }

  .sync-advertencias {
    margin-top: 0.875rem;
    padding: 0.625rem 0.75rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
  }
  .sync-advertencias-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: #92400e;
    margin: 0 0 0.25rem;
  }
  .sync-advertencias ul {
    margin: 0;
    padding-left: 1.1rem;
  }
  .sync-advertencias li {
    font-size: 0.75rem;
    color: #92400e;
    line-height: 1.4;
  }

  .sync-resultado p {
    font-size: 0.875rem;
    color: #374151;
    margin: 0 0 0.375rem;
  }

  .sync-error {
    color: #b91c1c;
    font-size: 0.875rem;
  }

  .sync-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.625rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #f1f5f9;
    flex-shrink: 0;
  }

  .btn-cancel,
  .btn-submit {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
  }
  .btn-cancel {
    background: #fff;
    color: #374151;
    border-color: #d1d5db;
  }
  .btn-cancel:hover {
    background: #f3f4f6;
  }
  .btn-submit {
    background: #3b82f6;
    color: #fff;
  }
  .btn-submit:hover {
    background: #2563eb;
  }
  .btn-submit:disabled {
    background: #93c5fd;
    cursor: not-allowed;
  }

  .inline-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
  }
  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
