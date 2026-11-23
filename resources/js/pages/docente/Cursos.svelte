<script lang="ts">
  /**
   * Mis cursos del docente — /docente/cursos (dirección institucional UTA).
   *
   * Todos los cursos donde es titular o imparte un componente, como en Mis
   * cursos del alumno: arriba los del semestre vigente (el más reciente con
   * cursos, el mismo del menú), en grilla o en lista; abajo «Semestres
   * anteriores», siempre a la vista, en una lista compacta con una franja por
   * año y semestre, del más reciente al más antiguo. Buscar por código, nombre
   * o carrera y ordenar vale para las dos partes.
   *
   * Sólo se muestra lo que entrega el controlador: estudiantes inscritos y si
   * el curso ya tiene programa. «Por revisar» y «sin leer» por curso no tienen
   * todavía consulta; antes se dibujaban siempre en 0.
   */
  import { Link } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import { ChevronRight, LayoutGrid, List, Search } from 'lucide-svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import { btnSecundario, campo, chico, chip, codigo, cuerpo, etiqueta, seccionTitulo, tarjeta, titulo, tono } from '@/lib/inst';
  import { nombreSemestre } from '@/lib/cursoDocente';
  import type { BreadcrumbItem } from '@/types';

  interface CursoDocente {
    id_curso: number;
    nombre: string;
    cod_curso: string;
    cod_asignatura?: string | null;
    letra_grupo?: string | null;
    carrera_nombre?: string | null;
    fecha_fin?: string | null;
    tiene_programa: boolean;
    semestre_real: number;
    agno_real?: number | null;
    total_estudiantes: number;
    es_titular_curso: boolean;
  }

  interface Props {
    cursosSemestre1: CursoDocente[];
    cursosSemestre2: CursoDocente[];
  }

  let { cursosSemestre1 = [], cursosSemestre2 = [] }: Props = $props();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/docente/dashboard' },
    { title: 'Mis cursos', href: '/docente/cursos' },
  ];

  type Vista = 'grilla' | 'lista';
  type Orden = 'codigo' | 'nombre' | 'estudiantes';

  const CLAVE_VISTA = 'docente-cursos-view';
  let vista = $state<Vista>('grilla');
  let busqueda = $state('');
  let orden = $state<Orden>('codigo');

  onMount(() => {
    try {
      const guardada = localStorage.getItem(CLAVE_VISTA);
      // Antes se guardaba 'grid' / 'list'.
      if (guardada === 'list' || guardada === 'lista') vista = 'lista';
    } catch {
      // Sin almacenamiento: queda la grilla.
    }
  });

  function elegirVista(v: Vista) {
    vista = v;
    try {
      localStorage.setItem(CLAVE_VISTA, v);
    } catch {
      // No se pudo recordar; no afecta a la vista actual.
    }
  }

  /** El backend manda 'N/A' sin asignatura: ahí se cae al código interno. */
  function codigoAsignatura(c: CursoDocente): string {
    return c.cod_asignatura && c.cod_asignatura !== 'N/A' ? c.cod_asignatura : c.cod_curso;
  }

  function codigoConGrupo(c: CursoDocente): string {
    return c.letra_grupo ? `${codigoAsignatura(c)} · Grupo ${c.letra_grupo}` : codigoAsignatura(c);
  }

  function carrera(c: CursoDocente): string {
    return c.carrera_nombre && c.carrera_nombre !== 'N/A' ? c.carrera_nombre : 'Sin carrera';
  }

  const hoy = new Date().toISOString().slice(0, 10);

  /**
   * La búsqueda acepta el nombre, la carrera o el código, con o sin grupo:
   * «DM301», «dm301 a» y «DM301 · Grupo A» encuentran el mismo curso. Para
   * los códigos se comparan sólo letras y números.
   */
  const compacto = (t: string) => t.toLowerCase().normalize('NFD').replace(/[^a-z0-9]/g, '');

  function coincide(c: CursoDocente, q: string): boolean {
    const texto = [c.nombre, carrera(c)].join(' ').toLowerCase();
    if (texto.includes(q)) return true;
    const buscado = compacto(q);
    if (!buscado) return false;
    const cod = compacto(codigoAsignatura(c));
    const letra = compacto(c.letra_grupo ?? '');
    return [cod, cod + letra, cod + 'grupo' + letra, compacto(c.cod_curso)].some((k) => k.includes(buscado));
  }

  const todos = $derived([...cursosSemestre1, ...cursosSemestre2]);
  const total = $derived(todos.length);

  const filtrados = $derived.by(() => {
    const q = busqueda.trim().toLowerCase();
    return todos.filter((c) => !q || coincide(c, q)).sort((a, b) => {
      if (orden === 'nombre') return a.nombre.localeCompare(b.nombre, 'es');
      if (orden === 'estudiantes') return b.total_estudiantes - a.total_estudiantes;
      return codigoConGrupo(a).localeCompare(codigoConGrupo(b), 'es');
    });
  });

  const claveDe = (c: CursoDocente) => (c.agno_real ? `${c.agno_real}-${c.semestre_real}` : 'sin-periodo');
  const pesoDe = (c: CursoDocente) => (c.agno_real ? c.agno_real * 10 + c.semestre_real : 0);

  /**
   * El semestre vigente, como en el Inicio y el menú: el más reciente con
   * cursos. Sale de todos los cursos, no de los filtrados, para que buscar no
   * cambie qué semestre es el actual. «En curso» salvo que todos sus cursos
   * ya hayan terminado.
   */
  const vigente = $derived.by(() => {
    const conPeriodo = todos.filter((c) => c.agno_real);
    if (conPeriodo.length === 0) return null;
    const ultimo = conPeriodo.reduce((a, c) => (pesoDe(c) > pesoDe(a) ? c : a));
    const clave = claveDe(ultimo);
    const cursos = todos.filter((c) => claveDe(c) === clave);
    const terminado = cursos.every((c) => c.fecha_fin && c.fecha_fin.slice(0, 10) < hoy);
    return {
      clave,
      nombre: nombreSemestre(ultimo.semestre_real, ultimo.agno_real!),
      estado: terminado ? 'Cerrado' : 'En curso',
      total: cursos.length,
    };
  });

  // Sin ningún curso con período (caso raro) todos van arriba.
  const esVigente = (c: CursoDocente) => !vigente || claveDe(c) === vigente.clave;

  const actuales = $derived(filtrados.filter(esVigente));

  /** Semestres anteriores, del más reciente al más antiguo; un curso sin año va al final. */
  const anteriores = $derived.by(() => {
    const grupos = new Map<string, { clave: string; nombre: string; peso: number; cursos: CursoDocente[] }>();
    for (const c of filtrados) {
      if (esVigente(c)) continue;
      const clave = claveDe(c);
      if (!grupos.has(clave)) {
        const nombre = c.agno_real ? nombreSemestre(c.semestre_real, c.agno_real) : 'Sin período';
        grupos.set(clave, { clave, nombre, peso: pesoDe(c), cursos: [] });
      }
      grupos.get(clave)!.cursos.push(c);
    }
    return [...grupos.values()].sort((a, b) => b.peso - a.peso);
  });

  const totalAnteriores = $derived(todos.filter((c) => !esVigente(c)).length);
  const nAnteriores = $derived(anteriores.reduce((n, g) => n + g.cursos.length, 0));

  const resumen = $derived.by(() => {
    if (!vigente || busqueda.trim()) return `${filtrados.length} de ${total} cursos`;
    const base = `${vigente.total} ${vigente.total === 1 ? 'curso' : 'cursos'} en el ${vigente.nombre.toLowerCase()}`;
    return totalAnteriores > 0 ? `${base} · ${totalAnteriores} en semestres anteriores` : base;
  });

  const cantidad = (n: number) => `${n} ${n === 1 ? 'curso' : 'cursos'}`;
  const estudiantes = (n: number) => `${n} ${n === 1 ? 'estudiante' : 'estudiantes'}`;

  const botonSeg =
    'inline-flex min-h-11 cursor-pointer items-center gap-1.5 border-0 border-r border-inst-linea px-3.5 text-[13px] font-bold last:border-r-0 lg:min-h-9';
