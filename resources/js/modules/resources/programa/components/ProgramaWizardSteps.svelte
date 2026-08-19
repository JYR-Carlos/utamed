<script lang="ts">
  import type { WizardUnidad, WizardActividad, WizardComponente } from '@/modules/resources/programa/types/programa.types';

  interface Props {
    step: number;
    syllabusType?: 'simplified' | 'combined' | 'complete' | null;
    curso: any;

    // Sección I: Identificación
    codigo: string;
    creditos_sct: string;
    horas_catedra: string;
    horas_taller: string;
    horas_laboratorio: string;
    categoria: string;

    // Sección II: Presentación
    presentacion: string;

    // Sección III: Estándares
    estandares: string;

    // Sección IV: Competencias
    competencias_especificas: { titulo: string }[];
    competencias_genericas: { titulo: string }[];
    subcompetencias: { titulo: string }[];

    // Sección V: Evaluación Diagnóstica
    items_evaluacion: { titulo: string; descripcion: string }[];

    // Sección VII (BASICO): Actividades
    actividades: WizardActividad[];
    existingActividades?: { id_actividad: number; nombre: string; fecha_limite: string }[];

    // Sección VI: Unidades
    unidades: WizardUnidad[];

    // Sección VII: Planificación
    resultados_aprendizaje: { resultado: string }[];
    metodologia: string;
    evaluacion: string;

    // Sección VIII: Recursos
    recursos: { descripcion: string; tipo: string; ubicacion: string }[];

    // Sección IX: Aspectos Administrativos
    normativa_curso: string;
    ponderacion_optativa: string;
    componentes: WizardComponente[];
  }

  let {
    step,
    syllabusType = null,
    curso,
    codigo = $bindable(),
    creditos_sct = $bindable(),
    horas_catedra = $bindable(),
    horas_taller = $bindable(),
    horas_laboratorio = $bindable(),
    categoria = $bindable(),
    presentacion = $bindable(),
    estandares = $bindable(),
    competencias_especificas = $bindable(),
    competencias_genericas = $bindable(),
    subcompetencias = $bindable(),
    items_evaluacion = $bindable(),
    actividades = $bindable(),
    existingActividades = [],
    unidades = $bindable(),
    resultados_aprendizaje,
    metodologia = $bindable(),
    evaluacion = $bindable(),
    recursos = $bindable(),
    normativa_curso = $bindable(),
    ponderacion_optativa = $bindable(),
    componentes = $bindable(),
  }: Props = $props();

  // Helper functions
  function addItem(arr: any[], template: any) {
    return [...arr, template];
  }

  function removeItem(arr: any[], index: number) {
    return arr.filter((_, i) => i !== index);
  }
</script>

