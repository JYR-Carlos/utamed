<script lang="ts">
  /**
   * Sincronización masiva de componentes con Intranet, para los cursos que
   * todavía no tienen ninguna. Mismo problema que sincronizar un curso a la
   * vez (guardar directo y avisar después), misma solución: mirar antes de
   * tocar, revisar, y sólo entonces confirmar.
   */
  import type { ResultadoPreviewComponentes } from '@/types/admin.types';

  interface CursoPreview {
    id_curso: number;
    cod_curso: number;
    asignatura: string | null;
    preview: ResultadoPreviewComponentes;
  }

  interface Props {
    isOpen: boolean;
    onClose: () => void;
    onSuccess: (mensaje: string) => void;
  }

  let { isOpen, onClose, onSuccess }: Props = $props();

  type Paso = 'aviso' | 'cargando' | 'revisar' | 'ejecutando' | 'resultado' | 'error';
  let paso = $state<Paso>('aviso');
  let cursos = $state<CursoPreview[]>([]);
  let aceptados = $state<Set<number>>(new Set());
  let errorMsg = $state('');
  let resumen = $state('');

  $effect(() => {
    if (isOpen) {
      paso = 'aviso';
      cursos = [];
      aceptados = new Set();
      errorMsg = '';
      resumen = '';
    }
  });

  async function cargarPreview() {
    paso = 'cargando';
    errorMsg = '';
    try {
      const res = await fetch('/admin/cursos/sincronizar-intranet-masivo/preview', {
        headers: { Accept: 'application/json' },
      });
      const json = await res.json().catch(() => null);
      if (!res.ok) {
        throw new Error(json?.error ?? json?.message ?? `Error al consultar la Intranet (código ${res.status}).`);
      }
      cursos = json.cursos ?? [];
      // Sólo se pre-marcan los que no tienen advertencias: lo demás requiere
      // mirada humana antes de aceptarse (no se "adivina" por la persona).
      aceptados = new Set(
        cursos.filter((c) => c.preview.advertencias.length === 0 && c.preview.componentes.length > 0)
          .map((c) => c.id_curso),
      );
      paso = 'revisar';
    } catch (e: any) {
      errorMsg = e?.message ?? 'Error al consultar la Intranet.';
      paso = 'error';
    }
  }

  function toggle(id: number) {
    const next = new Set(aceptados);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    aceptados = next;
  }

  async function confirmar() {
    if (aceptados.size === 0) return;
    paso = 'ejecutando';
    errorMsg = '';
    try {
      const res = await fetch('/admin/cursos/sincronizar-intranet-masivo', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN':
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ ids_curso: [...aceptados] }),
      });
      const json = await res.json().catch(() => null);
      if (!res.ok) {
        throw new Error(json?.error ?? json?.message ?? `Error al sincronizar (código ${res.status}).`);
      }

      const resultados = (json.resultados ?? []) as Array<{
        id_curso: number;
        resultado?: { componentes_creadas: string[] };
        error?: string;
      }>;
      const totalCreadas = resultados.reduce((n, r) => n + (r.resultado?.componentes_creadas.length ?? 0), 0);
      const conError = resultados.filter((r) => r.error).length;
      resumen = `Se sincronizaron ${resultados.length - conError} curso(s), ${totalCreadas} componente(s) creada(s) en total.`;
      if (conError > 0) resumen += ` ${conError} curso(s) con error — revisar manualmente.`;
      paso = 'resultado';
    } catch (e: any) {
      errorMsg = e?.message ?? 'Error al sincronizar con la Intranet.';
      paso = 'error';
    }
  }
</script>