</script>

<DocenteLayout {breadcrumbs} titulo="Mis cursos">
  {#snippet acciones()}
    <span class="text-[13px] text-inst-ink-2">{resumen}</span>
  {/snippet}

  <div class={cuerpo}>
    <div class="{tarjeta} flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3">
      <label class="relative flex min-w-0 flex-[1_1_320px] items-center">
        <Search size={16} aria-hidden="true" class="pointer-events-none absolute left-3 text-inst-ink-3" />
        <span class="sr-only">Buscar curso</span>
        <input type="search" bind:value={busqueda} placeholder="Buscar por código (DM301) o por nombre" class="{campo} pl-9.5" />
      </label>
      <label class="flex items-center gap-2">
        <span class="text-[13px] font-bold whitespace-nowrap">Ordenar por</span>
        <select bind:value={orden} class="{campo} w-auto">
          <option value="codigo">Código</option>
          <option value="nombre">Nombre</option>
          <option value="estudiantes">Más estudiantes</option>
        </select>
      </label>
      <div role="group" aria-label="Vista" class="inline-flex overflow-hidden rounded-md border border-inst-control">
        <button
          type="button"
          aria-pressed={vista === 'grilla'}
          onclick={() => elegirVista('grilla')}
          class="{botonSeg} {vista === 'grilla' ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta text-inst-ink'}"
        >
          <LayoutGrid size={15} aria-hidden="true" />Grilla
        </button>
        <button
          type="button"
          aria-pressed={vista === 'lista'}
          onclick={() => elegirVista('lista')}
          class="{botonSeg} {vista === 'lista' ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta text-inst-ink'}"
        >
          <List size={15} aria-hidden="true" />Lista
        </button>
      </div>
    </div>

    {#if total === 0 || filtrados.length === 0}
      <div class="{tarjeta} px-6 py-10 text-center">
        <p class="m-0 font-bold">{busqueda.trim() ? 'Sin resultados' : 'Sin cursos asignados'}</p>
        <p class="m-0 mt-1 text-inst-ink-2">
          {busqueda.trim() ? `No hay cursos que coincidan con «${busqueda.trim()}».` : 'Todavía no tienes cursos asignados.'}
        </p>
      </div>
    {:else}
      <section aria-labelledby="t-actual" class="flex flex-col gap-3.5">
        <div class={seccionTitulo}>
          <h2 id="t-actual" class="{titulo} m-0 text-[19px] leading-6">Este semestre</h2>
          {#if vigente}
            <span class="text-[13px] font-bold text-inst-ink-2">{vigente.nombre}</span>
            <span class="{chip} {vigente.estado === 'Cerrado' ? tono.off : tono.ok}">{vigente.estado}</span>
          {/if}
          <span class="ml-auto text-[13px] text-inst-ink-2">{cantidad(actuales.length)}</span>
        </div>

        {#if actuales.length === 0}
          <p class="m-0 text-inst-ink-2">Ningún curso de este semestre coincide con «{busqueda.trim()}».</p>
        {:else if vista === 'grilla'}
          <div class="grid grid-cols-[repeat(auto-fill,minmax(min(280px,100%),1fr))] gap-4">
            {#each actuales as curso (curso.id_curso)}
              <Link
                href="/docente/cursos/{curso.id_curso}"
                class="{tarjeta} flex flex-col gap-3.5 px-5 pt-4.5 text-inst-ink no-underline transition-shadow hover:text-inst-ink hover:shadow-md {vigente?.estado ===
                'Cerrado'
                  ? ''
                  : curso.es_titular_curso
                    ? 'border-t-4 border-t-inst-celeste'
                    : 'border-t-4 border-t-inst-oro'}"
              >
                <span class="flex flex-wrap items-center justify-between gap-2">
                  <span class={codigo}>{codigoConGrupo(curso)}</span>
                  <span class="{chip} {curso.es_titular_curso ? tono.accion : tono.oro}">
                    {curso.es_titular_curso ? 'Titular' : 'Componente'}
                  </span>
                </span>
                <span class="flex flex-col gap-1">
                  <span class="{titulo} text-lg leading-6">{curso.nombre}</span>
                  <span class="text-[13px] text-inst-ink-2">{carrera(curso)}</span>
                </span>
                <span class="-mx-5 mt-auto grid grid-cols-2 border-t border-inst-linea">
                  <span class="flex flex-col border-r border-inst-linea px-5 py-2.5">
                    <span class="{titulo} text-xl leading-6">{curso.total_estudiantes}</span>
                    <span class={etiqueta}>Estudiantes</span>
                  </span>
                  <span class="flex flex-col px-5 py-2.5">
                    <span class="{titulo} text-xl leading-6 {curso.tiene_programa ? '' : 'text-inst-warn'}">
                      {curso.tiene_programa ? 'Sí' : 'No'}
                    </span>
                    <span class={etiqueta}>Programa</span>
                  </span>
                </span>
              </Link>
            {/each}
          </div>
        {:else}
          <div class="{tarjeta} overflow-hidden">
            <!-- Anchos fijos: las tablas de cada período quedan alineadas. -->
            <table class="tabla-inst md:table-fixed">
              <colgroup>
                <col class="w-[34%]" />
                <col class="w-[22%]" />
                <col class="w-[13%]" />
                <col class="w-[12%]" />
                <col class="w-[11%]" />
                <col />
              </colgroup>
              <thead>
                <tr>
                  <th scope="col">Curso</th>
                  <th scope="col">Carrera</th>
                  <th scope="col">Rol</th>
                  <th scope="col" class="text-right">Estudiantes</th>
                  <th scope="col">Programa</th>
                  <th scope="col"><span class="sr-only">Acción</span></th>
                </tr>
              </thead>
              <tbody>
                {#each actuales as curso (curso.id_curso)}
                  <tr>
                    <td data-l="Curso">
                      <span class="flex min-w-0 flex-col">
                        <span class={codigo}>{codigoConGrupo(curso)}</span>
                        <span class="font-bold">{curso.nombre}</span>
                      </span>
                    </td>
                    <td data-l="Carrera" class="text-inst-ink-2">{carrera(curso)}</td>
                    <td data-l="Rol">
                      <span class="{chip} {curso.es_titular_curso ? tono.accion : tono.oro}">
                        {curso.es_titular_curso ? 'Titular' : 'Componente'}
                      </span>
                    </td>
                    <td data-l="Estudiantes" class="text-right">{curso.total_estudiantes}</td>
                    <td data-l="Programa">
                      <span class="{chip} {curso.tiene_programa ? tono.ok : tono.warn}">
                        {curso.tiene_programa ? 'Creado' : 'Sin crear'}
                      </span>
                    </td>
                    <td class="text-right">
                      <Link href="/docente/cursos/{curso.id_curso}" class="{btnSecundario} {chico}" aria-label="Abrir {curso.nombre} {codigoConGrupo(curso)}">
                        Abrir
                        <ChevronRight size={15} aria-hidden="true" />
                      </Link>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}
      </section>

      <!-- Siempre a la vista, como en Mis cursos del alumno: aunque esté vacía, deja claro dónde quedarán. -->
      <section aria-labelledby="t-anteriores" class="flex flex-col gap-3.5">
        <div class={seccionTitulo}>
          <h2 id="t-anteriores" class="{titulo} m-0 text-[19px] leading-6">Semestres anteriores</h2>
          {#if nAnteriores > 0}<span class="ml-auto text-[13px] text-inst-ink-2">{cantidad(nAnteriores)}</span>{/if}
        </div>

        {#if totalAnteriores === 0}
          <p class="m-0 text-inst-ink-2">Aquí quedarán tus cursos de semestres anteriores, cuando termine el semestre.</p>
        {:else if anteriores.length === 0}
          <p class="m-0 text-inst-ink-2">Ningún curso de semestres anteriores coincide con «{busqueda.trim()}».</p>
        {:else}
          <div class="{tarjeta} overflow-hidden">
            {#each anteriores as grupo (grupo.clave)}
              <div class="border-b border-inst-linea last:border-b-0">
                <h3 class="{etiqueta} m-0 flex items-center justify-between gap-2 border-b border-inst-linea bg-inst-sutil px-4 py-2.5 lg:px-5">
                  {grupo.nombre}<span class="font-bold tracking-normal normal-case">{cantidad(grupo.cursos.length)}</span>
                </h3>
                <ul class="m-0 list-none p-0">
                  {#each grupo.cursos as curso (curso.id_curso)}
                    <li class="border-b border-inst-linea last:border-b-0">
                      <Link
                        href="/docente/cursos/{curso.id_curso}"
                        class="flex min-h-14 items-center gap-3 px-4 py-2.5 text-inst-ink no-underline hover:bg-inst-sutil hover:text-inst-ink lg:gap-4 lg:px-5"
                      >
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                          <span class={codigo}>{codigoConGrupo(curso)}</span>
                          <span class="font-bold">{curso.nombre}</span>
                          <span class="text-[13px] text-inst-ink-2 md:hidden">{carrera(curso)} · {estudiantes(curso.total_estudiantes)}</span>
                        </span>
                        <span class="hidden w-60 shrink-0 text-[13px] text-inst-ink-2 md:block">
                          {carrera(curso)}<br />{estudiantes(curso.total_estudiantes)}
                        </span>
                        <span class="flex w-24 shrink-0 justify-end">
                          <span class="{chip} {curso.es_titular_curso ? tono.accion : tono.oro}">
                            {curso.es_titular_curso ? 'Titular' : 'Componente'}
                          </span>
                        </span>
                        <ChevronRight size={16} aria-hidden="true" class="shrink-0 text-inst-ink-3 max-sm:hidden" />
                      </Link>
                    </li>
                  {/each}
                </ul>
              </div>
            {/each}
          </div>
        {/if}
      </section>
    {/if}
  </div>
</DocenteLayout>
