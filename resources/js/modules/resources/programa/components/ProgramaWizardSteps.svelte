<script lang="ts">
  /**
   * Cuerpo del asistente de syllabus: el formulario de la sección abierta.
   *
   * Los campos son exactamente los que acepta
   * `App\Http\Requests\Programa\SyllabusRules` — esa clase es la allowlist con
   * la que el backend reconstruye el JSONB, así que cualquier campo que no esté
   * allí se descartaría en silencio al guardar. Por eso no se dibujan varias
   * cosas de la lámina que el modelo no guarda; están anotadas donde tocan.
   *
   * Cuando la sección no es editable por este usuario (permiso de módulo
   * `cursos/programas/modificar:modulo_N` ausente) se renderiza como documento
   * formateado, no como inputs deshabilitados: el paso se lee.
   */
  import {
    AlertCircle,
    ChevronDown,
    ChevronUp,
    Lock,
    Plus,
    Trash2,
  } from 'lucide-svelte';
  import type {
    WizardUnidad,
    WizardActividad,
    WizardComponente,
  } from '@/modules/resources/programa/types/programa.types';
  import type { IdPaso, TipoSyllabus } from '../utils/syllabusPasos';

  interface Props {
    seccion: IdPaso;
    tipo: TipoSyllabus;
    /** false → la sección se lee, no se escribe. */
    editable: boolean;
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

    // Sección VII (BÁSICO): Actividades
    actividades: WizardActividad[];
    existingActividades?: { id_actividad: number; nombre: string; fecha_limite: string }[];

    // Sección VI: Unidades
    unidades: WizardUnidad[];

    // Sección VII (COMPLETO): Planificación
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
    seccion,
    tipo,
    editable,
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

  const esBasico = $derived(tipo === 'BASICO');

  // ── Vocabulario visual ────────────────────────────────────────────────────
  const LABEL = 'text-[12px] font-medium text-[#5A5E6E]';
  const INPUT =
    'w-full rounded-[7px] border border-[#D6D9E0] bg-white px-[11px] py-[9px] text-[13.5px] text-[#1A1A24] outline-none transition-colors focus:border-[#002F6C]';
  const TEXTAREA = `${INPUT} resize-y leading-[1.55]`;
  const RO = 'rounded-[7px] bg-[#F7F8FA] px-[11px] py-[9px] text-[13.5px] text-[#1A1A24]';
  const ADD_LINK =
    'inline-flex w-fit items-center gap-1.5 border-none bg-transparent p-0 text-[12.5px] font-semibold text-[#002F6C] transition-colors hover:text-[#1B4789]';
  const ADD_BLOQUE =
    'flex items-center justify-center gap-2 rounded-[9px] border border-dashed border-[#C3C8D2] bg-white py-3.5 text-[13px] font-semibold text-[#002F6C] transition-colors hover:bg-[#F5F1EA]';
  const FILA =
    'flex items-center gap-2 rounded-[7px] border border-[#E5E7EB] bg-white px-3 py-2';
  const BORRAR =
    'shrink-0 rounded-md border-none bg-transparent p-1 text-[#C3C8D2] transition-colors hover:text-[#B91C1C]';

  // ── Sección VI: acordeón de unidades ──────────────────────────────────────
  let unidadAbierta = $state(0);

  function agregarUnidad() {
    unidades = [
      ...unidades,
      {
        numero: unidades.length + 1,
        titulo: '',
        contenidos: '',
        resultados_aprendizaje: [{ resultado: '' }],
      },
    ];
    unidadAbierta = unidades.length - 1;
  }

  /** Renumera para que `numero` siga el orden visible tras un borrado. */
  function quitarUnidad(i: number) {
    unidades = unidades
      .filter((_, idx) => idx !== i)
      .map((u, idx) => ({ ...u, numero: idx + 1 }));
    if (unidadAbierta >= unidades.length) unidadAbierta = Math.max(0, unidades.length - 1);
  }

  function tieneContenidoUnidad(u: WizardUnidad): boolean {
    return (
      !!u.titulo.trim() ||
      !!u.contenidos.trim() ||
      u.resultados_aprendizaje.some((r) => r.resultado.trim())
    );
  }

  // ── Sección IX: suma de ponderaciones ─────────────────────────────────────
  const totalPonderacion = $derived(
    componentes.reduce((acc, c) => acc + (Number(c.porcentaje) || 0), 0),
  );

  // Deben coincidir con SyllabusRules::seccionVIII() — el backend es la fuente
  // de verdad; si cambia allá, actualizar aquí también.
  const RECURSO_TIPOS = [
    'Libro',
    'Documentación Online',
    'Video',
    'Herramienta Software',
    'Base de Datos',
  ];
  const CATEGORIAS = ['Obligatorio', 'Electivo', 'Nivelación', 'Complementaria'];
  const TIPOS_ACTIVIDAD = ['participación', 'evaluación', 'taller', 'laboratorio', 'lectura'];

  function quitar<T>(arr: T[], i: number): T[] {
    return arr.filter((_, idx) => idx !== i);
  }
</script>

{#snippet encabezado(numeral: string, titulo: string, bajada: string)}
  <header class="flex flex-col gap-1">
    <h2 class="m-0 text-[17px] font-semibold text-[#1A1A24]">
      {#if numeral}{numeral}. {/if}{titulo}
    </h2>
    {#if bajada}
      <p class="m-0 text-[13px] text-pretty text-[#5A5E6E]">{bajada}</p>
    {/if}
  </header>
{/snippet}

{#snippet marca(obligatorio: boolean)}
  {#if obligatorio}
    <span class="text-[11.5px] text-[#DC2626]">obligatorio</span>
  {:else}
    <span class="text-[11px] text-[#9AA0AE]">opcional</span>
  {/if}
{/snippet}

{#snippet aviso(texto: string)}
  <p class="m-0 inline-flex items-center gap-1.5 text-[11.5px] text-[#B91C1C]">
    <AlertCircle size={13} class="shrink-0" aria-hidden="true" />
    {texto}
  </p>
{/snippet}

<!-- ══ Sección bloqueada: se lee como documento, no como formulario ══ -->
{#if !editable && seccion !== 'RESUMEN'}
  <div class="flex max-w-[760px] flex-col gap-3.5">
    <p
      class="m-0 flex items-center gap-2.5 rounded-lg border border-[#E5E7EB] bg-[#F7F8FA] px-3.5 py-2.5 text-[13px] text-[#5A5E6E]"
    >
      <Lock size={15} class="shrink-0" aria-hidden="true" />
      Esta sección es responsabilidad de otro docente. Puedes leerla, no editarla.
    </p>

    {#if seccion === 'I'}
      <dl class="m-0 grid grid-cols-1 gap-x-5 gap-y-3 sm:grid-cols-3">
        <div class="flex flex-col gap-1">
          <dt class={LABEL}>Código</dt>
          <dd class="m-0 font-mono text-[13.5px]">{codigo || '—'}</dd>
        </div>
        <div class="flex flex-col gap-1 sm:col-span-2">
          <dt class={LABEL}>Asignatura</dt>
          <dd class="m-0 text-[13.5px]">{curso?.asignatura_nombre ?? '—'}</dd>
        </div>
        <div class="flex flex-col gap-1">
          <dt class={LABEL}>Créditos SCT</dt>
          <dd class="m-0 text-[13.5px]">{creditos_sct || '—'}</dd>
        </div>
        <div class="flex flex-col gap-1">
          <dt class={LABEL}>Horas semanales</dt>
          <dd class="m-0 text-[13.5px]">
            {horas_catedra || 0} cátedra · {horas_taller || 0} taller · {horas_laboratorio || 0} lab.
          </dd>
        </div>
        <div class="flex flex-col gap-1">
          <dt class={LABEL}>Categoría</dt>
          <dd class="m-0 text-[13.5px]">{categoria || '—'}</dd>
        </div>
      </dl>
    {:else if seccion === 'II'}
      <p class="m-0 text-[15px] leading-[1.65] text-pretty whitespace-pre-wrap">
        {presentacion || 'Sin contenido.'}
      </p>
    {:else if seccion === 'III'}
      <p class="m-0 text-[15px] leading-[1.65] text-pretty whitespace-pre-wrap">
        {estandares || 'Sin contenido.'}
      </p>
    {:else if seccion === 'IV'}
      {#each [{ t: 'Específicas', l: competencias_especificas }, { t: 'Genéricas', l: competencias_genericas }, { t: 'Subcompetencias', l: subcompetencias }] as grupo}
        {#if grupo.l.some((c) => c.titulo.trim())}
          <div class="flex flex-col gap-1.5">
            <span class="text-[12px] font-semibold tracking-[0.05em] text-[#5A5E6E] uppercase"
              >{grupo.t}</span
            >
            <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] leading-[1.65]">
              {#each grupo.l.filter((c) => c.titulo.trim()) as c}
                <li>{c.titulo}</li>
              {/each}
            </ul>
          </div>
        {/if}
      {/each}
    {:else if seccion === 'V'}
      <ul class="m-0 flex list-disc flex-col gap-2 pl-5 text-[15px] leading-[1.65]">
        {#each items_evaluacion.filter((i) => i.titulo.trim()) as item}
          <li><strong class="font-semibold">{item.titulo}</strong>{item.descripcion ? `: ${item.descripcion}` : ''}</li>
        {:else}
          <li class="list-none text-[#9AA0AE]">Sin contenido.</li>
        {/each}
      </ul>
    {:else if seccion === 'VI'}
      <div class="flex flex-col gap-3.5">
        {#each unidades.filter((u) => u.titulo.trim()) as u}
          <div class="flex flex-col gap-1">
            <span class="font-mono text-[12px] text-[#5A5E6E]">Unidad {u.numero}</span>
            <span class="text-[15px] font-semibold">{u.titulo}</span>
            {#if u.contenidos.trim()}
              <p class="m-0 text-[14px] text-pretty whitespace-pre-wrap">{u.contenidos}</p>
            {/if}
          </div>
        {:else}
          <p class="m-0 text-[#9AA0AE]">Sin contenido.</p>
        {/each}
      </div>
    {:else if seccion === 'VII'}
      {#if esBasico}
        <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] leading-[1.65]">
          {#each actividades.filter((a) => a.nombre.trim()) as a}
            <li>{a.nombre}{a.tipo ? ` (${a.tipo})` : ''}</li>
          {:else}
            <li class="list-none text-[#9AA0AE]">Sin contenido.</li>
          {/each}
        </ul>
      {:else}
        <div class="flex flex-col gap-3.5 text-[15px] leading-[1.65]">
          <div class="flex flex-col gap-1.5">
            <span class="text-[12px] font-semibold tracking-[0.05em] text-[#5A5E6E] uppercase"
              >Metodología</span
            >
            <p class="m-0 text-pretty whitespace-pre-wrap">{metodologia || 'Sin contenido.'}</p>
          </div>
          <div class="flex flex-col gap-1.5">
            <span class="text-[12px] font-semibold tracking-[0.05em] text-[#5A5E6E] uppercase"
              >Evaluación</span
            >
            <p class="m-0 text-pretty whitespace-pre-wrap">{evaluacion || 'Sin contenido.'}</p>
          </div>
        </div>
      {/if}
    {:else if seccion === 'VIII'}
      <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] leading-[1.65]">
        {#each recursos.filter((r) => r.descripcion.trim()) as r}
          <li>{r.descripcion}{r.tipo ? ` (${r.tipo})` : ''}</li>
        {:else}
          <li class="list-none text-[#9AA0AE]">Sin contenido.</li>
        {/each}
      </ul>
    {:else if seccion === 'IX'}
      <div class="flex flex-col gap-3 text-[15px] leading-[1.65]">
        <p class="m-0 text-pretty whitespace-pre-wrap">{normativa_curso || 'Sin contenido.'}</p>
        <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5">
          {#each componentes.filter((c) => c.componente.trim()) as c}
            <li>{c.componente}: {c.porcentaje}%</li>
          {/each}
        </ul>
      </div>
    {/if}
  </div>

  <!-- ══ I. Identificación ══ -->
{:else if seccion === 'I'}
  <div class="flex max-w-[840px] flex-col gap-[22px]">
    {@render encabezado(
      'I',
      'Identificación',
      'Estos datos vienen de la asignatura y del grupo. Si algo está mal, se corrige en la ficha del curso.',
    )}

    <div class="grid grid-cols-1 gap-x-5 gap-y-3.5 sm:grid-cols-3">
      <div class="flex flex-col gap-1.5">
        <span class={LABEL}>Código de asignatura</span>
        <div class="{RO} font-mono">{codigo || '—'}</div>
      </div>
      <div class="flex flex-col gap-1.5 sm:col-span-2">
        <span class={LABEL}>Nombre</span>
        <div class={RO}>{curso?.asignatura_nombre ?? curso?.nombre ?? '—'}</div>
      </div>
      <div class="flex flex-col gap-1.5">
        <span class={LABEL}>Créditos SCT</span>
        <div class={RO}>{creditos_sct || '—'}</div>
      </div>
      <div class="flex flex-col gap-1.5">
        <span class={LABEL}>Semestre</span>
        <div class={RO}>{curso?.semestre_real ?? '—'}</div>
      </div>
      <div class="flex flex-col gap-1.5">
        <span class={LABEL}>Año</span>
        <div class={RO}>{curso?.agno_real ?? '—'}</div>
      </div>
      <div class="flex flex-col gap-1.5">
        <span class={LABEL}>Horas cátedra / semana</span>
        <div class={RO}>{horas_catedra || 0}</div>
      </div>
      <div class="flex flex-col gap-1.5">
        <span class={LABEL}>Horas laboratorio / semana</span>
        <div class={RO}>{horas_laboratorio || 0}</div>
      </div>
      <div class="flex flex-col gap-1.5">
        <span class={LABEL}>Horas taller / semana</span>
        <div class={RO}>{horas_taller || 0}</div>
      </div>
      <div class="flex flex-col gap-1.5 sm:col-span-2">
        <span class={LABEL}>Docente titular</span>
        <div class={RO}>{curso?.docente_titular ?? '—'}</div>
      </div>
    </div>

    <!--
      La lámina lleva aquí «Prerrequisitos» y una «descripción breve» de 140
      caracteres. Ninguna de las dos existe: no hay tabla de prerrequisitos en el
      esquema y la sección I sólo admite los campos de SyllabusRules::seccionI().
    -->
    <div class="flex flex-col gap-1.5 border-t border-[#EDEFF3] pt-5">
      <label for="categoria" class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Categoría de la asignatura</span>
        {@render marca(true)}
      </label>
      <select id="categoria" bind:value={categoria} class="{INPUT} max-w-[280px]">
        {#each CATEGORIAS as c}
          <option value={c}>{c}</option>
        {/each}
      </select>
      <span class="text-[11.5px] text-[#5A5E6E]">
        Es el único dato de esta sección que se elige aquí: `asignacion_plan.tipo_ramo` guarda un
        número sin catálogo, así que no puede rellenarla.
      </span>
    </div>
  </div>

  <!-- ══ II. Presentación ══ -->
{:else if seccion === 'II'}
  <div class="flex max-w-[840px] flex-col gap-5">
    {@render encabezado(
      'II',
      'Presentación',
      'De qué trata la asignatura y qué lugar ocupa en el plan. Es la bajada que abre el documento en el visor.',
    )}
    <div class="flex flex-col gap-1.5">
      <label for="presentacion" class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Descripción de la asignatura</span>
        {@render marca(!esBasico)}
        <span class="ml-auto font-mono text-[11.5px] text-[#9AA0AE]">
          {presentacion.trim() ? presentacion.trim().split(/\s+/).length : 0} palabras
        </span>
      </label>
      <textarea
        id="presentacion"
        rows="8"
        bind:value={presentacion}
        placeholder="Qué aborda la asignatura, cómo se organiza el trabajo y qué se espera del estudiante"
        class="{TEXTAREA} {!esBasico && !presentacion.trim()
          ? 'border-[1.5px] border-[#DC2626] bg-[#FEF7F7]'
          : ''}"
      ></textarea>
      {#if !esBasico && !presentacion.trim()}
        {@render aviso('Este campo es obligatorio.')}
      {/if}
      <!--
        La lámina separa «justificación en el plan» y «contribución al perfil de
        egreso» en dos campos más. El modelo guarda un único texto en
        `secciones.II.contenido.texto`, así que van dentro de éste.
      -->
    </div>
  </div>

  <!-- ══ III. Estándares ══ -->
{:else if seccion === 'III'}
  <div class="flex max-w-[840px] flex-col gap-5">
    {@render encabezado(
      'III',
      'Estándares',
      'Los estándares de desempeño que la asignatura compromete. Un estándar por línea.',
    )}
    <div class="flex flex-col gap-1.5">
      <label for="estandares" class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Estándares</span>
        {@render marca(true)}
      </label>
      <textarea
        id="estandares"
        rows="8"
        bind:value={estandares}
        placeholder={'Aplica marcos narrativos a productos digitales interactivos.\nDocumenta decisiones con criterios de usabilidad.'}
        class="{TEXTAREA} {!estandares.trim() ? 'border-[1.5px] border-[#DC2626] bg-[#FEF7F7]' : ''}"
      ></textarea>
      {#if !estandares.trim()}
        {@render aviso('Este campo es obligatorio.')}
      {/if}
      <!--
        La lámina propone estándares estructurados (código E-04 + nombre +
        descripción). `secciones.III.contenido` sólo admite `texto`.
      -->
    </div>
  </div>

  <!-- ══ IV. Competencias ══ -->
{:else if seccion === 'IV'}
  <div class="flex max-w-[840px] flex-col gap-6">
    {@render encabezado(
      'IV',
      'Competencias',
      'Qué es capaz de hacer quien aprueba. Las específicas son de la asignatura; las genéricas, del perfil de egreso.',
    )}

    {#each [{ clave: 'esp', titulo: 'Competencias específicas', obligatorio: true }, { clave: 'gen', titulo: 'Competencias genéricas', obligatorio: true }, { clave: 'sub', titulo: 'Subcompetencias', obligatorio: false }] as grupo}
      {@const lista =
        grupo.clave === 'esp'
          ? competencias_especificas
          : grupo.clave === 'gen'
            ? competencias_genericas
            : subcompetencias}
      <section class="flex flex-col gap-2.5">
        <div class="flex items-baseline gap-2">
          <span class="text-[12.5px] font-semibold text-[#1A1A24]">{grupo.titulo}</span>
          {@render marca(grupo.obligatorio)}
        </div>
        {#each lista as comp, i}
          <div class="flex items-center gap-2">
            <input
              type="text"
              bind:value={comp.titulo}
              placeholder="Enuncia la competencia en una frase"
              class={INPUT}
            />
            <button
              type="button"
              class={BORRAR}
              aria-label="Quitar"
              onclick={() => {
                if (grupo.clave === 'esp') competencias_especificas = quitar(competencias_especificas, i);
                else if (grupo.clave === 'gen') competencias_genericas = quitar(competencias_genericas, i);
                else subcompetencias = quitar(subcompetencias, i);
              }}
            >
              <Trash2 size={15} aria-hidden="true" />
            </button>
          </div>
        {/each}
        <button
          type="button"
          class={ADD_LINK}
          onclick={() => {
            if (grupo.clave === 'esp')
              competencias_especificas = [...competencias_especificas, { titulo: '' }];
            else if (grupo.clave === 'gen')
              competencias_genericas = [...competencias_genericas, { titulo: '' }];
            else subcompetencias = [...subcompetencias, { titulo: '' }];
          }}
        >
          <Plus size={14} aria-hidden="true" />
          Añadir
        </button>
        {#if grupo.obligatorio && !lista.some((c) => c.titulo.trim())}
          {@render aviso('Se necesita al menos una.')}
        {/if}
      </section>
    {/each}
    <!--
      La lámina añade descripción y nivel (Introductorio / Desarrollo /
      Consolidación) por competencia. El DTO `TituloItem` sólo guarda `titulo`.
    -->
  </div>

  <!-- ══ V. Evaluación Diagnóstica ══ -->
{:else if seccion === 'V'}
  <div class="flex max-w-[840px] flex-col gap-5">
    {@render encabezado(
      'V',
      'Evaluación Diagnóstica',
      'Qué se mide en la primera semana para situar el nivel de entrada del grupo.',
    )}
    {#each items_evaluacion as item, i}
      <div class="flex flex-col gap-2 rounded-[9px] border border-[#E5E7EB] bg-white p-3.5">
        <div class="flex items-center gap-2">
          <span class="w-[28px] shrink-0 font-mono text-[12px] text-[#5A5E6E]">{i + 1}</span>
          <input
            type="text"
            bind:value={item.titulo}
            placeholder="Conocimiento o habilidad que se sondea"
            class={INPUT}
          />
          <button
            type="button"
            class={BORRAR}
            aria-label="Quitar ítem"
            onclick={() => (items_evaluacion = quitar(items_evaluacion, i))}
          >
            <Trash2 size={15} aria-hidden="true" />
          </button>
        </div>
        <textarea
          rows="2"
          bind:value={item.descripcion}
          placeholder="Cómo se sondea (opcional)"
          class="{TEXTAREA} ml-[36px] w-[calc(100%-36px)]"
        ></textarea>
      </div>
    {/each}
    <button
      type="button"
      class={ADD_BLOQUE}
      onclick={() => (items_evaluacion = [...items_evaluacion, { titulo: '', descripcion: '' }])}
    >
      <Plus size={15} aria-hidden="true" />
      Añadir ítem
    </button>
    {#if !items_evaluacion.some((i) => i.titulo.trim())}
      {@render aviso('Se necesita al menos un ítem con título.')}
    {/if}
    <!-- La lámina añade un selector de «instrumento»; no existe en el modelo. -->
  </div>

  <!-- ══ VI. Unidades ══ -->
{:else if seccion === 'VI'}
  <div class="flex max-w-[900px] flex-col gap-4">
    {@render encabezado(
      'VI',
      'Unidades',
      'Las unidades temáticas del curso, con sus contenidos y objetivos de aprendizaje.',
    )}

    {#each unidades as unidad, i (i)}
      {@const abierta = unidadAbierta === i}
      <div
        class="overflow-hidden rounded-[9px] border bg-white {abierta
          ? 'border-[#002F6C] shadow-[0_1px_3px_rgba(0,47,108,.10)]'
          : 'border-[#E5E7EB]'}"
      >
        <div
          class="flex items-center gap-3 px-3.5 py-3 {abierta
            ? 'border-b border-[#E5E7EB] bg-[#F8FAFC]'
            : ''}"
        >
          <span
            class="shrink-0 font-mono text-[12px] {abierta
              ? 'font-semibold text-[#002F6C]'
              : 'text-[#5A5E6E]'}">U{unidad.numero}</span
          >
          <span class="min-w-0 flex-1 truncate text-[14px] font-medium">
            {unidad.titulo.trim() || 'Unidad sin título'}
          </span>
          {#if !abierta}
            <span class="shrink-0 text-[12px] text-[#9AA0AE]">
              {unidad.resultados_aprendizaje.filter((r) => r.resultado.trim()).length} objetivos
            </span>
          {/if}
          <button
            type="button"
            class={BORRAR}
            aria-label="Eliminar unidad {unidad.numero}"
            onclick={() => {
              if (
                !tieneContenidoUnidad(unidad) ||
                confirm(
                  `¿Eliminar la unidad ${unidad.numero}? Tiene contenido escrito y no se puede deshacer.`,
                )
              ) {
                quitarUnidad(i);
              }
            }}
          >
            <Trash2 size={15} aria-hidden="true" />
          </button>
          <button
            type="button"
            class="shrink-0 rounded-md border-none bg-transparent p-1 text-[#5A5E6E] transition-colors hover:text-[#002F6C]"
            aria-expanded={abierta}
            aria-label={abierta ? 'Contraer unidad' : 'Editar unidad'}
            onclick={() => (unidadAbierta = abierta ? -1 : i)}
          >
            {#if abierta}
              <ChevronUp size={16} aria-hidden="true" />
            {:else}
              <ChevronDown size={16} aria-hidden="true" />
            {/if}
          </button>
        </div>

        {#if abierta}
          <div class="flex flex-col gap-[18px] px-4 py-[18px]">
            <div class="flex flex-col gap-1.5">
              <span class={LABEL}>Nombre de la unidad</span>
              <input
                type="text"
                bind:value={unidad.titulo}
                placeholder="Título de la unidad"
                class={INPUT}
              />
            </div>

            <div class="flex flex-col gap-2">
              <span class={LABEL}>Objetivos de aprendizaje</span>
              {#each unidad.resultados_aprendizaje as ra, ri}
                <div class="flex items-center gap-2">
                  <input
                    type="text"
                    bind:value={ra.resultado}
                    placeholder="Qué será capaz de hacer el estudiante"
                    class={INPUT}
                  />
                  <button
                    type="button"
                    class={BORRAR}
                    aria-label="Quitar objetivo"
                    onclick={() => {
                      unidad.resultados_aprendizaje = quitar(unidad.resultados_aprendizaje, ri);
                    }}
                  >
                    <Trash2 size={14} aria-hidden="true" />
                  </button>
                </div>
              {/each}
              <button
                type="button"
                class={ADD_LINK}
                onclick={() => {
                  unidad.resultados_aprendizaje = [
                    ...unidad.resultados_aprendizaje,
                    { resultado: '' },
                  ];
                }}
              >
                <Plus size={14} aria-hidden="true" />
                Añadir objetivo
              </button>
            </div>

            <div class="flex flex-col gap-1.5">
              <span class={LABEL}>Contenidos</span>
              <textarea
                rows="3"
                bind:value={unidad.contenidos}
                placeholder="Temas que cubre la unidad"
                class={TEXTAREA}
              ></textarea>
            </div>
            <!--
              La lámina asigna horas (cátedra / laboratorio / taller) y
              bibliografía por unidad, y suma el total contra las horas
              planificadas. `UnidadSyllabus` sólo guarda numero, titulo,
              contenidos_items y resultados_aprendizaje: no hay dónde ponerlas.
            -->
          </div>
        {/if}
      </div>
    {/each}

    <button type="button" class={ADD_BLOQUE} onclick={agregarUnidad}>
      <Plus size={15} aria-hidden="true" />
      Añadir unidad
    </button>

    {#if !esBasico && !unidades.some((u) => u.titulo.trim())}
      {@render aviso('Se necesita al menos una unidad con título.')}
    {/if}
  </div>

  <!-- ══ VII. Actividades (BÁSICO) ══ -->
{:else if seccion === 'VII' && esBasico}
  <div class="flex max-w-[900px] flex-col gap-4">
    {@render encabezado(
      'VII',
      'Actividades de Aprendizaje',
      'Puedes enlazar actividades que ya existen en el curso o anotar las que están por crearse.',
    )}

    {#each actividades as act, i}
      <div class="flex flex-col gap-2.5 rounded-[9px] border border-[#E5E7EB] bg-white p-3.5">
        <div class="flex flex-col gap-1.5">
          <span class={LABEL}>Actividad del curso</span>
          <select
            class={INPUT}
            value={act.id_actividad ?? ''}
            onchange={(e) => {
              const valor = (e.currentTarget as HTMLSelectElement).value;
              act.id_actividad = valor ? Number(valor) : null;
              const existente = existingActividades.find((x) => x.id_actividad === act.id_actividad);
              if (existente) act.nombre = existente.nombre;
            }}
          >
            <option value="">— Nueva actividad (se creará al guardar) —</option>
            {#each existingActividades as ex}
              <option value={ex.id_actividad}>{ex.nombre}</option>
            {/each}
          </select>
        </div>
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
          <div class="flex flex-col gap-1.5 sm:col-span-2">
            <span class={LABEL}>Nombre</span>
            <input type="text" bind:value={act.nombre} placeholder="Nombre de la actividad" class={INPUT} />
          </div>
          <div class="flex flex-col gap-1.5">
            <span class={LABEL}>Tipo</span>
            <select bind:value={act.tipo} class={INPUT}>
              {#each TIPOS_ACTIVIDAD as t}
                <option value={t}>{t}</option>
              {/each}
            </select>
          </div>
        </div>
        <div class="flex items-end gap-2">
          <div class="flex flex-1 flex-col gap-1.5">
            <span class={LABEL}>Unidad</span>
            <select bind:value={act.nombre_unidad} class={INPUT}>
              <option value="">— Sin unidad —</option>
              {#each unidades.filter((u) => u.titulo.trim()) as u}
                <option value={u.titulo}>U{u.numero} · {u.titulo}</option>
              {/each}
            </select>
          </div>
          <button
            type="button"
            class="{BORRAR} mb-2"
            aria-label="Quitar actividad"
            onclick={() => (actividades = quitar(actividades, i))}
          >
            <Trash2 size={15} aria-hidden="true" />
          </button>
        </div>
      </div>
    {/each}

    <button
      type="button"
      class={ADD_BLOQUE}
      onclick={() =>
        (actividades = [
          ...actividades,
          {
            id_actividad: null,
            nombre: '',
            tipo: 'participación',
            id_unidad: null,
            id_seccion: null,
            nombre_unidad: '',
          },
        ])}
    >
      <Plus size={15} aria-hidden="true" />
      Añadir actividad
    </button>
  </div>

  <!-- ══ VII. Planificación (COMPLETO) ══ -->
{:else if seccion === 'VII'}
  <div class="flex max-w-[900px] flex-col gap-5">
    {@render encabezado(
      'VII',
      'Planificación',
      'Cómo se enseña y cómo se evalúa. Los resultados de aprendizaje se consolidan solos desde las unidades.',
    )}

    <section class="flex flex-col gap-2">
      <div class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Resultados de aprendizaje</span>
        <span class="text-[11px] text-[#9AA0AE]">se toman de la sección VI</span>
      </div>
      {#if resultados_aprendizaje.length > 0}
        <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
          {#each resultados_aprendizaje as r}
            <li class={FILA}>
              <span class="text-[13.5px] text-[#1A1A24]">{r.resultado}</span>
            </li>
          {/each}
        </ul>
      {:else}
        <p
          class="m-0 rounded-[7px] border border-dashed border-[#D6D9E0] bg-[#FCFBF9] px-3.5 py-3 text-[12.5px] text-[#5A5E6E]"
        >
          Escribe objetivos en las unidades de la sección VI y aparecerán aquí.
        </p>
      {/if}
      {#if resultados_aprendizaje.length === 0}
        {@render aviso('Se necesita al menos un resultado de aprendizaje.')}
      {/if}
    </section>

    <div class="flex flex-col gap-1.5">
      <label for="metodologia" class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Metodología</span>
        {@render marca(true)}
      </label>
      <textarea
        id="metodologia"
        rows="5"
        bind:value={metodologia}
        placeholder="Estrategias de enseñanza: clases, taller, proyecto, trabajo en terreno…"
        class="{TEXTAREA} {!metodologia.trim() ? 'border-[1.5px] border-[#DC2626] bg-[#FEF7F7]' : ''}"
      ></textarea>
      {#if !metodologia.trim()}
        {@render aviso('Este campo es obligatorio.')}
      {/if}
    </div>

    <div class="flex flex-col gap-1.5">
      <label for="evaluacion" class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Evaluación</span>
        {@render marca(true)}
      </label>
      <textarea
        id="evaluacion"
        rows="5"
        bind:value={evaluacion}
        placeholder="Tipos de evaluación y cómo se aplican a lo largo del semestre"
        class="{TEXTAREA} {!evaluacion.trim() ? 'border-[1.5px] border-[#DC2626] bg-[#FEF7F7]' : ''}"
      ></textarea>
      {#if !evaluacion.trim()}
        {@render aviso('Este campo es obligatorio.')}
      {/if}
    </div>
    <!--
      La lámina dibuja una planificación semana a semana (18 filas: semana,
      unidad, contenido, actividad evaluativa). No existe: no hay tabla de
      semanas ni de calendario académico, y `SeccionVIICompleto` sólo guarda
      resultados, metodología y evaluación.
    -->
  </div>

  <!-- ══ VIII. Recursos ══ -->
{:else if seccion === 'VIII'}
  <div class="flex max-w-[900px] flex-col gap-4">
    {@render encabezado(
      'VIII',
      'Recursos',
      esBasico
        ? 'Bibliografía y materiales de la asignatura.'
        : 'Bibliografía y materiales. El syllabus completo pide al menos dos.',
    )}

    {#each recursos as rec, i}
      <div class="flex flex-col gap-2.5 rounded-[9px] border border-[#E5E7EB] bg-white p-3.5">
        <div class="flex items-start gap-2">
          <div class="flex flex-1 flex-col gap-1.5">
            <span class={LABEL}>Referencia</span>
            <input
              type="text"
              bind:value={rec.descripcion}
              placeholder="Murray, J. (2017). Hamlet on the Holodeck. MIT Press."
              class={INPUT}
            />
          </div>
          <button
            type="button"
            class="{BORRAR} mt-7"
            aria-label="Quitar recurso"
            onclick={() => (recursos = quitar(recursos, i))}
          >
            <Trash2 size={15} aria-hidden="true" />
          </button>
        </div>
        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
          <div class="flex flex-col gap-1.5">
            <span class={LABEL}>Tipo</span>
            <select bind:value={rec.tipo} class={INPUT}>
              {#each RECURSO_TIPOS as t}
                <option value={t}>{t}</option>
              {/each}
            </select>
          </div>
          <div class="flex flex-col gap-1.5">
            <span class={LABEL}>Ubicación o enlace</span>
            <input
              type="text"
              bind:value={rec.ubicacion}
              placeholder="Biblioteca central · https://…"
              class={INPUT}
            />
          </div>
        </div>
      </div>
    {/each}

    <button
      type="button"
      class={ADD_BLOQUE}
      onclick={() => (recursos = [...recursos, { descripcion: '', tipo: 'Libro', ubicacion: '' }])}
    >
      <Plus size={15} aria-hidden="true" />
      Añadir referencia
    </button>

    {#if !esBasico && recursos.filter((r) => r.descripcion.trim()).length < 2}
      {@render aviso('El syllabus completo exige al menos dos referencias.')}
    {/if}
  </div>

  <!-- ══ IX. Aspectos Administrativos ══ -->
{:else if seccion === 'IX'}
  <div class="flex max-w-[860px] flex-col gap-6">
    {@render encabezado(
      'IX',
      'Aspectos Administrativos',
      'Última sección. Al guardar, el programa queda listo para revisión desde el visor.',
    )}

    <section class="flex flex-col gap-2.5">
      <div class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Componentes de evaluación</span>
        {@render marca(true)}
      </div>
      {#each componentes as comp, i}
        <div class="flex flex-col gap-2.5 rounded-[9px] border border-[#E5E7EB] bg-white p-3.5">
          <div class="flex items-center gap-2.5">
            <input
              type="text"
              bind:value={comp.componente}
              placeholder="Cátedra, Taller, Laboratorio…"
              class={INPUT}
            />
            <label class="flex w-[120px] shrink-0 items-center gap-1.5">
              <span class="sr-only">Porcentaje del componente {i + 1}</span>
              <input
                type="number"
                min="0"
                max="100"
                bind:value={comp.porcentaje}
                class="{INPUT} text-right tabular-nums"
              />
              <span class="text-[13px] text-[#9AA0AE]">%</span>
            </label>
            <button
              type="button"
              class={BORRAR}
              aria-label="Quitar componente"
              onclick={() => (componentes = quitar(componentes, i))}
            >
              <Trash2 size={15} aria-hidden="true" />
            </button>
          </div>
          <div class="flex flex-wrap items-center gap-5">
            <label class="inline-flex items-center gap-2 text-[12.5px] text-[#1A1A24]">
              <input
                type="checkbox"
                bind:checked={comp.genera_acta}
                class="size-4 rounded border-[#D6D9E0]"
              />
              Genera acta
            </label>
            <label class="inline-flex items-center gap-2 text-[12.5px] text-[#1A1A24]">
              <input
                type="checkbox"
                bind:checked={comp.aprobacion_obligatoria}
                class="size-4 rounded border-[#D6D9E0]"
              />
              Aprobación obligatoria
            </label>
            <label class="inline-flex items-center gap-2 text-[12.5px] text-[#1A1A24]">
              Asistencia mínima
              <input
                type="number"
                min="0"
                max="100"
                bind:value={comp.asistencia_obligatoria}
                class="{INPUT} w-[86px] text-right tabular-nums"
              />
              <span class="text-[#9AA0AE]">%</span>
            </label>
          </div>
        </div>
      {/each}
      <div class="flex flex-wrap items-center gap-3.5">
        <button
          type="button"
          class={ADD_LINK}
          onclick={() =>
            (componentes = [
              ...componentes,
              {
                componente: '',
                porcentaje: 0,
                genera_acta: false,
                aprobacion_obligatoria: false,
                asistencia_obligatoria: 0,
              },
            ])}
        >
          <Plus size={14} aria-hidden="true" />
          Añadir componente
        </button>
        <span
          class="ml-auto inline-flex items-center gap-1.5 text-[12.5px] font-semibold {totalPonderacion ===
          100
            ? 'text-[#047857]'
            : 'text-[#B45309]'}"
        >
          Total: {totalPonderacion}%
        </span>
      </div>
      {#if !componentes.some((c) => c.componente.trim())}
        {@render aviso('Se necesita al menos un componente.')}
      {/if}
    </section>

    <div class="flex max-w-[280px] flex-col gap-1.5 border-t border-[#EDEFF3] pt-5">
      <label for="ponderacion" class={LABEL}>Ponderación de la prueba optativa</label>
      <div class="flex items-center gap-2">
        <input
          id="ponderacion"
          type="number"
          min="0"
          max="100"
          bind:value={ponderacion_optativa}
          class="{INPUT} text-right tabular-nums"
        />
        <span class="text-[13px] text-[#9AA0AE]">%</span>
      </div>
    </div>

    <div class="flex flex-col gap-1.5 border-t border-[#EDEFF3] pt-5">
      <label for="normativa" class="flex items-baseline gap-2">
        <span class="text-[12.5px] font-semibold text-[#1A1A24]">Normativa del curso</span>
        {@render marca(true)}
      </label>
      <textarea
        id="normativa"
        rows="6"
        bind:value={normativa_curso}
        placeholder="Asistencia, integridad académica, uso de asistentes de IA, horario de consultas, contacto…"
        class="{TEXTAREA} {!normativa_curso.trim()
          ? 'border-[1.5px] border-[#DC2626] bg-[#FEF7F7]'
          : ''}"
      ></textarea>
      {#if !normativa_curso.trim()}
        {@render aviso('Este campo es obligatorio.')}
      {/if}
      <span class="text-[11.5px] text-[#5A5E6E]">
        La lámina separa nota mínima, políticas y contacto en campos propios;
        `secciones.IX.contenido` guarda un único texto, así que van todos aquí.
      </span>
    </div>
  </div>
{/if}
