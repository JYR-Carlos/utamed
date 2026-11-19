<script lang="ts">
  /**
   * Gestión de grupos — /docente/cursos/{curso}/gestion/grupos (revisión
   * «Mi UTAmed»).
   *
   * Paso 1: para qué son los grupos. Paso 2: la lista de estudiantes para
   * seleccionar y crear un grupo, y los grupos para renombrarlos, quitarles o
   * agregarles integrantes, o eliminarlos.
   *
   * La base guarda los grupos por actividad (agenda.actividad_asignada_grupo),
   * no por curso: «Por curso» se muestra sin poder elegirse, y para repetir los
   * grupos en otra actividad está «Reutilizar grupos».
   */
  import { Link, page, router } from '@inertiajs/svelte';
  import { Check, ChevronLeft, Copy, Pencil, Plus, Search, Trash2, UserPlus, X } from 'lucide-svelte';
  import CursoDocenteLayout from '@/layouts/CursoDocenteLayout.svelte';
  import ModalInst from '@/components/docente/ModalInst.svelte';
  import ReutilizarGruposModal from './Activities/components/ReutilizarGruposModal.svelte';
  import {
    aviso,
    avisoTono,
    btnFantasma,
    btnPrimario,
    btnSecundario,
    campo,
    chico,
    chip,
    cuerpo,
    tarjeta,
    tarjetaCabecera,
    tarjetaPie,
    titulo,
    tono,
  } from '@/lib/inst';
  import AvatarAlumno from '@/components/docente/AvatarAlumno.svelte';

  type Estudiante = { id_estudiante: number; nombre_completo: string; url_foto?: string | null };
  type Grupo = { grupo: number; nombre_grupo: string | null; etiqueta: string; integrantes: Estudiante[] };
  type ActividadGrupal = {
    id_actividad: number;
    nombre: string;
    fecha_limite: string | null;
    max_integrantes: number | null;
    cantidad_grupos: number;
  };

  interface Props {
    curso: { id_curso: number; nombre: string; etiqueta?: string };
    actividades: ActividadGrupal[];
    actividad: number | null;
    grupos: Grupo[];
    estudiantes: Estudiante[];
  }

  let { curso, actividades, actividad, grupos, estudiantes }: Props = $props();

  const elegida = $derived(actividades.find((a) => a.id_actividad === actividad) ?? null);
  const base = $derived(`/docente/cursos/${curso.id_curso}/actividades/${actividad}`);
  const opciones = { preserveScroll: true, preserveState: true, replace: true } as const;

  function elegirActividad(id: number) {
    seleccion = new Set();
    router.get(`/docente/cursos/${curso.id_curso}/gestion/grupos`, { actividad: id }, { preserveScroll: true, replace: true });
  }

  // ── Quién está en qué grupo ───────────────────────────────────────────────
  const grupoDe = $derived(new Map(grupos.flatMap((g) => g.integrantes.map((i) => [i.id_estudiante, g] as const))));
  const sinGrupo = $derived(estudiantes.filter((e) => !grupoDe.has(e.id_estudiante)));

  let mostrar = $state<'sin' | 'todos'>('sin');
  let busqueda = $state('');
  let seleccion = $state<Set<number>>(new Set());

  const lista = $derived(
    (mostrar === 'sin' ? sinGrupo : estudiantes).filter(
      (e) => !busqueda.trim() || e.nombre_completo.toLowerCase().includes(busqueda.trim().toLowerCase()),
    ),
  );

  const maximo = $derived(elegida?.max_integrantes ?? null);
  const excede = $derived(maximo !== null && seleccion.size > maximo);

  function alternar(id: number) {
    const s = new Set(seleccion);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    seleccion = s;
  }

  // ── Acciones (endpoints de DocenteActivityController) ─────────────────────
  let ocupado = $state(false);
  let error = $state<string | null>(null);

  const alTerminar = {
    onError: (e: Record<string, string>) => (error = Object.values(e)[0] ?? 'No se pudo guardar.'),
    onFinish: () => (ocupado = false),
  };

  function crearGrupo() {
    if (seleccion.size === 0 || excede) return;
    ocupado = true;
    error = null;
    router.post(`${base}/grupos-create`, { estudiantes: [...seleccion] }, {
      ...opciones,
      ...alTerminar,
      onSuccess: () => (seleccion = new Set()),
    });
  }

  function quitar(g: Grupo, e: Estudiante) {
    ocupado = true;
    error = null;
    router.delete(`${base}/grupos/${g.grupo}/estudiantes/${e.id_estudiante}`, { ...opciones, ...alTerminar });
  }

  let agregandoA = $state<number | null>(null);
  let agregarId = $state<number>(0);

  function agregar(g: Grupo) {
    if (!agregarId) return;
    ocupado = true;
    error = null;
    router.post(`${base}/grupos/${g.grupo}/estudiante`, { id_estudiante: agregarId }, {
      ...opciones,
      ...alTerminar,
      onSuccess: () => {
        agregandoA = null;
        agregarId = 0;
      },
    });
  }

  let renombrando = $state<number | null>(null);
  let nombreNuevo = $state('');

  function renombrar(g: Grupo) {
    ocupado = true;
    error = null;
    router.patch(`${base}/grupos/${g.grupo}`, { nombre_grupo: nombreNuevo.trim() || null }, {
      ...opciones,
      ...alTerminar,
      onSuccess: () => (renombrando = null),
    });
  }

  let eliminando = $state<Grupo | null>(null);

  function eliminar() {
    if (!eliminando) return;
    const g = eliminando;
    ocupado = true;
    error = null;
    router.delete(`${base}/grupos-delete/${g.grupo}`, {
      ...opciones,
      ...alTerminar,
      onSuccess: () => (eliminando = null),
    });
  }

  // ── Reutilizar los grupos de otra actividad ───────────────────────────────
  const origenes = $derived(actividades.filter((a) => a.id_actividad !== actividad && a.cantidad_grupos > 0));
  let reutilizar = $state(false);
  let origen = $state<number | null>(null);
  let errorReutilizar = $state<string | null>(null);
  let copiando = $state(false);

  function copiar(ids: number[]) {
    if (!origen) return;
    copiando = true;
    errorReutilizar = null;
    router.post(`${base}/grupos-copy`, { id_actividad_origen: origen, grupos: ids }, {
      ...opciones,
      onSuccess: () => (reutilizar = false),
      onError: (e) => (errorReutilizar = Object.values(e)[0] ?? 'No se pudieron copiar los grupos.'),
      onFinish: () => (copiando = false),
    });
  }

  const flash = $derived((($page.props as any).flash ?? {}) as { success?: string; error?: string });
  const BTN_SEG = 'min-h-11 border-0 border-r border-inst-linea px-3.5 text-[13px] font-bold last:border-r-0 lg:min-h-9';
