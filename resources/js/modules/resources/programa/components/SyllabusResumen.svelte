<script lang="ts">
  /**
   * Paso «Resumen» del asistente: así se verá el documento en el visor.
   *
   * Se dibuja desde el estado del formulario, no desde el servidor, porque su
   * propósito es revisar antes de guardar. Mantiene la misma medida (760 px) y
   * la misma tipografía que `ProgramaDocument`, para que la revisión y la
   * lectura sean la misma imagen.
   */
  import { Info } from 'lucide-svelte';
  import type { WizardUnidad, WizardActividad } from '@/modules/resources/programa/types/programa.types';
  import type { BibliografiaSyllabus } from '@/types/syllabus.types';
  import { formatDomainUrl } from '@/utils/formatters';

  interface Props {
    codigo: string;
    tipo: 'BASICO' | 'COMPLETO';
    version: number | null;
    nombreAsignatura: string;
    creditos_sct: string;
    horas_catedra: string;
    horas_taller: string;
    horas_laboratorio: string;
    categoria: string;
    docenteTitular: string | null;
    presentacion: string;
    unidades: WizardUnidad[];
    actividades: WizardActividad[];
    bibliografias?: BibliografiaSyllabus[];
    /**
     * @deprecated Sección VIII ahora utiliza `bibliografias`. Mantenido por retrocompatibilidad con esquemas anteriores.
     */
    recursos?: { descripcion: string; tipo: string; ubicacion: string }[];
  }

  let {
    codigo,
    tipo,
    version,
    nombreAsignatura,
    creditos_sct,
    horas_catedra,
    horas_taller,
    horas_laboratorio,
    categoria,
    docenteTitular,
    presentacion,
    unidades,
    actividades,
    bibliografias = [],
    recursos = [],
  }: Props = $props();

  const unidadesConTitulo = $derived(unidades.filter((u) => u.titulo.trim()));
  const actividadesConNombre = $derived(actividades.filter((a) => a.nombre.trim()));
  const bibliografiasConTitulo = $derived(bibliografias.filter((b) => b.titulo?.trim()));
  const recursosConTexto = $derived(recursos.filter((r) => r.descripcion.trim()));

  const horas = $derived(
    [
      Number(horas_catedra) ? `${horas_catedra} cátedra` : null,
      Number(horas_taller) ? `${horas_taller} taller` : null,
      Number(horas_laboratorio) ? `${horas_laboratorio} laboratorio` : null,
    ]
      .filter(Boolean)
      .join(' · ') || '—',
  );

  const NUM = 'w-[30px] shrink-0 font-mono text-[12.5px] text-[#9AA0AE]';
  const TIT = 'm-0 text-[18px] font-semibold text-[#1A1A24]';
</script>

