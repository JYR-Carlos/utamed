<script lang="ts">
  import { Button } from '@/components/ui/button';
  import { ArrowLeft, Edit2, Save, X } from 'lucide-svelte';
  import { router } from '@inertiajs/svelte';
  import { store as storePrograma } from '@/actions/App/Http/Controllers/Admin/ProgramaController';
  import ProgramaStateBadges from './ProgramaStateBadges.svelte';
  import CompletenessProgressBar from './CompletenessProgressBar.svelte';
  // import SyllabusEditor from '../SyllabusEditor.svelte';

  interface Programa {
    id_programa: number;
    version_programa: number;
    estado: string;
    creado_por: number;
    revisado_por?: number;
    fecha_creacion: string;
    data_syllabus: {
      metadata?: {
        tipo_syllabus: string;
        curso?: string;
        asignatura?: string;
        creditos?: number;
      };
      secciones?: Record<string, any>;
    };
    completenessPercentage?: number;
    creator?: { id_usuario: number; nombre_completo: string };
    reviewer?: { id_usuario: number; nombre_completo: string };
  }

  interface Props {
    programa: Programa;
    /** Las rutas de escritura del programa cuelgan del curso, no del id_programa. */
    cursoId: number;
    userRole: string;
    userId: number;
  }

  let { programa, cursoId, userRole, userId }: Props = $props();

  let isEditing = $state(false);
  let editedSyllabus = $state<typeof programa.data_syllabus | null>(null);
  let isSaving = $state(false);

  const isAdmin = $derived(userRole === 'admin' || userRole === 'administrator' || userRole === 'Admin');

  const canEdit = $derived(
    (userId === programa.creado_por || isAdmin) &&
      (programa.estado === 'BORRADOR' || programa.estado === 'BASICO_COMPLETO' || programa.estado === 'COMPLETO'),
  );

  const requiredSecciones = $derived(
    programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO'
      ? ['I', 'II', 'VI', 'VII', 'VIII']
      : ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX'],
  );

  // ── Section metadata ────────────────────────────────────────────────
  const SECTION_TITLES: Record<string, string> = {
    I: 'Identificación',
    II: 'Presentación',
    III: 'Estándares',
    IV: 'Competencias',
    V: 'Evaluación Diagnóstica',
    VI: 'Unidades',
    VII: 'Planificación',
    VIII: 'Recursos',
    IX: 'Aspectos Administrativos',
  };

  /**
   * Returns true if `value` contains at least one non-empty leaf.
   * Treats empty strings, empty arrays and empty objects as empty.
   */
  function hasContent(value: unknown): boolean {
    if (value == null) return false;
    if (typeof value === 'string') return value.trim().length > 0;
    if (typeof value === 'number' || typeof value === 'boolean') return true;
    if (Array.isArray(value)) return value.length > 0 && value.some(hasContent);
    if (typeof value === 'object') return Object.values(value as object).some(hasContent);
    return false;
  }

  function startEditing() {
    editedSyllabus = JSON.parse(JSON.stringify(programa.data_syllabus));
    isEditing = true;
  }

  function cancelEditing() {
    isEditing = false;
    editedSyllabus = null;
  }

  async function handleSave() {
    if (!editedSyllabus) return;

    isSaving = true;
    try {
      // No existe una ruta de escritura por id_programa: guardar es un POST a
      // admin.cursos.programa.store, que crea una versión nueva del programa del
      // curso a partir de las secciones enviadas (mismo endpoint que SyllabusModal).
      const action = storePrograma['/admin/cursos/{curso}/programa'](cursoId);

      const response = await fetch(action.url, {
        method: action.method.toUpperCase(),
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          syllabus_type: editedSyllabus.metadata?.tipo_syllabus === 'BASICO' ? 'simplified' : 'complete',
          secciones: editedSyllabus.secciones ?? {},
        }),
      });

      if (response.ok) {
        isEditing = false;
        editedSyllabus = null;
        router.reload();
      } else {
        alert('Error al guardar los cambios');
      }
    } catch (error) {
      console.error('Error saving:', error);
      alert('Error al guardar los cambios');
    } finally {
      isSaving = false;
    }
  }
</script>

