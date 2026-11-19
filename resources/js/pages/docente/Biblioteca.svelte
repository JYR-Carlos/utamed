<script lang="ts">
  /**
   * Pestaña «Biblioteca» del curso — /docente/cursos/{curso}/biblioteca.
   *
   * Aparte del syllabus (revisión «Mi UTAmed»): los recursos de la sección
   * VIII del programa agrupados por unidad, con buscador y filtros, y la
   * bibliografía obligatoria y complementaria al lado como referencia.
   *
   * El lienzo propone además que el docente agregue, edite u oculte recursos
   * propios por unidad; eso necesita una tabla de material del curso que la
   * base no tiene, así que aquí sólo se lee lo que el programa registra.
   */
  import { Link } from '@inertiajs/svelte';
  import { BookOpen, ExternalLink, FileText, Link2, Search } from 'lucide-svelte';
  import CursoDocenteLayout from '@/layouts/CursoDocenteLayout.svelte';
  import { btnSecundario, campo, chico, chip, cuerpo, tarjeta, tarjetaCabecera, titulo, tono } from '@/lib/inst';
  import { gruposDeRecursos, type GrupoRecursos, type TipoRecurso } from '@/lib/programaAlumno';

  interface Props {
    curso: { id_curso: number; nombre: string; etiqueta?: string };
    hayPrograma: boolean;
    secciones: Array<{ numeral_romano?: string; nombre_seccion: string; contenidos?: Array<{ texto_contenido: string | null }> }>;
  }

  let { curso, hayPrograma, secciones }: Props = $props();

  const grupos = $derived(gruposDeRecursos(secciones as any));
  /** La bibliografía va al costado; las unidades, al centro. */
  const esBibliografia = (g: GrupoRecursos) => /bibliograf/i.test(g.titulo);
  const unidades = $derived(grupos.filter((g) => !esBibliografia(g)));
  const bibliografia = $derived(grupos.filter(esBibliografia));

  let busqueda = $state('');
  let filtroUnidad = $state('todas');
  let filtroTipo = $state<'todos' | TipoRecurso>('todos');

  const NOMBRE_TIPO: Record<TipoRecurso, string> = { libro: 'Libro o texto', archivo: 'Archivo', enlace: 'Enlace' };
  const ICONO = { libro: BookOpen, archivo: FileText, enlace: Link2 } as const;
  const COLOR: Record<TipoRecurso, string> = {
    libro: 'bg-inst-sel text-inst-ink',
    archivo: 'bg-inst-accion-s text-inst-accion-t',
    enlace: 'bg-inst-info-s text-inst-info',
  };

  const unidadesVisibles = $derived(
    unidades
      .filter((g) => filtroUnidad === 'todas' || g.id === filtroUnidad)
      .map((g) => ({
        ...g,
        recursos: g.recursos.filter(
          (r) =>
            (filtroTipo === 'todos' || r.tipo === filtroTipo) &&
            (!busqueda.trim() || r.texto.toLowerCase().includes(busqueda.trim().toLowerCase())),
        ),
      })),
  );
</script>

<svelte:head>
  <title>Biblioteca · {curso.etiqueta ?? curso.nombre} | UTAmed</title>
</svelte:head>