</script>

<svelte:head>
  <title>Gestión de grupos · {curso.etiqueta ?? curso.nombre} | UTAmed</title>
</svelte:head>

<CursoDocenteLayout idCurso={curso.id_curso} seccion="gestion" subpagina="Gestión de grupos" nombre={curso.etiqueta ?? curso.nombre}>
  <div class={cuerpo}>
    <div class="-mb-2 flex flex-col items-start gap-1">
      <Link href="/docente/cursos/{curso.id_curso}/gestion" class="{btnFantasma} {chico} pl-1">
        <ChevronLeft size={16} aria-hidden="true" />Gestión
      </Link>
      <h2 class="{titulo} m-0 text-2xl leading-[30px]">Gestión de grupos</h2>
    </div>

    {#if flash.error || error}
      <div class="{aviso} {avisoTono.error}" role="alert"><p class="m-0 text-[13px] font-bold">{error ?? flash.error}</p></div>
    {:else if flash.success}
      <div class="{aviso} {avisoTono.ok}" role="status"><p class="m-0 text-[13px] font-bold">{flash.success}</p></div>
    {/if}

    {#if actividades.length === 0}
      <section class="{tarjeta} px-6 py-10 text-center">
        <p class="m-0 font-bold">El curso no tiene actividades grupales</p>
        <p class="m-0 mt-1 text-inst-ink-2">Los grupos se arman para una actividad grupal. Créala en Actividades marcando «Es una actividad grupal».</p>
        <Link href="/docente/cursos/{curso.id_curso}/actividades" class="{btnSecundario} {chico} mt-4">Ir a Actividades</Link>
      </section>
    {:else}
      <section class={tarjeta} aria-labelledby="t-paso1">
        <div class={tarjetaCabecera}>
          <span class="{chip} {tono.solido}">1</span>
          <h3 id="t-paso1" class="{titulo} m-0 text-[17px] leading-[22px]">¿Para qué son estos grupos?</h3>
        </div>
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(320px,100%),1fr))] gap-3 p-4.5">
          <label class="flex cursor-pointer gap-3 rounded-lg border-2 border-inst-accion bg-inst-accion-s px-4 py-3.5">
            <input type="radio" name="alcance" checked class="mt-1 size-[18px] accent-[var(--color-inst-accion)]" />
            <span class="flex min-w-0 flex-1 flex-col gap-2">
              <span class="flex flex-col gap-0.5">
                <span class="font-bold">Por actividad</span>
                <span class="text-[13px] text-inst-ink-2">Los grupos valen sólo para la actividad que elijas.</span>
              </span>
              <select
                class={campo}
                aria-label="Actividad"
                value={actividad}
                onchange={(e) => elegirActividad(Number((e.currentTarget as HTMLSelectElement).value))}
              >
                {#each actividades as a (a.id_actividad)}
                  <option value={a.id_actividad}>{a.nombre} · {a.cantidad_grupos} {a.cantidad_grupos === 1 ? 'grupo' : 'grupos'}</option>
                {/each}
              </select>
            </span>
          </label>
          <label class="flex cursor-not-allowed gap-3 rounded-lg border border-inst-linea bg-inst-sutil px-4 py-3.5 opacity-80">
            <input type="radio" name="alcance" disabled class="mt-1 size-[18px]" />
            <span class="flex flex-col gap-0.5">
              <span class="font-bold">Por curso</span>
              <span class="text-[13px] text-inst-ink-2">
                Todavía no disponible: los grupos se guardan por actividad. Para repetirlos en otra, elígela y usa «Reutilizar grupos».
              </span>
            </span>
          </label>
        </div>
      </section>

      <section class={tarjeta} aria-labelledby="t-paso2">
        <div class={tarjetaCabecera}>
          <span class="{chip} {tono.solido}">2</span>
          <h3 id="t-paso2" class="{titulo} m-0 text-[17px] leading-[22px]">Arma y modifica los grupos</h3>
          <span class="text-[13px] text-inst-ink-2">
            {elegida?.nombre} · {grupos.length} {grupos.length === 1 ? 'grupo' : 'grupos'} · {sinGrupo.length} {sinGrupo.length === 1 ? 'estudiante' : 'estudiantes'} sin grupo
          </span>
          {#if origenes.length > 0}
            <button type="button" class="{btnSecundario} {chico} ml-auto" onclick={() => ((reutilizar = true), (origen = null), (errorReutilizar = null))}>
              <Copy size={16} aria-hidden="true" />Reutilizar grupos
            </button>
          {/if}
        </div>

        <div class="flex flex-col lg:flex-row">
          <!-- Estudiantes para seleccionar -->
          <div class="flex min-w-0 flex-col border-inst-linea lg:w-[380px] lg:shrink-0 lg:border-r">
            <div class="flex flex-col gap-2.5 border-b border-inst-linea px-4.5 py-3.5">
              <label class="relative">
                <span class="sr-only">Buscar estudiante</span>
                <Search size={16} aria-hidden="true" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-inst-ink-3" />
                <input type="search" bind:value={busqueda} placeholder="Buscar estudiante" class="{campo} pl-9" />
              </label>
              <div class="inline-flex self-start overflow-hidden rounded-md border border-inst-control" role="group" aria-label="Mostrar">
                <button type="button" aria-pressed={mostrar === 'sin'} class="{BTN_SEG} {mostrar === 'sin' ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta hover:bg-inst-sutil'}" onclick={() => (mostrar = 'sin')}>
                  Sin grupo · {sinGrupo.length}
                </button>
                <button type="button" aria-pressed={mostrar === 'todos'} class="{BTN_SEG} {mostrar === 'todos' ? 'bg-inst-accion text-inst-sobre-accion' : 'bg-inst-tarjeta hover:bg-inst-sutil'}" onclick={() => (mostrar = 'todos')}>
                  Todos · {estudiantes.length}
                </button>
              </div>
            </div>
            <ul class="m-0 max-h-[520px] list-none overflow-y-auto px-4.5 py-1">
              {#each lista as e (e.id_estudiante)}
                {@const g = grupoDe.get(e.id_estudiante)}
                <li>
                  <label class="flex items-center gap-2.5 border-b border-inst-linea py-2.5 {g ? 'opacity-70' : 'cursor-pointer'}">
                    <input
                      type="checkbox"
                      class="size-[18px] shrink-0 accent-[var(--color-inst-accion)]"
                      disabled={!!g}
                      checked={seleccion.has(e.id_estudiante)}
                      onchange={() => alternar(e.id_estudiante)}
                    />
                    <AvatarAlumno src={e.url_foto ?? null} nombre={e.nombre_completo} class="size-8 bg-inst-sel text-xs font-extrabold" />
                    <span class="min-w-0 flex-1 {g ? '' : 'font-bold'}">{e.nombre_completo}</span>
                    {#if g}<span class="{chip} {tono.off}">{g.etiqueta}</span>{:else}<span class="{chip} {tono.warn}">Sin grupo</span>{/if}
                  </label>
                </li>
              {:else}
                <li class="py-6 text-center text-inst-ink-2">
                  {mostrar === 'sin' && !busqueda ? 'Todos los estudiantes tienen grupo.' : 'Ningún estudiante coincide con la búsqueda.'}
                </li>
              {/each}
            </ul>
            <div class="{tarjetaPie} mt-auto">
              <span class="text-[13px] font-bold">
                {seleccion.size} {seleccion.size === 1 ? 'seleccionado' : 'seleccionados'}
                {#if excede}<span class="block font-normal text-inst-error">Máximo {maximo} integrantes por grupo.</span>{/if}
              </span>
              <button type="button" class="{btnPrimario} ml-auto" disabled={seleccion.size === 0 || excede || ocupado} onclick={crearGrupo}>
                <UserPlus size={16} aria-hidden="true" />Crear grupo{seleccion.size > 0 ? ` con ${seleccion.size} ${seleccion.size === 1 ? 'estudiante' : 'estudiantes'}` : ''}
              </button>
            </div>
          </div>

          <!-- Grupos -->
          <div class="grid min-w-0 flex-1 grid-cols-[repeat(auto-fill,minmax(min(260px,100%),1fr))] content-start gap-3.5 bg-inst-sutil px-4.5 py-4">
            {#each grupos as g (g.grupo)}
              {@const libres = sinGrupo}
              <div class={tarjeta}>
                <div class="flex items-center gap-2 border-b border-inst-linea px-3.5 py-2.5">
                  {#if renombrando === g.grupo}
                    <input
                      class="{campo} min-h-9 py-1"
                      bind:value={nombreNuevo}
                      aria-label="Nombre de {g.etiqueta}"
                      placeholder={g.etiqueta}
                      maxlength={100}
                      onkeydown={(e) => e.key === 'Enter' && renombrar(g)}
                    />
                    <button type="button" class="{btnFantasma} {chico} px-0! size-11 lg:size-9" aria-label="Guardar el nombre" disabled={ocupado} onclick={() => renombrar(g)}>
                      <Check size={16} aria-hidden="true" />
                    </button>
                    <button type="button" class="{btnFantasma} {chico} px-0! size-11 lg:size-9" aria-label="Cancelar" onclick={() => (renombrando = null)}>
                      <X size={16} aria-hidden="true" />
                    </button>
                  {:else}
                    <span class="font-bold">{g.etiqueta}</span>
                    <span class="{chip} {tono.off}">{g.integrantes.length}</span>
                    <span class="ml-auto flex gap-0.5">
                      <button type="button" class="{btnFantasma} {chico} px-0! size-11 lg:size-9" aria-label="Renombrar {g.etiqueta}" onclick={() => ((renombrando = g.grupo), (nombreNuevo = g.nombre_grupo ?? ''))}>
                        <Pencil size={16} aria-hidden="true" />
                      </button>
                      <button type="button" class="{btnFantasma} {chico} px-0! size-11 text-inst-error lg:size-9" aria-label="Eliminar {g.etiqueta}" onclick={() => (eliminando = g)}>
                        <Trash2 size={16} aria-hidden="true" />
                      </button>
                    </span>
                  {/if}
                </div>
                <ul class="m-0 list-none px-3.5 py-1">
                  {#each g.integrantes as i (i.id_estudiante)}
                    <li class="flex items-center gap-2.5 py-1.5">
                      <AvatarAlumno src={i.url_foto ?? null} nombre={i.nombre_completo} class="size-8 bg-inst-sel text-xs font-extrabold" />
                      <span class="min-w-0 flex-1">{i.nombre_completo}</span>
                      <button type="button" class="{btnFantasma} {chico} px-0! size-11 lg:size-9" aria-label="Quitar a {i.nombre_completo} del {g.etiqueta}" disabled={ocupado} onclick={() => quitar(g, i)}>
                        <X size={16} aria-hidden="true" />
                      </button>
                    </li>
                  {:else}
                    <li class="py-2 text-[13px] text-inst-ink-2">Sin integrantes.</li>
                  {/each}
                </ul>
                <div class="px-3.5 pt-1 pb-3">
                  {#if agregandoA === g.grupo}
                    <div class="flex gap-2">
                      <select class="{campo} min-h-9 py-1" bind:value={agregarId} aria-label="Estudiante para agregar a {g.etiqueta}">
                        <option value={0}>Elige un estudiante</option>
                        {#each libres as e (e.id_estudiante)}<option value={e.id_estudiante}>{e.nombre_completo}</option>{/each}
                      </select>
                      <button type="button" class="{btnPrimario} {chico}" disabled={!agregarId || ocupado} onclick={() => agregar(g)}>Agregar</button>
                    </div>
                  {:else if libres.length > 0 && (maximo === null || g.integrantes.length < maximo)}
                    <button type="button" class="{btnFantasma} {chico} pl-1" onclick={() => ((agregandoA = g.grupo), (agregarId = 0))}>
                      <Plus size={16} aria-hidden="true" />Agregar estudiante
                    </button>
                  {/if}
                </div>
              </div>
            {:else}
              <p class="m-0 text-inst-ink-2">
                Todavía no hay grupos en esta actividad. Selecciona estudiantes a la izquierda y crea el primero.
              </p>
            {/each}
          </div>
        </div>
      </section>
    {/if}
  </div>
</CursoDocenteLayout>

{#if eliminando}
  <ModalInst titulo="Eliminar {eliminando.etiqueta}" onCerrar={() => !ocupado && (eliminando = null)}>
    <p class="m-0">
      Sus {eliminando.integrantes.length} integrantes quedan sin grupo en «{elegida?.nombre}».
      <strong>También se borran los mensajes y las entregas de su agenda</strong>, y no se pueden recuperar.
      Si el grupo ya tiene evaluación, no se elimina.
    </p>
    {#snippet pie()}
      <button type="button" class="{btnSecundario} ml-auto" disabled={ocupado} onclick={() => (eliminando = null)}>Cancelar</button>
      <button type="button" class="{btnPrimario} border-inst-error bg-inst-error hover:border-inst-error hover:bg-inst-error" disabled={ocupado} onclick={eliminar}>
        <Trash2 size={16} aria-hidden="true" />Eliminar grupo
      </button>
    {/snippet}
  </ModalInst>
{/if}

{#if reutilizar && actividad}
  <ReutilizarGruposModal
    idCurso={curso.id_curso}
    idActividad={actividad}
    actividades={origenes as any}
    seleccionada={origen}
    loading={copiando}
    error={errorReutilizar}
    onSeleccionar={(id) => (origen = id)}
    onCopiar={copiar}
    onCerrar={() => (reutilizar = false)}
  />
{/if}