<div class="space-y-6">
  <!-- Header with Back Button -->
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-4">
      <button onclick={() => history.back()} class="p-2 hover:bg-slate-100 rounded-lg transition" title="Volver">
        <ArrowLeft size={24} class="text-slate-600" />
      </button>
      <div>
        <h1 class="text-3xl font-bold text-slate-900">
          {programa.data_syllabus?.metadata?.asignatura || 'Programa'}
        </h1>
        <p class="text-slate-600">
          v{programa.version_programa} • Código: {programa.data_syllabus?.metadata?.curso || 'N/A'}
        </p>
      </div>
    </div>

    {#if canEdit && !isEditing}
      <Button variant="default" onclick={startEditing}>
        <Edit2 size={18} class="mr-2" />
        Editar
      </Button>
    {/if}
  </div>

  <!-- Status Section -->
  <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div>
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Estado Actual</h2>
        <ProgramaStateBadges
          estado={programa.estado}
          tipoSyllabus={programa.data_syllabus?.metadata?.tipo_syllabus}
          completenessPercentage={programa.completenessPercentage}
        />
      </div>

      <div>
        <h2 class="text-sm font-semibold text-slate-700 mb-2">Información</h2>
        <div class="space-y-1 text-sm">
          <p>
            <strong>Tipo:</strong>
            {programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO' ? 'Básico (5 secciones)' : 'Completo (9 secciones)'}
          </p>
          <p><strong>Créditos:</strong> {programa.data_syllabus?.metadata?.creditos || 'No especificado'}</p>
          <p><strong>Versión:</strong> {programa.version_programa}</p>
        </div>
      </div>
    </div>

    <!-- Completeness Bar -->
    {#if programa.data_syllabus?.metadata?.tipo_syllabus}
      <div class="mt-4 pt-4 border-t border-blue-200">
        <CompletenessProgressBar percentage={programa.completenessPercentage || 0} tipo={programa.data_syllabus.metadata.tipo_syllabus} />
      </div>
    {/if}
  </div>

  <!-- Programa Metadata -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded-lg border border-gray-200">
      <h3 class="text-sm font-semibold text-slate-700 mb-2">Creado por</h3>
      <p class="text-slate-900">{programa.creator?.nombre_completo || 'Sistema'}</p>
      <p class="text-xs text-slate-500 mt-1">
        {new Date(programa.fecha_creacion).toLocaleDateString('es-ES')}
      </p>
    </div>

    <div class="bg-white p-4 rounded-lg border border-gray-200">
      <h3 class="text-sm font-semibold text-slate-700 mb-2">Revisado por</h3>
      <p class="text-slate-900">
        {programa.revisado_por && programa.reviewer ? programa.reviewer.nombre_completo : '—'}
      </p>
      {#if !programa.revisado_por}
        <p class="text-xs text-orange-600 mt-1">Pendiente aprobación</p>
      {/if}
    </div>

    <div class="bg-white p-4 rounded-lg border border-gray-200">
      <h3 class="text-sm font-semibold text-slate-700 mb-2">Secciones</h3>
      <p class="text-slate-900">{requiredSecciones.join(', ')}</p>
    </div>
  </div>

  <!-- Syllabus Editor / Viewer -->
  {#if isEditing && editedSyllabus}
    <div class="bg-white p-6 rounded-lg border border-2 border-blue-500">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-slate-900">Editar Syllabus</h2>
        <div class="flex gap-2">
          <Button variant="outline" onclick={cancelEditing} disabled={isSaving}>
            <X size={18} class="mr-2" />
            Cancelar
          </Button>
          <Button variant="default" onclick={handleSave} disabled={isSaving}>
            <Save size={18} class="mr-2" />
            {isSaving ? 'Guardando...' : 'Guardar Cambios'}
          </Button>
        </div>
      </div>

      <!-- <SyllabusEditor bind:syllabus={editedSyllabus} {requiredSecciones} /> -->
      <!-- Note: Implement SyllabusEditor or use inline section editors -->
    </div>
  {:else}
    <div class="bg-white p-6 rounded-lg border border-gray-200">
      <h2 class="text-xl font-bold text-slate-900 mb-4">Contenido del Syllabus</h2>

      {#if programa.data_syllabus?.secciones}
        {@const filledSecciones = requiredSecciones.filter((s) => hasContent(programa.data_syllabus.secciones?.[s]?.contenido))}
        {#if filledSecciones.length === 0}
          <p class="text-slate-500 italic">Ninguna sección tiene contenido aún.</p>
        {:else}
          <div class="space-y-6">
            {#each filledSecciones as seccion}
              {@const c = programa.data_syllabus.secciones![seccion].contenido}
              <div class="border-l-4 border-blue-500 pl-4 py-2">
                <h3 class="text-lg font-bold text-slate-900 mb-3">
                  {seccion}. {SECTION_TITLES[seccion] ?? `Sección ${seccion}`}
                </h3>

                <!-- I: Identificación -->
                {#if seccion === 'I'}
                  <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                    {#if c.nombre_asignatura}<div class="contents">
                        <dt class="text-slate-500 font-medium">Asignatura</dt>
                        <dd class="text-slate-800">{c.nombre_asignatura}</dd>
                      </div>{/if}
                    {#if c.codigo}<div class="contents">
                        <dt class="text-slate-500 font-medium">Código</dt>
                        <dd class="text-slate-800">{c.codigo}</dd>
                      </div>{/if}
                    {#if c.creditos_sct}<div class="contents">
                        <dt class="text-slate-500 font-medium">Créditos SCT</dt>
                        <dd class="text-slate-800">{c.creditos_sct}</dd>
                      </div>{/if}
                    {#if c.categoria}<div class="contents">
                        <dt class="text-slate-500 font-medium">Categoría</dt>
                        <dd class="text-slate-800">{c.categoria}</dd>
                      </div>{/if}
                    {#if c.horas}
                      <div class="contents">
                        <dt class="text-slate-500 font-medium">Horas</dt>
                        <dd class="text-slate-800">
                          {[
                            c.horas.catedra && `Cátedra: ${c.horas.catedra}`,
                            c.horas.taller && `Taller: ${c.horas.taller}`,
                            c.horas.laboratorio && `Lab: ${c.horas.laboratorio}`,
                          ]
                            .filter(Boolean)
                            .join(' | ')}
                        </dd>
                      </div>
                    {/if}
                  </dl>

                  <!-- II: Presentación -->
                {:else if seccion === 'II'}
                  <p class="text-slate-700 text-sm whitespace-pre-wrap leading-relaxed">{c.texto}</p>

                  <!-- VI: Unidades -->
                {:else if seccion === 'VI' && c.unidades}
                  <div class="space-y-4">
                    {#each c.unidades as unidad}
                      <div class="bg-slate-50 rounded-lg p-4">
                        <p class="text-sm font-semibold text-slate-800 mb-2">Unidad {unidad.numero}: {unidad.titulo}</p>
                        {#if unidad.contenidos_items?.length}
                          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Contenidos</p>
                          <ul class="list-disc list-inside space-y-1">
                            {#each unidad.contenidos_items as ci}
                              <li class="text-sm text-slate-700">{ci.item}</li>
                            {/each}
                          </ul>
                        {/if}
                        {#if unidad.resultados_aprendizaje?.length}
                          <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mt-2 mb-1">Resultados de aprendizaje</p>
                          <ul class="list-disc list-inside space-y-1">
                            {#each unidad.resultados_aprendizaje as ra}
                              <li class="text-sm text-slate-700">{ra.resultado}</li>
                            {/each}
                          </ul>
                        {/if}
                      </div>
                    {/each}
                  </div>

                  <!-- VII: Planificación -->
                {:else if seccion === 'VII'}
                  {#if c.actividades?.length}
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">Actividades</p>
                    <ul class="space-y-1">
                      {#each c.actividades as act}
                        <li class="text-sm text-slate-700 flex gap-2">
                          <span class="text-slate-400 capitalize">[{act.tipo}]</span>
                          <span>{act.nombre}</span>
                        </li>
                      {/each}
                    </ul>
                  {/if}
                  {#if c.evaluaciones_items?.length}
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mt-3 mb-2">Evaluaciones</p>
                    <ul class="list-disc list-inside space-y-1">
                      {#each c.evaluaciones_items as ei}<li class="text-sm text-slate-700">{ei.item}</li>{/each}
                    </ul>
                  {/if}
                  {#if c.metodologia_items?.length}
                    <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mt-3 mb-2">Metodología</p>
                    <ul class="list-disc list-inside space-y-1">
                      {#each c.metodologia_items as mi}<li class="text-sm text-slate-700">{mi.item}</li>{/each}
                    </ul>
                  {/if}

                  <!-- VIII: Recursos -->
                {:else if seccion === 'VIII' && c.recursos?.length}
                  <ul class="space-y-1">
                    {#each c.recursos as r}
                      <li class="text-sm text-slate-700">
                        {#if r.url}<a href={r.url} target="_blank" class="text-blue-600 hover:underline">{r.titulo ?? r.url}</a>{:else}{r.titulo ??
                            r}{/if}
                        {#if r.tipo}<span class="ml-2 text-xs text-slate-400">[{r.tipo}]</span>{/if}
                      </li>
                    {/each}
                  </ul>

                  <!-- Fallback for other sections (III–V, IX) -->
                {:else if typeof c === 'string'}
                  <p class="text-slate-700 text-sm whitespace-pre-wrap">{c}</p>
                {:else}
                  <pre class="text-xs text-slate-600 bg-slate-50 rounded p-3 overflow-x-auto">{JSON.stringify(c, null, 2)}</pre>
                {/if}
              </div>
            {/each}
          </div>
        {/if}
      {:else}
        <p class="text-slate-500 italic">Sin contenido disponible</p>
      {/if}
    </div>
  {/if}

  <!-- Conversion Notice (for BASICO) -->
  {#if programa.data_syllabus?.metadata?.tipo_syllabus === 'BASICO' && isEditing}
    <div class="bg-amber-50 border border-amber-300 rounded-lg p-4">
      <p class="text-sm text-amber-900">
        <strong>ℹ️ Nota:</strong> Si añade las secciones III, IV, V o IX a este programa Básico, se convertirá automáticamente a tipo COMPLETO.
      </p>
    </div>
  {/if}
</div>