{#if step === 1}
  <!-- Sección I: Identificación -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    {#if syllabusType === 'simplified'}
      <div class="rounded-lg bg-blue-50 border border-blue-200 p-3 mb-4">
        <p class="text-sm text-blue-800">
          <strong>Modo Simplificado:</strong> Estamos en la <strong>Edición Simplificada</strong>. Completa solo los campos esenciales para una
          creación rápida.
        </p>
      </div>
    {:else if syllabusType === 'combined'}
      <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 mb-4">
        <p class="text-sm text-amber-800">
          <strong>Modo Combinado:</strong> Continuarás desde tu edición simplificada y ahora completarás todos los detalles.
        </p>
      </div>
    {:else if syllabusType === 'complete'}
      <div class="rounded-lg bg-green-50 border border-green-200 p-3 mb-4">
        <p class="text-sm text-green-800">
          <strong>Syllabus Completo:</strong> Crearás todas las 9 secciones con información detallada. Asegúrate de tener toda la información disponible.
        </p>
      </div>
    {/if}

    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">📚 I. Identificación</h3>
      <p class="text-sm text-slate-500 mt-0.5">Información básica de la asignatura</p>
    </div>

    <div class="space-y-1.5">
      <label for="asignatura-input" class="block text-sm font-semibold text-slate-700">
        Asignatura <span class="text-red-500">*</span>
      </label>
      <input
        id="asignatura-input"
        type="text"
        value={curso.asignatura_nombre ?? ''}
        disabled
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed"
      />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="space-y-1.5">
        <label for="codigo-input" class="block text-sm font-semibold text-slate-700">
          Código <span class="text-red-500">*</span>
        </label>
        <input
          id="codigo-input"
          type="text"
          value={codigo}
          disabled
          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed"
        />
      </div>
      <div class="space-y-1.5">
        <label for="creditos-input" class="block text-sm font-semibold text-slate-700">
          Créditos SCT <span class="text-red-500">*</span>
        </label>
        <input
          id="creditos-input"
          type="number"
          value={creditos_sct}
          disabled
          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed"
        />
      </div>
    </div>

    <div class="space-y-1.5">
      <label for="categoria-select" class="block text-sm font-semibold text-slate-700">Categoría</label>
      <select
        id="categoria-select"
        bind:value={categoria}
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="Obligatorio">Obligatorio</option>
        <option value="Electivo">Electivo</option>
        <option value="Nivelación">Nivelación</option>
        <option value="Complementaria">Complementaria</option>
      </select>
    </div>

    <div class="space-y-2">
      <div class="block text-sm font-semibold text-slate-700">Horas de Dedicación</div>
      <div class="grid grid-cols-3 gap-3">
        <div class="space-y-1">
          <label for="horas-catedra-input" class="block text-xs text-slate-600">Cátedra <span class="text-red-500">*</span></label>
          <input
            id="horas-catedra-input"
            type="number"
            value={horas_catedra}
            disabled
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed"
          />
        </div>
        <div class="space-y-1">
          <label for="horas-taller-input" class="block text-xs text-slate-600">Taller</label>
          <input
            id="horas-taller-input"
            type="number"
            value={horas_taller}
            disabled
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed"
          />
        </div>
        <div class="space-y-1">
          <label for="horas-laboratorio-input" class="block text-xs text-slate-600">Laboratorio</label>
          <input
            id="horas-laboratorio-input"
            type="number"
            value={horas_laboratorio}
            disabled
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-slate-50 text-slate-500 cursor-not-allowed"
          />
        </div>
      </div>
    </div>
  </div>
{:else if step === 2}
  <!-- Sección II: Presentación -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">📝 II. Presentación</h3>
      <p class="text-sm text-slate-500 mt-0.5">Descripción general y contexto de la asignatura</p>
    </div>
    <textarea
      bind:value={presentacion}
      rows="6"
      placeholder="Describe el propósito, importancia y contexto de la asignatura..."
      class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
    ></textarea>
    <p class="text-xs text-slate-400">{presentacion.length} caracteres</p>
  </div>
{:else if step === 3}
  <!-- Sección III: Estándares -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">📋 III. Estándares</h3>
      <p class="text-sm text-slate-500 mt-0.5">Estándares y marcos de referencia</p>
    </div>
    <textarea
      bind:value={estandares}
      rows="6"
      placeholder="Describe los estándares académicos, marcos de referencia y normativas aplicables..."
      class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
    ></textarea>
    <p class="text-xs text-slate-400">{estandares.length} caracteres</p>
  </div>
{:else if step === 4}
  <!-- Sección IV: Competencias -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-6">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">🎯 IV. Competencias</h3>
      <p class="text-sm text-slate-500 mt-0.5">Competencias específicas, genéricas y subcompetencias</p>
    </div>

    <div class="space-y-3">
      <h4 class="font-semibold text-slate-800">Competencias Específicas</h4>
      {#each competencias_especificas as comp, i}
        <div class="flex gap-2">
          <input
            type="text"
            bind:value={comp.titulo}
            placeholder="Ej: Analizar estructuras biológicas"
            class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
          {#if competencias_especificas.length > 1}
            <button
              type="button"
              title="Eliminar competencia específica"
              onclick={() => (competencias_especificas = removeItem(competencias_especificas, i))}
              class="p-2 text-red-600 hover:bg-red-50 rounded transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
              >
            </button>
          {/if}
        </div>
      {/each}
      <button
        type="button"
        onclick={() => (competencias_especificas = addItem(competencias_especificas, { titulo: '' }))}
        class="text-sm text-blue-600 hover:underline">+ Agregar competencia específica</button
      >
    </div>

    <div class="space-y-3">
      <h4 class="font-semibold text-slate-800">Competencias Genéricas</h4>
      {#each competencias_genericas as comp, i}
        <div class="flex gap-2">
          <input
            type="text"
            bind:value={comp.titulo}
            placeholder="Ej: Comunicación efectiva"
            class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
          {#if competencias_genericas.length > 1}
            <button
              type="button"
              title="Eliminar competencia genérica"
              onclick={() => (competencias_genericas = removeItem(competencias_genericas, i))}
              class="p-2 text-red-600 hover:bg-red-50 rounded transition-colors"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
              >
            </button>
          {/if}
        </div>
      {/each}
      <button
        type="button"
        onclick={() => (competencias_genericas = addItem(competencias_genericas, { titulo: '' }))}
        class="text-sm text-blue-600 hover:underline">+ Agregar competencia genérica</button
      >
    </div>

    <div class="space-y-3">
      <h4 class="font-semibold text-slate-800">Subcompetencias (opcional)</h4>
      {#each subcompetencias as sub, i}
        <div class="flex gap-2">
          <input
            type="text"
            bind:value={sub.titulo}
            placeholder="Ej: Clasificación de organismos"
            class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
          <button
            type="button"
            title="Eliminar subcompetencia"
            onclick={() => (subcompetencias = removeItem(subcompetencias, i))}
            class="p-2 text-red-600 hover:bg-red-50 rounded transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
            >
          </button>
        </div>
      {/each}
      <button type="button" onclick={() => (subcompetencias = addItem(subcompetencias, { titulo: '' }))} class="text-sm text-blue-600 hover:underline"
        >+ Agregar subcompetencia</button
      >
    </div>
  </div>
{:else if step === 5}
  <!-- Sección V: Evaluación Diagnóstica -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">📊 V. Evaluación Diagnóstica</h3>
      <p class="text-sm text-slate-500 mt-0.5">Preguntas o ítems para evaluar conocimientos previos</p>
    </div>
    <div class="space-y-3">
      {#each items_evaluacion as item, i}
        <div class="rounded-lg border border-slate-200 p-3 space-y-2">
          <input
            type="text"
            bind:value={item.titulo}
            placeholder="Título del ítem"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
          <textarea
            bind:value={item.descripcion}
            placeholder="Descripción (opcional)"
            rows="2"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
          ></textarea>
          {#if items_evaluacion.length > 1}
            <button type="button" onclick={() => (items_evaluacion = removeItem(items_evaluacion, i))} class="text-xs text-red-600 hover:underline"
              >Eliminar</button
            >
          {/if}
        </div>
      {/each}
      <button
        type="button"
        onclick={() => (items_evaluacion = addItem(items_evaluacion, { titulo: '', descripcion: '' }))}
        class="w-full px-3 py-2 border-2 border-dashed border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 text-sm">+ Agregar ítem</button
      >
    </div>
  </div>
{:else if step === 6 && (syllabusType === 'simplified' || syllabusType === 'combined')}
  <!-- Sección VI: Unidades + Actividades (BASICO) -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-6">
    <!-- Unidades -->
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate mb-3">📖 VI. Unidades</h3>
      <div class="space-y-4">
        {#each unidades as unit, i}
          <div class="rounded-lg border border-slate-200 p-4 space-y-3 border-l-4 border-l-blue-600">
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold text-slate-700">U{unit.numero}. {unit.titulo || 'Unidad'}</span>
              {#if unidades.length > 1}
                <button
                  type="button"
                  onclick={() => (unidades = removeItem(unidades, i))}
                  title="Eliminar unidad"
                  class="text-red-600 hover:bg-red-50 p-1 rounded"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
                  >
                </button>
              {/if}
            </div>
            <input
              type="text"
              bind:value={unit.titulo}
              oninput={() => (unidades = [...unidades])}
              placeholder="Título de la unidad"
              class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <textarea
              bind:value={unit.contenidos}
              placeholder="Contenidos principales..."
              rows="2"
              class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            ></textarea>
            <div class="bg-blue-50 p-3 rounded-lg space-y-2">
              <h5 class="text-sm font-semibold text-slate-700">Resultados de Aprendizaje U{unit.numero}</h5>
              {#each unit.resultados_aprendizaje as ra, raIdx}
                <div class="flex gap-2">
                  <input
                    type="text"
                    bind:value={ra.resultado}
                    placeholder="Ej: El estudiante podrá..."
                    class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                  {#if unit.resultados_aprendizaje.length > 1}
                    <button
                      type="button"
                      onclick={() => {
                        unit.resultados_aprendizaje = removeItem(unit.resultados_aprendizaje, raIdx);
                        unidades = [...unidades];
                      }}
                      title="Eliminar resultado"
                      class="p-2 text-red-600 hover:bg-red-100 rounded transition-colors"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
                      >
                    </button>
                  {/if}
                </div>
              {/each}
              <button
                type="button"
                onclick={() => {
                  unit.resultados_aprendizaje = addItem(unit.resultados_aprendizaje, { resultado: '' });
                  unidades = [...unidades];
                }}
                class="text-xs text-blue-600 hover:underline"
              >
                + Agregar resultado U{unit.numero}
              </button>
            </div>
          </div>
        {/each}
        <button
          type="button"
          onclick={() =>
            (unidades = addItem(unidades, { numero: unidades.length + 1, titulo: '', contenidos: '', resultados_aprendizaje: [{ resultado: '' }] }))}
          class="w-full px-3 py-2 border-2 border-dashed border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 text-sm font-medium"
        >
          + Nueva unidad
        </button>
      </div>
    </div>

    <!-- Actividades (subsección) -->
    <div class="border-t-4 border-t-amber-200 pt-6">
      <h3 class="text-lg font-semibold text-slate-900 truncate mb-3">✅ Actividades de Aprendizaje</h3>
      <p class="text-sm text-slate-500 mb-4">Asocia actividades existentes o crea nuevas relacionadas a las unidades</p>
      <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2 mb-4">
        💡 Las nuevas actividades se asocian a una unidad específica. Puedes omitir este campo y rellenarlo después.
      </p>

      <!-- Selector de actividades existentes -->
      {#if existingActividades.length > 0}
        <div class="space-y-3 border-l-4 border-blue-300 pl-4 py-3 mb-4">
          <h4 class="text-sm font-semibold text-slate-700">📋 Actividades existentes del curso:</h4>
          <div class="space-y-2 max-h-40 overflow-y-auto">
            {#each existingActividades as act}
              <button
                type="button"
                onclick={() => {
                  if (!actividades.some((a) => a.id_actividad === act.id_actividad)) {
                    actividades = [
                      ...actividades,
                      { id_actividad: act.id_actividad, nombre: act.nombre, tipo: 'tarea', id_unidad: null, id_seccion: null, nombre_unidad: '' },
                    ];
                  }
                }}
                class="w-full px-3 py-2 text-left text-sm rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 transition-colors"
              >
                <div class="font-medium text-slate-800">{act.nombre}</div>
                <div class="text-xs text-slate-600">Límite: {new Date(act.fecha_limite).toLocaleDateString('es-ES')}</div>
              </button>
            {/each}
          </div>
        </div>
      {/if}

      <!-- Actividades seleccionadas/creadas -->
      <div class="space-y-3">
        <h4 class="text-sm font-semibold text-slate-700">
          ✅ Actividades:{' '}<span class="text-xs font-normal text-slate-500">({actividades.length})</span>
        </h4>
        {#each actividades as act, i}
          <div class="rounded-lg border border-slate-200 p-4 space-y-3 bg-slate-50">
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label for="act-nombre-{i}" class="block text-sm font-semibold text-slate-700 mb-1">Nombre</label>
                <input
                  id="act-nombre-{i}"
                  type="text"
                  bind:value={act.nombre}
                  placeholder="Ej: Trabajo colaborativo"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <label for="act-tipo-{i}" class="block text-sm font-semibold text-slate-700 mb-1">Tipo</label>
                <select
                  bind:value={act.tipo}
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="participación">Participación</option>
                  <option value="tarea">Tarea</option>
                  <option value="proyecto">Proyecto</option>
                  <option value="evaluación">Evaluación</option>
                  <option value="discusión">Discusión</option>
                  <option value="otra">Otra</option>
                </select>
              </div>
              <div>
                <label for="act-unidad-{i}" class="block text-sm font-semibold text-slate-700 mb-1">Unidad</label>
                <select
                  bind:value={act.nombre_unidad}
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">-- Selecciona unidad --</option>
                  {#each unidades as unidad}
                    <option value={unidad.titulo}>{unidad.numero}. {unidad.titulo}</option>
                  {/each}
                </select>
              </div>
            </div>
            {#if actividades.length > 1}
              <button type="button" onclick={() => (actividades = removeItem(actividades, i))} class="text-xs text-red-600 hover:underline">
                ✕ Eliminar
              </button>
            {/if}
          </div>
        {/each}
        <button
          type="button"
          onclick={() =>
            (actividades = addItem(actividades, {
              id_actividad: null,
              nombre: '',
              tipo: 'participación',
              id_unidad: null,
              id_seccion: null,
              nombre_unidad: '',
            }))}
          class="w-full px-3 py-2 border-2 border-dashed border-green-300 text-green-600 rounded-lg hover:bg-green-50 text-sm font-medium transition-colors"
        >
          + Crear nueva actividad
        </button>
      </div>
    </div>
  </div>
{:else if step === 6 && syllabusType === 'complete'}
  <!-- Sección VI: Unidades -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">📖 VI. Unidades y Resultados de Aprendizaje</h3>
      <p class="text-sm text-slate-500 mt-0.5">Estructura temática y resultados esperados por unidad</p>
    </div>
    <div class="space-y-4">
      {#each unidades as unit, i}
        <div class="rounded-lg border border-slate-200 p-4 space-y-3 border-l-4 border-l-blue-600">
          <div class="flex items-center justify-between">
            <span class="text-sm font-semibold text-slate-700">U{unit.numero}. {unit.titulo || 'Unidad'}</span>
            {#if unidades.length > 1}
              <button
                type="button"
                onclick={() => (unidades = removeItem(unidades, i))}
                title="Eliminar unidad"
                class="text-red-600 hover:bg-red-50 p-1 rounded"
              >
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  ><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
                >
              </button>
            {/if}
          </div>

          <!-- Título de unidad -->
          <input
            type="text"
            bind:value={unit.titulo}
            placeholder="Título de la unidad"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />

          <!-- Contenidos de unidad -->
          <textarea
            bind:value={unit.contenidos}
            placeholder="Contenidos principales..."
            rows="2"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          ></textarea>

          <!-- Resultados de Aprendizaje por Unidad -->
          <div class="bg-blue-50 p-3 rounded-lg space-y-2">
            <h5 class="text-sm font-semibold text-slate-700">Resultados de Aprendizaje U{unit.numero}</h5>
            {#each unit.resultados_aprendizaje as ra, raIdx}
              <div class="flex gap-2">
                <input
                  type="text"
                  bind:value={ra.resultado}
                  placeholder="Ej: El estudiante podrá..."
                  class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                {#if unit.resultados_aprendizaje.length > 1}
                  <button
                    type="button"
                    onclick={() => {
                      unit.resultados_aprendizaje = removeItem(unit.resultados_aprendizaje, raIdx);
                      unidades = [...unidades];
                    }}
                    title="Eliminar resultado"
                    class="p-2 text-red-600 hover:bg-red-100 rounded transition-colors"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg
                    >
                  </button>
                {/if}
              </div>
            {/each}
            <button
              type="button"
              onclick={() => {
                unit.resultados_aprendizaje = addItem(unit.resultados_aprendizaje, { resultado: '' });
                unidades = [...unidades];
              }}
              class="text-xs text-blue-600 hover:underline"
            >
              + Agregar resultado U{unit.numero}
            </button>
          </div>
        </div>
      {/each}
      <button
        type="button"
        onclick={() =>
          (unidades = addItem(unidades, { numero: unidades.length + 1, titulo: '', contenidos: '', resultados_aprendizaje: [{ resultado: '' }] }))}
        class="w-full px-3 py-2 border-2 border-dashed border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 text-sm font-medium"
      >
        + Nueva unidad
      </button>
    </div>
  </div>
{:else if step === 7 && syllabusType === 'complete'}
  <!-- Sección VII: Planificación -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">📅 VII. Planificación</h3>
      <p class="text-sm text-slate-500 mt-0.5">Metodología, evaluación y resultados de aprendizaje</p>
    </div>

    <div class="space-y-3">
      <div>
        <div class="block text-sm font-semibold text-slate-700 mb-2">Resultados de Aprendizaje Consolidados</div>
        <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
          {#if resultados_aprendizaje.length > 0}
            <ul class="space-y-1.5">
              {#each resultados_aprendizaje as ra}
                <li class="text-sm text-slate-700 flex items-start gap-2">
                  <span class="text-blue-600 font-semibold mt-0.5">•</span>
                  <span>{ra.resultado}</span>
                </li>
              {/each}
            </ul>
            <p class="text-xs text-slate-500 mt-2 italic">
              Estos resultados se consolidan automáticamente desde las unidades (Sección VI). Edítalos en cada unidad si es necesario.
            </p>
          {:else}
            <p class="text-sm text-slate-500 italic">
              No hay resultados de aprendizaje definidos. Agrega contenidos y resultados en las unidades (Sección VI).
            </p>
          {/if}
        </div>
      </div>

      <div>
        <label for="metodologia-textarea" class="block text-sm font-semibold text-slate-700 mb-2">Metodología</label>
        <textarea
          id="metodologia-textarea"
          bind:value={metodologia}
          placeholder="Describe las estrategias de enseñanza..."
          rows="3"
          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        ></textarea>
      </div>

      <div>
        <label for="evaluacion-textarea" class="block text-sm font-semibold text-slate-700 mb-2">Evaluación</label>
        <textarea
          id="evaluacion-textarea"
          bind:value={evaluacion}
          placeholder="Describe los criterios y métodos de evaluación..."
          rows="3"
          class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        ></textarea>
      </div>
    </div>
  </div>
{:else if step === 8 && syllabusType === 'complete'}
  <!-- Sección VIII: Recursos -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">📚 VIII. Recursos</h3>
      <p class="text-sm text-slate-500 mt-0.5">Libros, documentos y materiales (mínimo 2)</p>
    </div>
    <div class="space-y-3">
      {#each recursos as rec, i}
        <div class="rounded-lg border border-slate-200 p-4 space-y-2">
          <input
            type="text"
            bind:value={rec.descripcion}
            placeholder="Descripción del recurso"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
          <select bind:value={rec.tipo} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option>Libro</option>
            <option>Documentación Online</option>
            <option>Video</option>
            <option>Herramienta Software</option>
            <option>Base de Datos</option>
          </select>
          <input
            type="text"
            bind:value={rec.ubicacion}
            placeholder="URL o ubicación (opcional)"
            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
          />
          {#if recursos.length > 2}
            <button type="button" onclick={() => (recursos = removeItem(recursos, i))} class="text-xs text-red-600 hover:underline">Eliminar</button>
          {/if}
        </div>
      {/each}
      {#if recursos.length < 10}
        <button
          type="button"
          onclick={() => (recursos = addItem(recursos, { descripcion: '', tipo: 'Libro', ubicacion: '' }))}
          class="w-full px-3 py-2 border-2 border-dashed border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 text-sm">+ Agregar recurso</button
        >
      {/if}
    </div>
  </div>
{:else if step === 9 && syllabusType === 'complete'}
  <!-- Sección IX: Aspectos Administrativos -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-4">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate">⚙️ IX. Aspectos Administrativos</h3>
      <p class="text-sm text-slate-500 mt-0.5">Información administrativa y componentes de evaluación</p>
      <p class="text-xs text-blue-600 mt-2 p-2 bg-blue-50 rounded">
        📌 Los componentes de evaluación se cargan automáticamente desde las secciones del curso. Puedes editarlos aquí si es necesario.
      </p>
    </div>

    <div>
      <label for="ponderacion-optativa-input" class="block text-sm font-semibold text-slate-700 mb-2">Ponderación Optativa (%)</label>
      <input
        id="ponderacion-optativa-input"
        type="number"
        bind:value={ponderacion_optativa}
        placeholder="0"
        min="0"
        max="100"
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
      />
    </div>

    <div class="space-y-3">
      <h4 class="font-semibold text-slate-800">Componentes de Evaluación</h4>
      <div class="overflow-x-auto border border-slate-200 rounded-lg">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50">
              <th class="px-3 py-2 text-left font-semibold text-slate-700">Componente</th>
              <th class="px-3 py-2 text-center font-semibold text-slate-700">Genera Acta</th>
              <th class="px-3 py-2 text-center font-semibold text-slate-700">Porcentaje %</th>
              <th class="px-3 py-2 text-center font-semibold text-slate-700">Aprobación Obligatoria</th>
              <th class="px-3 py-2 text-center font-semibold text-slate-700">Asistencia Obligatoria %</th>
              <th class="px-3 py-2 text-center font-semibold text-slate-700">Acciones</th>
            </tr>
          </thead>
          <tbody>
            {#each componentes as comp, i}
              <tr class="border-b border-slate-200 hover:bg-slate-50 bg-blue-50">
                <td class="px-3 py-2">
                  <input
                    type="text"
                    bind:value={comp.componente}
                    placeholder="Ej: Examen, Proyecto"
                    class="w-full px-2 py-1 border border-slate-300 rounded text-sm bg-white"
                  />
                </td>
                <td class="px-3 py-2 text-center">
                  <input type="checkbox" bind:checked={comp.genera_acta} class="w-4 h-4 rounded" />
                </td>
                <td class="px-3 py-2">
                  <input
                    type="number"
                    bind:value={comp.porcentaje}
                    placeholder="0"
                    min="0"
                    max="100"
                    class="w-16 px-2 py-1 border border-slate-300 rounded text-sm bg-white"
                  />
                </td>
                <td class="px-3 py-2 text-center">
                  <input type="checkbox" bind:checked={comp.aprobacion_obligatoria} class="w-4 h-4 rounded" />
                </td>
                <td class="px-3 py-2">
                  <input
                    type="number"
                    bind:value={comp.asistencia_obligatoria}
                    placeholder="0"
                    min="0"
                    max="100"
                    class="w-16 px-2 py-1 border border-slate-300 rounded text-sm bg-white"
                  />
                </td>
                <td class="px-3 py-2 text-center">
                  {#if componentes.length > 1}
                    <button type="button" onclick={() => (componentes = removeItem(componentes, i))} class="text-xs text-red-600 hover:underline">
                      Eliminar
                    </button>
                  {/if}
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
      <button
        type="button"
        onclick={() =>
          (componentes = addItem(componentes, {
            componente: '',
            porcentaje: 0,
            genera_acta: false,
            aprobacion_obligatoria: false,
            asistencia_obligatoria: 0,
          }))}
        class="w-full px-3 py-2 border-2 border-dashed border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 text-sm">+ Agregar componente</button
      >
    </div>

    <div>
      <label for="normativa-textarea" class="block text-sm font-semibold text-slate-700 mb-2">Normativa del Curso</label>
      <textarea
        id="normativa-textarea"
        bind:value={normativa_curso}
        placeholder="Descripción de requisitos, normas y regulaciones del curso..."
        rows="4"
        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
      ></textarea>
    </div>

    <!-- Actividades (subsección al final para COMPLETO) -->
    <div class="border-t-4 border-t-orange-200 pt-6 mt-6">
      <h3 class="text-lg font-semibold text-slate-900 truncate mb-3">✅ Actividades de Aprendizaje (Opcional)</h3>
      <p class="text-sm text-slate-500 mb-4">Asocia actividades existentes o crea nuevas relacionadas a las unidades</p>
      <p class="text-xs text-orange-700 bg-orange-50 border border-orange-200 rounded p-2 mb-4">
        💡 Las nuevas actividades se asocian a una unidad específica. Puedes omitir este campo y rellenarlo después.
      </p>

      <!-- Selector de actividades existentes -->
      {#if existingActividades.length > 0}
        <div class="space-y-3 border-l-4 border-blue-300 pl-4 py-3 mb-4">
          <h4 class="text-sm font-semibold text-slate-700">📋 Actividades existentes del curso:</h4>
          <div class="space-y-2 max-h-40 overflow-y-auto">
            {#each existingActividades as act}
              <button
                type="button"
                onclick={() => {
                  if (!actividades.some((a) => a.id_actividad === act.id_actividad)) {
                    actividades = [
                      ...actividades,
                      { id_actividad: act.id_actividad, nombre: act.nombre, tipo: 'tarea', id_unidad: null, id_seccion: null, nombre_unidad: '' },
                    ];
                  }
                }}
                class="w-full px-3 py-2 text-left text-sm rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 transition-colors"
              >
                <div class="font-medium text-slate-800">{act.nombre}</div>
                <div class="text-xs text-slate-600">Límite: {new Date(act.fecha_limite).toLocaleDateString('es-ES')}</div>
              </button>
            {/each}
          </div>
        </div>
      {/if}

      <!-- Actividades seleccionadas/creadas -->
      <div class="space-y-3">
        <h4 class="text-sm font-semibold text-slate-700">
          ✅ Actividades:{' '}<span class="text-xs font-normal text-slate-500">({actividades.length})</span>
        </h4>
        {#each actividades as act, i}
          <div class="rounded-lg border border-slate-200 p-4 space-y-3 bg-slate-50">
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label for="act-nombre-{i}" class="block text-sm font-semibold text-slate-700 mb-1">Nombre</label>
                <input
                  id="act-nombre-{i}"
                  type="text"
                  bind:value={act.nombre}
                  placeholder="Ej: Trabajo colaborativo"
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <label for="act-tipo-{i}" class="block text-sm font-semibold text-slate-700 mb-1">Tipo</label>
                <select
                  bind:value={act.tipo}
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="participación">Participación</option>
                  <option value="tarea">Tarea</option>
                  <option value="proyecto">Proyecto</option>
                  <option value="evaluación">Evaluación</option>
                  <option value="discusión">Discusión</option>
                  <option value="otra">Otra</option>
                </select>
              </div>
              <div>
                <label for="act-unidad-{i}" class="block text-sm font-semibold text-slate-700 mb-1">Unidad</label>
                <select
                  bind:value={act.nombre_unidad}
                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">-- Selecciona unidad --</option>
                  {#each unidades as unidad}
                    <option value={unidad.titulo}>{unidad.numero}. {unidad.titulo}</option>
                  {/each}
                </select>
              </div>
            </div>
            {#if actividades.length > 1}
              <button type="button" onclick={() => (actividades = removeItem(actividades, i))} class="text-xs text-red-600 hover:underline">
                ✕ Eliminar
              </button>
            {/if}
          </div>
        {/each}
        <button
          type="button"
          onclick={() =>
            (actividades = addItem(actividades, {
              id_actividad: null,
              nombre: '',
              tipo: 'participación',
              id_unidad: null,
              id_seccion: null,
              nombre_unidad: '',
            }))}
          class="w-full px-3 py-2 border-2 border-dashed border-green-300 text-green-600 rounded-lg hover:bg-green-50 text-sm font-medium transition-colors"
        >
          + Crear nueva actividad
        </button>
      </div>
    </div>
  </div>
{:else if step === 10}
  <!-- Paso 10: Resumen/Confirmación (BASICO) -->
  <div class="rounded-lg border border-slate-200 bg-white p-5 space-y-6">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 truncate mb-2">✓ Resumen del Programa</h3>
      <p class="text-sm text-slate-500">Verifica los datos antes de generar el programa</p>
    </div>

    <!-- Identificación -->
    <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 space-y-2">
      <h4 class="font-semibold text-blue-900">📚 I. Identificación</h4>
      <div class="grid grid-cols-2 gap-3 text-sm">
        <div><span class="text-slate-600">Asignatura:</span> <span class="font-medium">{curso.asignatura_nombre}</span></div>
        <div><span class="text-slate-600">Código:</span> <span class="font-medium">{codigo}</span></div>
        <div><span class="text-slate-600">Créditos:</span> <span class="font-medium">{creditos_sct}</span></div>
        <div><span class="text-slate-600">Categoría:</span> <span class="font-medium">{categoria}</span></div>
      </div>
    </div>

    <!-- Presentación -->
    {#if presentacion.trim()}
      <div class="rounded-lg bg-green-50 border border-green-200 p-4 space-y-2">
        <h4 class="font-semibold text-green-900">📝 II. Presentación</h4>
        <p class="text-sm text-slate-700 line-clamp-3">{presentacion}</p>
      </div>
    {/if}

    <!-- Unidades -->
    {#if unidades.some((u) => u.titulo.trim())}
      <div class="rounded-lg bg-purple-50 border border-purple-200 p-4 space-y-3">
        <h4 class="font-semibold text-purple-900">📖 VI. Unidades ({unidades.filter((u) => u.titulo.trim()).length})</h4>
        <ul class="space-y-1 text-sm">
          {#each unidades.filter((u) => u.titulo.trim()) as u}
            <li class="text-slate-700">• U{u.numero}. {u.titulo}</li>
          {/each}
        </ul>
      </div>
    {/if}

    <!-- Actividades -->
    {#if actividades.some((a) => a.nombre.trim() || a.id_actividad)}
      <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 space-y-3">
        <h4 class="font-semibold text-amber-900">✅ Actividades ({actividades.filter((a) => a.nombre.trim() || a.id_actividad).length})</h4>
        <ul class="space-y-1 text-sm">
          {#each actividades.filter((a) => a.nombre.trim() || a.id_actividad) as a}
            <li class="text-slate-700">
              • {a.nombre || 'Actividad'} {a.nombre_unidad ? `(Unidad: ${a.nombre_unidad})` : ''}
            </li>
          {/each}
        </ul>
      </div>
    {/if}

    <!-- Resumen de completitud -->
    <div class="rounded-lg bg-slate-50 border border-slate-300 p-4 space-y-3">
      <h4 class="font-semibold text-slate-900">📊 Estado de Completitud</h4>
      <div class="space-y-2 text-sm">
        <div class="flex items-center gap-2">
          <span class="inline-block w-5 h-5 rounded-full {presentacion.trim() ? 'bg-green-500' : 'bg-slate-300'} flex-shrink-0"></span>
          <span class="text-slate-700">Presentación</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-5 h-5 rounded-full {unidades.some((u) => u.titulo.trim()) ? 'bg-green-500' : 'bg-slate-300'} flex-shrink-0"></span>
          <span class="text-slate-700">Unidades</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-5 h-5 rounded-full {actividades.some((a) => a.nombre.trim() || a.id_actividad) ? 'bg-green-500' : 'bg-slate-300'} flex-shrink-0"></span>
          <span class="text-slate-700">Actividades (opcional)</span>
        </div>
      </div>
    </div>

    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3">
      <p class="text-sm text-blue-800">
        <strong>✨ Listo para generar:</strong> Una vez que hagas clic en "Generar Programa", se creará tu programa de cátedra con la información completada.
        Podrás editarlo después si es necesario.
      </p>
    </div>
  </div>
{/if}