<div class="flex w-full flex-col items-center gap-4">
  <p class="m-0 w-full max-w-[760px] text-[12.5px] text-[#5A5E6E]">
    Así se verá el documento en el visor. Revisa antes de guardar.
  </p>

  <article
    class="flex w-full max-w-[760px] flex-col gap-[26px] rounded-[10px] border border-[#E5E7EB] bg-white px-6 py-8 text-[15px] leading-[1.65] text-[#1A1A24] sm:px-11"
  >
    <header class="flex flex-col gap-1.5 border-b border-[#EDEFF3] pb-[18px]">
      <span class="font-mono text-[12.5px] text-[#5A5E6E]">
        {[codigo, tipo, version ? `versión ${version}` : 'versión nueva'].filter(Boolean).join(' · ')}
      </span>
      <h2 class="m-0 text-[22px] font-semibold tracking-[-0.01em]">
        {nombreAsignatura || 'Sin nombre'}
      </h2>
    </header>

    <section class="flex flex-col gap-3">
      <div class="flex items-baseline gap-2.5">
        <span class={NUM}>I.</span>
        <h3 class={TIT}>Identificación</h3>
      </div>
      <dl class="m-0 grid grid-cols-1 gap-x-7 gap-y-3 border-y border-[#EDEFF3] py-4 sm:grid-cols-2">
        <div class="flex flex-col">
          <dt class="text-[12px] text-[#5A5E6E]">Créditos SCT</dt>
          <dd class="m-0 text-[14.5px] font-medium">{creditos_sct || '—'}</dd>
        </div>
        <div class="flex flex-col">
          <dt class="text-[12px] text-[#5A5E6E]">Horas semanales</dt>
          <dd class="m-0 text-[14.5px] font-medium">{horas}</dd>
        </div>
        <div class="flex flex-col">
          <dt class="text-[12px] text-[#5A5E6E]">Categoría</dt>
          <dd class="m-0 text-[14.5px] font-medium">{categoria || '—'}</dd>
        </div>
        <div class="flex flex-col">
          <dt class="text-[12px] text-[#5A5E6E]">Docente titular</dt>
          <dd class="m-0 text-[14.5px] font-medium">{docenteTitular ?? '—'}</dd>
        </div>
      </dl>
    </section>

    <section class="flex flex-col gap-2.5">
      <div class="flex items-baseline gap-2.5">
        <span class={NUM}>II.</span>
        <h3 class={TIT}>Presentación</h3>
      </div>
      <p class="m-0 text-pretty whitespace-pre-wrap {presentacion.trim() ? '' : 'text-[#9AA0AE]'}">
        {presentacion.trim() || 'Sin contenido todavía.'}
      </p>
    </section>

    <section class="flex flex-col gap-3">
      <div class="flex items-baseline gap-2.5">
        <span class={NUM}>VI.</span>
        <h3 class={TIT}>Unidades</h3>
      </div>
      {#if unidadesConTitulo.length > 0}
        <div class="flex flex-col gap-3.5">
          {#each unidadesConTitulo as u}
            <div class="flex flex-col gap-1">
              <span class="font-mono text-[12px] text-[#5A5E6E]">Unidad {u.numero}</span>
              <span class="text-[15px] font-semibold">{u.titulo}</span>
              {#if u.contenidos.trim()}
                <p class="m-0 text-pretty whitespace-pre-wrap">{u.contenidos}</p>
              {/if}
            </div>
          {/each}
        </div>
      {:else}
        <p class="m-0 text-[#9AA0AE]">Sin contenido todavía.</p>
      {/if}
    </section>

    <section class="flex flex-col gap-2.5">
      <div class="flex items-baseline gap-2.5">
        <span class={NUM}>VII.</span>
        <h3 class={TIT}>Actividades de Aprendizaje</h3>
      </div>
      {#if actividadesConNombre.length > 0}
        <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5">
          {#each actividadesConNombre as a}
            <li>
              {a.nombre}{a.tipo ? ` (${a.tipo})` : ''}{a.nombre_unidad
                ? ` — Unidad: ${a.nombre_unidad}`
                : ''}
            </li>
          {/each}
        </ul>
      {:else}
        <p class="m-0 text-[#9AA0AE]">Sin contenido todavía.</p>
      {/if}
    </section>

    <section class="flex flex-col gap-2.5">
      <div class="flex items-baseline gap-2.5">
        <span class={NUM}>VIII.</span>
        <h3 class={TIT}>Bibliografía y Recursos</h3>
      </div>
      {#if bibliografiasConTitulo.length > 0}
        <ul class="m-0 flex list-disc flex-col gap-2 pl-5 text-[14px]">
          {#each bibliografiasConTitulo as b}
            <li>
              <span class="font-semibold text-[#1A1A24]">{b.titulo}</span>
              {#if b.autor} · <span class="text-[#5A5E6E]">{b.autor}</span>{/if}
              <span class="font-mono text-[12px] text-[#5A5E6E]">({b.anio})</span>
              {#if b.editorial} <span class="italic text-[#9AA0AE]">· {b.editorial}</span>{/if}
              {#if b.es_bibliografia_uta}
                <span class="ml-1 rounded-full bg-[#E8EDF5] px-2 py-0.5 text-[10.5px] font-bold text-[#002F6C]">Oficial UTA</span>
              {/if}
              {#if b.url}
                · <a href={b.url} target="_blank" rel="noopener noreferrer" class="text-[#002F6C] underline hover:text-[#1B4789]">{formatDomainUrl(b.url)}</a>
              {/if}
              {#if b.uuid_archivo}
                · <a href={`/api/bibliografias/${b.uuid_archivo}/archivo`} target="_blank" rel="noopener noreferrer" class="font-medium text-[#002F6C] underline hover:text-[#1B4789]">Ver archivo</a>
              {/if}
              {#if b.cita}
                <span class="block text-[12.5px] italic text-[#5A5E6E]">«{b.cita}»</span>
              {/if}
            </li>
          {/each}
        </ul>
      {:else if recursosConTexto.length > 0}
        <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[14px]">
          {#each recursosConTexto as r}
            <li>{r.descripcion}{r.tipo ? ` (${r.tipo})` : ''}</li>
          {/each}
        </ul>
      {:else}
        <p class="m-0 text-[#9AA0AE]">Sin contenido todavía.</p>
      {/if}
    </section>
  </article>

  <p
    class="m-0 flex w-full max-w-[760px] items-start gap-2.5 rounded-[9px] border border-[#E3D9C6] bg-[#F5F1EA] px-3.5 py-3 text-[12.5px] leading-[1.5] text-[#6B5B3E]"
  >
    <Info size={15} class="mt-0.5 shrink-0" aria-hidden="true" />
    Al guardar, el syllabus básico queda <strong class="font-semibold">entregado</strong> y visible
    para los alumnos —la versión básica no pasa por aprobación—. Completarlo hasta las nueve
    secciones se hace desde el visor del programa.
  </p>
</div>
