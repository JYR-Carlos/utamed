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
  import { RefreshCw, AlertTriangle, CheckCircle2, XCircle, Layers, UserPlus } from 'lucide-svelte';

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
    errorMsg = '';
    try {
      const res = await fetch(`/admin/cursos/${curso.id_curso}/sincronizar-intranet/preview`, {
        headers: { Accept: 'application/json' },
      });
      const json = await res.json().catch(() => null);
      if (!res.ok) {
        throw new Error(json?.error ?? json?.message ?? `Error al consultar la Intranet (código ${res.status}).`);
      }
      origen = json.origen;
      componentes = json.componentes ?? [];
      advertencias = json.advertencias ?? [];
      aceptados = new Set(componentes.map((c) => c.id_tipo_componente));
      // Si el origen proviene de PLAN (fallback), deshabilitar inscripción automática
      inscribirAlumnos = origen === 'INTRANET';
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
    errorMsg = '';
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
          inscribir_automaticamente: origen === 'INTRANET' ? inscribirAlumnos : false,
        }),
      });
      const json = await res.json().catch(() => null);
      if (!res.ok) {
        throw new Error(json?.error ?? json?.message ?? `Error al sincronizar (código ${res.status}).`);
      }
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
    <!-- ── Header ── -->
    <div class="sync-header">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
          <RefreshCw size={20} class="text-blue-600" />
        </div>
        <div>
          <h2 class="sync-title">Sincronizar con Intranet</h2>
          <p class="sync-subtitle">
            Curso #{curso.cod_curso} {curso.nombre ? `· ${curso.nombre}` : ''}
          </p>
        </div>
      </div>
    </div>

    <!-- ── Body ── -->
    <div class="sync-body">
      {#if paso === 'aviso'}
        <div class="sync-warning">
          <div class="flex items-start gap-3">
            <AlertTriangle size={22} class="text-amber-600 shrink-0 mt-0.5" />
            <div class="space-y-2">
              <p class="font-semibold text-amber-900 text-sm">
                Antes de continuar:
              </p>
              <p class="text-amber-800 text-sm leading-relaxed">
                Si ya ejecutó la sincronización previamente para este curso, revise que las componentes y estudiantes no existan ya para evitar duplicidades.
              </p>
              <div class="mt-3 p-3 bg-amber-100/70 rounded-lg text-xs text-amber-900 leading-relaxed">
                <strong>Paso de previsualización seguro:</strong> En este paso sólo se consulta la información disponible (Intranet y Plan de Estudios); <em>no se escribirá nada en la base de datos hasta que confirme</em>.
              </div>
            </div>
          </div>
        </div>
      {:else if paso === 'cargando'}
        <div class="sync-loading">
          <div class="spinner-large"></div>
          <p class="font-medium text-gray-700 mt-3 text-base">Consultando Intranet y Plan de Estudios…</p>
          <p class="text-xs text-gray-400 mt-1">Obteniendo oferta académica y componentes sugeridas</p>
        </div>
      {:else if paso === 'revisar'}
        <!-- Banner de Origen -->
        <div class="sync-origen-banner" class:origen-intranet={origen === 'INTRANET'} class:origen-plan={origen === 'PLAN'}>
          <div class="flex items-center gap-2">
            {#if origen === 'INTRANET'}
              <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
              <span class="font-semibold text-emerald-800 text-sm">Detectado directamente en la Intranet</span>
            {:else if origen === 'PLAN'}
              <div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>
              <span class="font-semibold text-blue-900 text-sm">Derivado del Plan de Estudios</span>
            {/if}
          </div>
          <p class="text-xs mt-1 text-gray-600 leading-relaxed">
            {#if origen === 'INTRANET'}
              Se encontraron las asignaturas y actas registradas en Oracle para este período académico.
            {:else if origen === 'PLAN'}
              La Intranet no tiene oferta o no se encuentra accesible; las componentes se calcularon a partir de las horas del Plan de Estudios.
            {/if}
          </p>
        </div>

        {#if componentes.length === 0}
          <div class="sync-empty-box">
            <Layers size={32} class="text-gray-300 mb-2" />
            <p class="text-sm font-semibold text-gray-600">No se detectaron componentes nuevas</p>
            <p class="text-xs text-gray-400 mt-1">El curso ya cuenta con todas las secciones o no hay información para sincronizar.</p>
          </div>
        {:else}
          <div class="space-y-2.5">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 px-1">
              Componentes para crear / verificar ({aceptados.size} de {componentes.length} seleccionadas)
            </p>
            <div class="sync-comp-list">
              {#each componentes as c (c.id_tipo_componente)}
                {@const isChecked = aceptados.has(c.id_tipo_componente)}
                <label class="sync-comp-row" class:row-selected={isChecked}>
                  <input
                    type="checkbox"
                    checked={isChecked}
                    onchange={() => toggleAceptado(c.id_tipo_componente)}
                    class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                  />
                  <div class="flex-1 min-w-0">
                    <span class="sync-comp-tipo">{c.tipo}</span>
                    {#if c.cur_codigo}
                      <span class="text-xs text-gray-400 block mt-0.5">Acta Intranet: #{c.cur_codigo}</span>
                    {/if}
                  </div>
                  <span class="sync-comp-origen" class:badge-intranet={c.origen === 'INTRANET'} class:badge-plan={c.origen === 'PLAN'}>
                    {c.origen === 'INTRANET' ? 'Intranet' : 'Plan de Estudios'}
                  </span>
                </label>
              {/each}
            </div>
          </div>

          <!-- Opción de inscripción automática -->
          <label class="sync-inscribir-card" class:opacity-60={origen === 'PLAN'}>
            <div class="flex items-start gap-3">
              <input
                type="checkbox"
                bind:checked={inscribirAlumnos}
                disabled={origen === 'PLAN'}
                class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500 mt-0.5 shrink-0"
              />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <UserPlus size={15} class="text-blue-600 shrink-0" />
                  <span class="text-sm font-semibold text-gray-900">
                    Inscribir automáticamente a los estudiantes desde la Intranet
                  </span>
                </div>
                {#if origen === 'PLAN'}
                  <p class="text-xs text-amber-700 mt-1 leading-relaxed">
                    ℹ️ No disponible: las componentes se derivaron del Plan de Estudios o la conexión con la Intranet no se encuentra activa para consultar listas de alumnos.
                  </p>
                {:else}
                  <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    Consultará las actas de Oracle e inscribirá en UTAMED a los estudiantes registrados en el curso y sus componentes.
                  </p>
                {/if}
              </div>
            </div>
          </label>
        {/if}

        {#if advertencias.length > 0}
          <div class="sync-advertencias">
            <div class="flex items-center gap-1.5 mb-2">
              <AlertTriangle size={15} class="text-amber-700 shrink-0" />
              <p class="sync-advertencias-title">Advertencias a tener en cuenta</p>
            </div>
            <ul class="space-y-1.5">
              {#each advertencias as a}
                <li>{a}</li>
              {/each}
            </ul>
          </div>
        {/if}
      {:else if paso === 'ejecutando'}
        <div class="sync-loading">
          <div class="spinner-large"></div>
          <p class="font-medium text-gray-700 mt-3 text-base">Sincronizando componentes con la base de datos…</p>
          <p class="text-xs text-gray-400 mt-1">Creando contextos y configurando parámetros de aprobación</p>
        </div>
      {:else if paso === 'resultado' && resultado}
        <div class="sync-resultado-box">
          <div class="flex items-start gap-3">
            <CheckCircle2 size={24} class="text-emerald-600 shrink-0 mt-0.5" />
            <div class="space-y-2">
              <h3 class="text-base font-bold text-emerald-950">Sincronización finalizada con éxito</h3>
              <p class="text-sm text-emerald-900">
                Se crearon <strong>{resultado.componentes_creadas.length}</strong> componente(s) en UTAMED.
              </p>
              {#if resultado.componentes_existentes.length > 0}
                <p class="text-xs text-emerald-800">
                  ℹ️ {resultado.componentes_existentes.length} componente(s) ya existían previamente y se conservaron intactas.
                </p>
              {/if}
            </div>
          </div>
        </div>

        {#if resultado.advertencias.length > 0}
          <div class="sync-advertencias">
            <div class="flex items-center gap-1.5 mb-2">
              <AlertTriangle size={15} class="text-amber-700 shrink-0" />
              <p class="sync-advertencias-title">Detalles y advertencias del proceso</p>
            </div>
            <ul class="space-y-1.5">
              {#each resultado.advertencias as a}
                <li>{a}</li>
              {/each}
            </ul>
          </div>
        {/if}
      {:else if paso === 'error'}
        <div class="sync-error-box">
          <div class="flex items-start gap-3">
            <XCircle size={24} class="text-red-600 shrink-0 mt-0.5" />
            <div class="space-y-1 flex-1">
              <h3 class="text-base font-bold text-red-950">No fue posible completar la operación</h3>
              <p class="text-sm text-red-800 leading-relaxed">{errorMsg}</p>
            </div>
          </div>
        </div>
      {/if}
    </div>

    <!-- ── Footer ── -->
    <div class="sync-footer">
      {#if paso === 'aviso'}
        <button type="button" class="btn-cancel" onclick={onClose}>Cancelar</button>
        <button type="button" class="btn-submit" onclick={cargarPreview}>
          Continuar a Previsualización
        </button>
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
          Entendido, cerrar
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
    background: rgba(15, 23, 42, 0.4);
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
    border-radius: 20px;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.22);
    width: min(620px, calc(100vw - 2rem));
    min-height: min(480px, 72dvh);
    max-height: 88dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .sync-header {
    padding: 1.5rem 1.75rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
    flex-shrink: 0;
  }

  .sync-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1.3;
  }

  .sync-subtitle {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0.25rem 0 0;
  }

  .sync-body {
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
    padding: 1.5rem 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
  }

  .sync-warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
  }

  .sync-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1rem;
    text-align: center;
    margin: auto 0;
  }

  .spinner-large {
    width: 38px;
    height: 38px;
    border: 3px solid #e2e8f0;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }

  .sync-origen-banner {
    padding: 0.875rem 1.25rem;
    border-radius: 12px;
    border: 1px solid transparent;
  }

  .origen-intranet {
    background: #ecfdf5;
    border-color: #a7f3d0;
  }

  .origen-plan {
    background: #eff6ff;
    border-color: #bfdbfe;
  }

  .sync-empty-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1.5rem;
    text-align: center;
    background: #f8fafc;
    border: 1.5px dashed #cbd5e1;
    border-radius: 12px;
  }

  .sync-comp-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
  }

  .sync-comp-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1.15rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .sync-comp-row:hover {
    border-color: #93c5fd;
    background: #f8fafc;
  }

  .row-selected {
    border-color: #3b82f6;
    background: #f0f7ff;
  }

  .sync-comp-tipo {
    font-size: 0.925rem;
    font-weight: 600;
    color: #0f172a;
  }

  .sync-comp-origen {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
  }

  .badge-intranet {
    background: #dcfce7;
    color: #166534;
  }

  .badge-plan {
    background: #dbeafe;
    color: #1e40af;
  }

  .sync-inscribir-card {
    padding: 1rem 1.25rem;
    border-radius: 12px;
    background: #f8fafc;
    border: 1.5px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.15s ease;
  }

  .sync-inscribir-card:hover:not(.opacity-60) {
    border-color: #cbd5e1;
    background: #f1f5f9;
  }

  .sync-advertencias {
    padding: 1rem 1.25rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 12px;
  }

  .sync-advertencias-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #92400e;
    margin: 0;
  }

  .sync-advertencias ul {
    margin: 0;
    padding-left: 1.25rem;
  }

  .sync-advertencias li {
    font-size: 0.775rem;
    color: #92400e;
    line-height: 1.5;
  }

  .sync-resultado-box {
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
  }

  .sync-error-box {
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
  }

  .sync-footer {
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

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }
</style>
