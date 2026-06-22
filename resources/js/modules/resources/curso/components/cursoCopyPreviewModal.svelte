<script lang="ts">
  import { router } from '@inertiajs/svelte';
  import { Copy, X, AlertTriangle, BookOpen, Users, ClipboardList, Calendar } from 'lucide-svelte';
  import type { Curso } from '../types/curso.types';

  interface ActividadPreview {
    id_actividad: number;
    nombre: string;
    tipo_actividad: string | null;
    ponderacion: number | null;
    fecha_limite: string | null;
    es_grupal: boolean;
  }

  interface DocentePreview {
    nombre: string;
    cargo: string | null;
    es_titular: boolean;
  }

  interface ComponentePreview {
    id_componente: number;
    tipo: string;
    genera_acta: boolean;
    porcentaje_aprobacion: number | null;
    aprobacion_obligatoria: boolean;
    porcentaje_asistencia_obligatoria: number | null;
    docentes: DocentePreview[];
    actividades: ActividadPreview[];
  }

  interface ProgramaPreview {
    estado: string;
    tipo_syllabus: string | null;
    version: number;
  }

  interface PreviewData {
    curso: {
      id_curso: number;
      cod_curso: number;
      nombre: string | null;
      asignatura_nombre: string | null;
      cod_asignatura: string | null;
      carrera_nombre: string | null;
      periodo: string | null;
      fecha_inicio: string | null;
      es_colegiado: boolean;
    };
    programa: ProgramaPreview | null;
    componentes: ComponentePreview[];
  }

  interface Props {
    isOpen?: boolean;
    curso?: Curso | null;
    onClose?: () => void;
    onSuccess?: () => void;
  }

  let {
    isOpen = $bindable(false),
    curso = null,
    onClose = () => {},
    onSuccess = () => {},
  }: Props = $props();

  let preview = $state<PreviewData | null>(null);
  let loading = $state(false);
  let loadError = $state('');
  let copying = $state(false);
  let copyError = $state('');

  // Form fields for the new course
  let newCodCurso = $state('');
  let newFechaInicio = $state('');

  const totalActividades = $derived(
    preview?.componentes.reduce((sum, c) => sum + c.actividades.length, 0) ?? 0,
  );

  const actividadesConFecha = $derived(
    preview?.componentes.reduce(
      (sum, c) => sum + c.actividades.filter((a) => a.fecha_limite).length,
      0,
    ) ?? 0,
  );

  $effect(() => {
    if (isOpen && curso) {
      preview = null;
      loadError = '';
      copyError = '';
      newCodCurso = '';
      newFechaInicio = '';
      fetchPreview(curso.id_curso);
    }
  });

  async function fetchPreview(idCurso: number) {
    loading = true;
    try {
      const res = await fetch(`/admin/cursos/${idCurso}/preview-copia`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      preview = await res.json();
    } catch (e) {
      loadError = 'No se pudo cargar la previsualización del curso.';
    } finally {
      loading = false;
    }
  }

  function handleClose() {
    isOpen = false;
    onClose();
  }

  function handleBackdropKey(e: KeyboardEvent) {
    if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') handleClose();
  }

  function handleSubmit(e: SubmitEvent) {
    e.preventDefault();
    if (!curso || !newCodCurso || !newFechaInicio) return;
    copying = true;
    copyError = '';
    router.post(
      `/admin/cursos/${curso.id_curso}/copiar`,
      { cod_curso: parseInt(newCodCurso), fecha_inicio: newFechaInicio },
      {
        onSuccess: () => {
          copying = false;
          isOpen = false;
          onSuccess();
        },
        onError: (errors) => {
          copying = false;
          copyError = Object.values(errors).flat().join('. ');
        },
      },
    );
  }

  function estadoProgramaBadge(estado: string): { label: string; cls: string } {
    const map: Record<string, { label: string; cls: string }> = {
      BORRADOR: { label: 'Borrador', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
      BASICO_COMPLETO: { label: 'Básico', cls: 'bg-blue-50 text-blue-700 border-blue-200' },
      COMPLETO: { label: 'Completo', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
      APROBADO: { label: 'Aprobado', cls: 'bg-green-50 text-green-700 border-green-200' },
      ENVIADO: { label: 'Enviado', cls: 'bg-purple-50 text-purple-700 border-purple-200' },
    };
    return map[estado] ?? { label: estado, cls: 'bg-gray-100 text-gray-600 border-gray-200' };
  }
</script>

{#if isOpen && curso}
  <!-- Backdrop -->
  <!-- svelte-ignore a11y_interactive_supports_focus -->
  <div
    class="fixed inset-0 z-50 bg-gray-900/40 backdrop-blur-[1px]"
    role="button"
    aria-label="Cerrar"
    onclick={handleClose}
    onkeydown={handleBackdropKey}
  ></div>

  <!-- Modal -->
  <div
    class="fixed inset-0 z-[60] flex items-center justify-center p-4 pointer-events-none"
    aria-modal="true"
    role="dialog"
    aria-labelledby="copy-modal-title"
  >
    <div
      class="pointer-events-auto w-full max-w-2xl max-h-[88dvh] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden"
    >
      <!-- Header -->
      <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-gray-100 flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
            <Copy size={17} class="text-blue-600" />
          </div>
          <div>
            <p class="text-[11px] font-semibold text-blue-600 uppercase tracking-widest">
              Copiar Curso
            </p>
            <h2 id="copy-modal-title" class="text-base font-bold text-gray-900 leading-snug">
              {curso.asignatura_nombre ?? 'Curso'}
            </h2>
          </div>
        </div>
        <button
          onclick={handleClose}
          class="mt-0.5 p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition"
          aria-label="Cerrar"
        >
          <X size={17} />
        </button>
      </div>

      <!-- Scrollable body -->
      <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
        {#if loading}
          <div class="flex items-center justify-center py-16">
            <div class="w-7 h-7 border-2 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
            <span class="ml-3 text-sm text-gray-500">Cargando previsualización…</span>
          </div>
        {:else if loadError}
          <div class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            <AlertTriangle size={16} class="flex-shrink-0" />
            {loadError}
          </div>
        {:else if preview}
          <!-- Warning banner -->
          {#if actividadesConFecha > 0}
            <div
              class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl"
            >
              <AlertTriangle size={16} class="text-amber-600 flex-shrink-0 mt-0.5" />
              <div>
                <p class="text-sm font-semibold text-amber-800">Aviso: Fechas de actividades</p>
                <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">
                  {actividadesConFecha} actividad{actividadesConFecha > 1 ? 'es tienen' : ' tiene'} fecha
                  límite asignada. Al copiar el curso, <strong>todas las fechas quedarán
                  vacías</strong> y deberás actualizarlas manualmente en el nuevo curso.
                </p>
              </div>
            </div>
          {/if}

          <!-- Resumen del curso origen -->
          <div class="p-4 rounded-xl bg-gray-50 border border-gray-200">
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">
              Curso origen
            </p>
            <p class="text-sm font-bold text-gray-900">
              {preview.curso.asignatura_nombre ?? '—'}
            </p>
            <div class="flex flex-wrap gap-x-3 gap-y-1 mt-1.5 text-xs text-gray-500">
              {#if preview.curso.cod_asignatura}
                <span class="font-mono bg-white border border-gray-200 px-1.5 py-0.5 rounded">
                  {preview.curso.cod_asignatura}
                </span>
              {/if}
              {#if preview.curso.carrera_nombre}
                <span>{preview.curso.carrera_nombre}</span>
              {/if}
              {#if preview.curso.periodo}
                <span>{preview.curso.periodo}</span>
              {/if}
              {#if preview.curso.es_colegiado}
                <span class="bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded border border-indigo-200 font-medium">
                  Colegiado
                </span>
              {/if}
            </div>
          </div>

          <!-- Programa -->
          <div>
            <p
              class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-1.5 mb-2"
            >
              <BookOpen size={11} />
              Programa Académico
            </p>
            {#if preview.programa}
              {@const badge = estadoProgramaBadge(preview.programa.estado)}
              <div
                class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-white"
              >
                <div>
                  <p class="text-sm font-medium text-gray-800">
                    Tipo: {preview.programa.tipo_syllabus ?? 'No especificado'} — v{preview.programa.version}
                  </p>
                </div>
                <span
                  class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {badge.cls}"
                >
                  {badge.label}
                </span>
              </div>
              <p class="text-xs text-gray-400 mt-1.5">
                El programa <strong>no se copia</strong>. El nuevo curso iniciará sin programa y deberás generarlo nuevamente.
              </p>
            {:else}
              <p class="text-xs text-gray-400 italic p-3 border border-gray-200 rounded-xl bg-gray-50">
                Este curso no tiene programa académico.
              </p>
            {/if}
          </div>

          <!-- Componentes y actividades -->
          <div>
            <p
              class="text-xs font-semibold text-gray-500 uppercase tracking-wider flex items-center gap-1.5 mb-3"
            >
              <ClipboardList size={11} />
              Secciones y Actividades ({preview.componentes.length} sección{preview.componentes.length !== 1 ? 'es' : ''} · {totalActividades} actividad{totalActividades !== 1 ? 'es' : ''})
            </p>

            {#if preview.componentes.length === 0}
              <p class="text-xs text-gray-400 italic">Este curso no tiene secciones configuradas.</p>
            {:else}
              <div class="space-y-3">
                {#each preview.componentes as comp (comp.id_componente)}
                  <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <!-- Componente header -->
                    <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-200">
                      <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-800">{comp.tipo}</span>
                        {#if comp.genera_acta}
                          <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-green-50 text-green-700 border border-green-200">
                            Acta
                          </span>
                        {/if}
                      </div>
                      <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span>Aprobación: {comp.porcentaje_aprobacion ?? '—'}%</span>
                        <span>Asistencia: {comp.porcentaje_asistencia_obligatoria ?? '—'}%</span>
                      </div>
                    </div>

                    <!-- Docentes -->
                    {#if comp.docentes.length > 0}
                      <div class="px-4 py-2 border-b border-gray-100 flex flex-wrap gap-2">
                        <Users size={12} class="text-gray-400 mt-0.5 flex-shrink-0" />
                        {#each comp.docentes as d}
                          <span class="text-xs text-gray-600">
                            {d.nombre}
                            {#if d.es_titular}
                              <span class="text-[10px] text-blue-600 font-semibold">(Titular)</span>
                            {/if}
                          </span>
                        {/each}
                      </div>
                    {/if}

                    <!-- Actividades -->
                    {#if comp.actividades.length === 0}
                      <div class="px-4 py-3 text-xs text-gray-400 italic">Sin actividades</div>
                    {:else}
                      <table class="w-full text-xs">
                        <thead class="bg-gray-50/60">
                          <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-500 uppercase tracking-wider">Actividad</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-500 uppercase tracking-wider">Pond.</th>
                            <th class="px-4 py-2 text-right font-semibold text-gray-500 uppercase tracking-wider flex items-center justify-end gap-1">
                              <Calendar size={10} />
                              Fecha límite
                            </th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                          {#each comp.actividades as act (act.id_actividad)}
                            <tr class="hover:bg-gray-50/60">
                              <td class="px-4 py-2 font-medium text-gray-800">
                                {act.nombre}
                                {#if act.es_grupal}
                                  <span class="ml-1 text-[10px] text-indigo-600 font-semibold">Grupal</span>
                                {/if}
                              </td>
                              <td class="px-4 py-2 text-gray-500">
                                {act.tipo_actividad ?? '—'}
                              </td>
                              <td class="px-4 py-2 text-right text-gray-600">
                                {act.ponderacion != null ? `${act.ponderacion}%` : '—'}
                              </td>
                              <td class="px-4 py-2 text-right">
                                {#if act.fecha_limite}
                                  <span class="text-amber-600 line-through">{act.fecha_limite}</span>
                                  <span class="text-gray-400 ml-1 no-underline">→ vacía</span>
                                {:else}
                                  <span class="text-gray-400">—</span>
                                {/if}
                              </td>
                            </tr>
                          {/each}
                        </tbody>
                      </table>
                    {/if}
                  </div>
                {/each}
              </div>
            {/if}
          </div>

          <!-- Form: new course data -->
          <form id="copy-form" onsubmit={handleSubmit}>
            <div class="p-4 rounded-xl border border-blue-200 bg-blue-50/40 space-y-4">
              <p class="text-sm font-semibold text-gray-800">Datos del nuevo curso</p>

              <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-gray-600" for="new-cod-curso">
                    Código del Curso *
                  </label>
                  <input
                    id="new-cod-curso"
                    type="number"
                    bind:value={newCodCurso}
                    placeholder="Ej: 12346"
                    min="1"
                    required
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>

                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-semibold text-gray-600" for="new-fecha-inicio">
                    Fecha de inicio *
                  </label>
                  <input
                    id="new-fecha-inicio"
                    type="date"
                    bind:value={newFechaInicio}
                    required
                    class="px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                </div>
              </div>

              {#if copyError}
                <p class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                  {copyError}
                </p>
              {/if}
            </div>
          </form>
        {/if}
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex-shrink-0">
        <button
          onclick={handleClose}
          class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
          disabled={copying}
        >
          Cancelar
        </button>

        {#if preview && !loading}
          <button
            type="submit"
            form="copy-form"
            disabled={copying || !newCodCurso || !newFechaInicio}
            class="flex items-center gap-2 px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98] transition shadow-sm"
          >
            {#if copying}
              <div class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></div>
              Copiando…
            {:else}
              <Copy size={14} />
              Confirmar Copia
            {/if}
          </button>
        {/if}
      </div>
    </div>
  </div>
{/if}