<CursoDocenteLayout idCurso={curso.id_curso} seccion="biblioteca" nombre={curso.etiqueta ?? curso.nombre}>
  <div class={cuerpo}>
    <h2 class="{titulo} m-0 text-2xl leading-[30px]">Biblioteca</h2>

    {#if grupos.length === 0}
      <section class="{tarjeta} px-6 py-8 text-center">
        <p class="m-0 font-bold">{hayPrograma ? 'El programa no registra recursos' : 'Todavía no hay programa'}</p>
        <p class="m-0 mt-1 text-inst-ink-2">
          {hayPrograma
            ? 'Los recursos y la bibliografía se escriben en la sección VIII del programa.'
            : 'La biblioteca muestra los recursos de la sección VIII del programa del curso.'}
        </p>
      </section>
    {:else}
      <div class="{tarjeta} flex flex-wrap items-end gap-3 px-4.5 py-3.5">
        <label class="relative min-w-[220px] flex-[1_1_260px]">
          <span class="sr-only">Buscar recurso</span>
          <Search size={16} aria-hidden="true" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-inst-ink-3" />
          <input type="search" bind:value={busqueda} placeholder="Buscar recurso" class="{campo} pl-9" />
        </label>
        {#if unidades.length > 1}
          <label class="flex flex-[0_1_200px] flex-col gap-1 text-[13px] font-bold">
            Unidad
            <select bind:value={filtroUnidad} class={campo}>
              <option value="todas">Todas</option>
              {#each unidades as g (g.id)}<option value={g.id}>{g.corto}</option>{/each}
            </select>
          </label>
        {/if}
        <label class="flex flex-[0_1_180px] flex-col gap-1 text-[13px] font-bold">
          Tipo
          <select bind:value={filtroTipo} class={campo}>
            <option value="todos">Todos</option>
            <option value="archivo">Archivo</option>
            <option value="enlace">Enlace</option>
            <option value="libro">Libro o texto</option>
          </select>
        </label>
      </div>

      <div class="flex flex-wrap items-start gap-5">
        <div class="flex min-w-0 flex-[999_1_600px] flex-col gap-4">
          {#each unidadesVisibles as g (g.id)}
            <section aria-labelledby="t-{g.id}" class="{tarjeta} overflow-hidden">
              <div class={tarjetaCabecera}>
                <h3 id="t-{g.id}" class="{titulo} m-0 text-[17px] leading-[22px]">{g.titulo}</h3>
                <span class="{chip} {tono.off}">{g.recursos.length}</span>
              </div>
              {#if g.recursos.length === 0}
                <p class="m-0 px-4.5 py-4 text-inst-ink-2">Ningún recurso de esta unidad coincide con el filtro.</p>
              {:else}
                <ul class="m-0 list-none p-0">
                  {#each g.recursos as r, i (i)}
                    {@const Icono = ICONO[r.tipo]}
                    <li class="flex items-center gap-3 border-b border-inst-linea px-4.5 py-3 last:border-b-0">
                      <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-md {COLOR[r.tipo]}">
                        <Icono size={20} />
                      </span>
                      <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="font-bold text-pretty">{r.texto}</span>
                        <span class="text-[13px] text-inst-ink-2">
                          {NOMBRE_TIPO[r.tipo]}{r.direccion ? ` · ${r.direccion}` : ''}
                        </span>
                      </span>
                      {#if r.href}
                        <a href={r.href} target="_blank" rel="noopener noreferrer" class="{btnSecundario} {chico}">
                          Abrir
                          <ExternalLink size={14} aria-hidden="true" />
                        </a>
                      {/if}
                    </li>
                  {/each}
                </ul>
              {/if}
            </section>
          {:else}
            <p class="m-0 text-inst-ink-2">El programa no separa los recursos por unidad.</p>
          {/each}
        </div>

        <aside aria-labelledby="t-biblio-prog" class="{tarjeta} flex-[1_1_320px]">
          <div class={tarjetaCabecera}>
            <h3 id="t-biblio-prog" class="{titulo} m-0 text-[17px] leading-[22px]">Bibliografía del programa</h3>
          </div>
          <div class="flex flex-col gap-3.5 px-4.5 py-4">
            <span class="text-[13px] text-inst-ink-2">
              Sección VIII del programa. Se muestra aquí como referencia y se edita en el programa.
            </span>
            {#each bibliografia as g (g.id)}
              <div class="flex flex-col gap-2">
                <span class="text-xs font-extrabold tracking-[0.06em] text-inst-ink-3 uppercase">{g.titulo.replace(/^Bibliograf[íi]a\s*/i, '') || g.titulo}</span>
                <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[13px] marker:text-inst-enlace">
                  {#each g.recursos as r, i (i)}
                    <li class="text-pretty">
                      {#if r.href}<a href={r.href} target="_blank" rel="noopener noreferrer" class="text-inst-enlace">{r.texto}</a>{:else}{r.texto}{/if}
                    </li>
                  {/each}
                </ul>
              </div>
            {:else}
              <p class="m-0 text-[13px] text-inst-ink-2">El programa no registra bibliografía.</p>
            {/each}
            <Link href="/docente/cursos/{curso.id_curso}/programa#seccion-VIII" class="{btnSecundario} {chico} self-start">
              <BookOpen size={16} aria-hidden="true" />
              Ver en el programa
            </Link>
          </div>
        </aside>
      </div>
    {/if}
  </div>
</CursoDocenteLayout>