{#if isOpen}
  <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
  <div class="masivo-backdrop" onclick={onClose} role="presentation"></div>

  <div class="masivo-dialog" role="dialog" aria-modal="true" aria-label="Sincronización masiva con Intranet">
    <div class="masivo-header">
      <h2 class="masivo-title">Sincronizar cursos sin componentes</h2>
      <p class="masivo-subtitle">Detecta y crea componentes desde Intranet (o el Plan de Estudios) para varios cursos a la vez.</p>
    </div>

    <div class="masivo-body">
      {#if paso === 'aviso'}
        <div class="masivo-warning">
          Este proceso revisa hasta 50 cursos sin ninguna componente todavía. No se guarda nada
          hasta que usted confirme cuáles aceptar.
        </div>
      {:else if paso === 'cargando'}
        <div class="masivo-loading"><div class="inline-spinner"></div><span>Consultando Intranet…</span></div>
      {:else if paso === 'revisar'}
        {#if cursos.length === 0}
          <p class="masivo-empty">No hay cursos sin componentes por sincronizar.</p>
        {:else}
          <div class="masivo-list">
            {#each cursos as c (c.id_curso)}
              <label class="masivo-row">
                <input
                  type="checkbox"
                  checked={aceptados.has(c.id_curso)}
                  onchange={() => toggle(c.id_curso)}
                />
                <div class="masivo-row-info">
                  <div class="masivo-row-title">
                    #{c.cod_curso} — {c.asignatura ?? 'Sin asignatura'}
                    <span class="masivo-origen">{c.preview.origen === 'INTRANET' ? '🟢' : '🔵'}</span>
                  </div>
                  <div class="masivo-row-tipos">
                    {c.preview.componentes.map((comp) => comp.tipo).join(', ') || 'Sin componentes detectadas'}
                  </div>
                  {#if c.preview.advertencias.length > 0}
                    <div class="masivo-row-advertencia">⚠️ {c.preview.advertencias.join(' | ')}</div>
                  {/if}
                </div>
              </label>
            {/each}
          </div>
        {/if}
      {:else if paso === 'ejecutando'}
        <div class="masivo-loading"><div class="inline-spinner"></div><span>Sincronizando…</span></div>
      {:else if paso === 'resultado'}
        <p class="masivo-resumen">{resumen}</p>
      {:else if paso === 'error'}
        <p class="masivo-error">{errorMsg}</p>
      {/if}
    </div>

    <div class="masivo-footer">
      {#if paso === 'aviso'}
        <button type="button" class="btn-cancel" onclick={onClose}>Cancelar</button>
        <button type="button" class="btn-submit" onclick={cargarPreview}>Continuar</button>
      {:else if paso === 'revisar'}
        <button type="button" class="btn-cancel" onclick={onClose}>Cancelar</button>
        <button type="button" class="btn-submit" disabled={aceptados.size === 0} onclick={confirmar}>
          Sincronizar seleccionados ({aceptados.size})
        </button>
      {:else if paso === 'resultado'}
        <button
          type="button"
          class="btn-submit"
          onclick={() => {
            onSuccess(resumen);
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
  .masivo-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    z-index: 60;
  }
  .masivo-dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 61;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.22);
    width: min(680px, calc(100vw - 2rem));
    min-height: min(500px, 75dvh);
    max-height: 88dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .masivo-header {
    padding: 1.5rem 1.75rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    flex-shrink: 0;
  }
  .masivo-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1.3;
  }
  .masivo-subtitle {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0.25rem 0 0;
  }
  .masivo-body {
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
    padding: 1.5rem 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }
  .masivo-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    font-size: 0.875rem;
    color: #92400e;
    line-height: 1.6;
  }
  .masivo-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    color: #64748b;
    font-size: 0.925rem;
    padding: 4rem 1rem;
    text-align: center;
    margin: auto 0;
  }
  .masivo-empty {
    color: #64748b;
    font-size: 0.925rem;
    padding: 2rem;
    text-align: center;
  }
  .masivo-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
  }
  .masivo-row {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.85rem 1.15rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .masivo-row:hover {
    border-color: #93c5fd;
    background: #f8fafc;
  }
  .masivo-row input {
    margin-top: 0.25rem;
  }
  .masivo-row-info {
    flex: 1;
    min-width: 0;
  }
  .masivo-row-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #0f172a;
  }
  .masivo-origen {
    margin-left: 0.35rem;
  }
  .masivo-row-tipos {
    font-size: 0.775rem;
    color: #64748b;
    margin-top: 0.2rem;
  }
  .masivo-row-advertencia {
    font-size: 0.75rem;
    color: #92400e;
    margin-top: 0.35rem;
    background: #fef3c7;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    display: inline-block;
  }
  .masivo-resumen {
    font-size: 0.925rem;
    color: #0f172a;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
  }
  .masivo-error {
    color: #991b1b;
    font-size: 0.925rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
  }
  .masivo-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 1.75rem;
    border-top: 1px solid #f1f5f9;
    background: #ffffff;
    flex-shrink: 0;
  }
  .btn-cancel,
  .btn-submit {
    padding: 0.65rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s ease;
  }
  .btn-cancel {
    background: #ffffff;
    color: #475569;
    border-color: #cbd5e1;
  }
  .btn-cancel:hover {
    background: #f8fafc;
    color: #1e293b;
    border-color: #94a3b8;
  }
  .btn-submit {
    background: #2563eb;
    color: #ffffff;
  }
  .btn-submit:hover:not(:disabled) {
    background: #1d4ed8;
  }
  .btn-submit:disabled {
    background: #93c5fd;
    cursor: not-allowed;
    opacity: 0.7;
  }
  .inline-spinner {
    width: 28px;
    height: 28px;
    border: 3px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }
  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
