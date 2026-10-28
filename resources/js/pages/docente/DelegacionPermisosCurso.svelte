<script lang="ts">
  /**
   * Delegación de permisos — /docente/cursos/{curso}/delegacion-permisos.
   *
   * Matriz persona × permiso agrupada en 7 bloques colapsables (una tabla
   * por bloque, así ninguna pasa de 10 columnas). Guardado optimista por
   * celda, sin botón "Guardar": el switch se mueve al tocarlo y el servidor
   * confirma o revierte (PermisoSwitch + banner superior).
   *
   * El titular no aparece en la matriz — conserva todos los permisos por
   * definición — y sólo se delega a docentes del equipo (colegiados o
   * titulares de otro componente); la delegación a ayudantes no existe hoy
   * en el backend (assertIsMiembroCurso sólo reconoce docente_componente),
   * así que no se fabrica esa fila aquí.
   */
  import { router } from '@inertiajs/svelte';
  import DocenteLayout from '@/layouts/DocenteLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { ShieldCheck, Users, ChevronsDownUp, ChevronsUpDown, Layers } from 'lucide-svelte';
  import { Toaster, toast } from 'svelte-sonner';
  import GrupoPermisosMatriz from '@/components/docente/permisos/GrupoPermisosMatriz.svelte';
  import { formatSlugAction } from '@/utils/permisos';

  type Estado = 'reposo' | 'guardando' | 'confirmado' | 'error';

  interface Permiso {
    slug: string;
    nombre: string;
  }

  interface Grupo {
    grupo: string;
    permisos: Permiso[];
  }

  interface Miembro {
    id_usuario: number;
    id_docente: number;
    nombre: string;
    es_titular: boolean;
    tipo_componente?: string | null;
    permisos: Record<string, boolean>;
  }

  interface ComponenteItem {
    id_componente: number;
    tipo: string;
    id_contexto: number;
  }

  interface Curso {
    etiqueta?: string;
    id_curso: number;
    cod_curso: string;
    nombre: string;
    letra_grupo?: string | null;
    semestre_real?: number | null;
    agno_real?: number | null;
    id_contexto?: number;
  }

  interface Props {
    curso: Curso;
    componentes?: ComponenteItem[];
    contexto_activo?: number;
    id_contexto_curso?: number;
    miembros: Miembro[];
    grupos: Grupo[];
    id_contexto: number;
  }

  let {
    curso,
    componentes = [],
    contexto_activo,
    id_contexto_curso = curso.id_contexto ?? id_contexto,
    miembros,
    grupos,
    id_contexto,
  }: Props = $props();

  // ── Estado local ────────────────────────────────────────────────────────

  let contextoSeleccionado = $state<number>(contexto_activo ?? id_contexto_curso ?? id_contexto);

  let permisosLocales = $state<Record<number, Record<string, boolean>>>(
    Object.fromEntries(miembros.map((m) => [m.id_usuario, { ...m.permisos }])),
  );

  $effect(() => {
    contextoSeleccionado = contexto_activo ?? id_contexto_curso ?? id_contexto;
  });

  $effect(() => {
    permisosLocales = Object.fromEntries(miembros.map((m) => [m.id_usuario, { ...m.permisos }]));
  });

  /** Estado visual transitorio por celda. Ausente = 'reposo'. */
  let estadosCelda = $state<Record<string, Estado>>({});

  let expandido = $state<Record<string, boolean>>(
    Object.fromEntries(grupos.map((g) => [g.grupo, true])),
  );

  const etiquetaContexto = $derived(
    contextoSeleccionado === id_contexto_curso
      ? 'Curso Completo'
      : (componentes.find((c) => c.id_contexto === contextoSeleccionado)?.tipo ?? 'Componente'),
  );

  const toggleUrl = $derived(`/docente/cursos/${curso.id_curso}/delegacion-permisos/toggle`);
  const batchUrl = $derived(`/docente/cursos/${curso.id_curso}/delegacion-permisos/batch`);
  const todosExpandidos = $derived(grupos.every((g) => expandido[g.grupo]));
  const todosLosSlugs = $derived(grupos.flatMap((g) => g.permisos.map((p) => p.slug)));
  const totalPermisosDisponibles = $derived(todosLosSlugs.length);

  function cambiarContexto(nuevoContexto: number) {
    if (nuevoContexto === contextoSeleccionado) return;
    contextoSeleccionado = nuevoContexto;
    router.get(
      window.location.pathname,
      { contexto: nuevoContexto },
      {
        preserveState: true,
        preserveScroll: true,
        only: ['miembros', 'contexto_activo', 'id_contexto'],
      },
    );
  }

  function totalActivosMiembro(idUsuario: number): number {
    const usuarioPerms = permisosLocales[idUsuario] ?? {};
    return todosLosSlugs.filter((slug) => usuarioPerms[slug] === true).length;
  }

  function subtituloMiembro(m: Miembro): string {
    const rol = m.es_titular ? 'Docente de componente' : 'Docente colegiado';
    return m.tipo_componente ? `${rol} · ${m.tipo_componente}` : rol;
  }

  function claveCelda(idUsuario: number, slug: string): string {
    return `${idUsuario}:${slug}`;
  }

  function isChecked(idUsuario: number, slug: string): boolean {
    return permisosLocales[idUsuario]?.[slug] ?? false;
  }

  function getEstado(idUsuario: number, slug: string): Estado {
    return estadosCelda[claveCelda(idUsuario, slug)] ?? 'reposo';
  }

  function nombreMiembro(idUsuario: number): string {
    return miembros.find((m) => m.id_usuario === idUsuario)?.nombre ?? 'este integrante';
  }

  function handleToggle(idUsuario: number, slug: string, otorgar: boolean) {
    const key = claveCelda(idUsuario, slug);
    const accion = formatSlugAction(slug);
    const persona = nombreMiembro(idUsuario);

    permisosLocales[idUsuario][slug] = otorgar;
    estadosCelda[key] = 'guardando';

    router.post(
      toggleUrl,
      { id_usuario: idUsuario, slug, otorgar, id_contexto: contextoSeleccionado },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          estadosCelda[key] = 'confirmado';
          toast.success(`"${accion}" ${otorgar ? 'concedido a' : 'revocado a'} ${persona} (${etiquetaContexto}).`);
          setTimeout(() => {
            if (estadosCelda[key] === 'confirmado') estadosCelda[key] = 'reposo';
          }, 1500);
        },
        onError: (errs) => {
          permisosLocales[idUsuario][slug] = !otorgar;
          estadosCelda[key] = 'error';
          toast.error(Object.values(errs)[0] || 'No se pudo actualizar el permiso.');
          setTimeout(() => {
            if (estadosCelda[key] === 'error') estadosCelda[key] = 'reposo';
          }, 2500);
        },
      },
    );
  }

  function handleBatch(
    idUsuario: number,
    slugs: string[],
    otorgar: boolean,
    nombreSeccion?: string,
  ) {
    if (slugs.length === 0) return;

    const persona = nombreMiembro(idUsuario);
    const prevPermisos = { ...permisosLocales[idUsuario] };

    // Actualización optimista inmediata
    for (const slug of slugs) {
      permisosLocales[idUsuario][slug] = otorgar;
      estadosCelda[claveCelda(idUsuario, slug)] = 'guardando';
    }

    router.post(
      batchUrl,
      { id_usuario: idUsuario, slugs, otorgar, id_contexto: contextoSeleccionado },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          for (const slug of slugs) {
            estadosCelda[claveCelda(idUsuario, slug)] = 'confirmado';
          }
          toast.success(
            `${slugs.length} permisos ${otorgar ? 'otorgados' : 'revocados'}${nombreSeccion ? ' (' + nombreSeccion + ')' : ''} a ${persona} (${etiquetaContexto}).`,
          );
          setTimeout(() => {
            for (const slug of slugs) {
              if (estadosCelda[claveCelda(idUsuario, slug)] === 'confirmado') {
                estadosCelda[claveCelda(idUsuario, slug)] = 'reposo';
              }
            }
          }, 1500);
        },
        onError: (errs) => {
          permisosLocales[idUsuario] = prevPermisos;
          for (const slug of slugs) {
            estadosCelda[claveCelda(idUsuario, slug)] = 'error';
          }
          toast.error(Object.values(errs)[0] || 'No se pudieron actualizar los permisos en lote.');
          setTimeout(() => {
            for (const slug of slugs) {
              if (estadosCelda[claveCelda(idUsuario, slug)] === 'error') {
                estadosCelda[claveCelda(idUsuario, slug)] = 'reposo';
              }
            }
          }, 2500);
        },
      },
    );
  }

  function alternarTodos() {
    const next = !todosExpandidos;
    expandido = Object.fromEntries(grupos.map((g) => [g.grupo, next]));
  }

  const breadcrumbs: BreadcrumbItem[] = $derived([
    { title: 'Mis cursos', href: '/docente/cursos' },
    { title: curso.etiqueta ?? curso.nombre, href: `/docente/cursos/${curso.id_curso}` },
    { title: 'Delegación de permisos', href: '' },
  ]);

  const subtituloCurso = $derived(
    [curso.cod_curso, curso.letra_grupo ? `Grupo ${curso.letra_grupo}` : null, curso.semestre_real && curso.agno_real ? `${curso.semestre_real}º semestre ${curso.agno_real}` : null]
      .filter(Boolean)
      .join(' · '),
  );
</script>

<DocenteLayout {breadcrumbs}>
  <Toaster richColors position="bottom-right" />
  <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 bg-white p-4 md:p-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-2xl font-semibold tracking-tight text-ink md:text-[28px]">Delegación de permisos</h1>
        <div class="flex flex-wrap items-center gap-2.5">
          <span class="text-sm text-ink-2">{subtituloCurso}</span>
          <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-uta-blue-light px-2.5 py-0.5 text-xs font-semibold text-navy">
            <ShieldCheck class="h-[13px] w-[13px]" />
            Actúas como Docente Titular
          </span>
        </div>
      </div>
    </header>

    {#if componentes.length > 0}
      <div class="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 shadow-xs">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <Layers class="h-4 w-4 text-uta-blue" />
            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Ámbito de delegación</span>
          </div>
          <span class="text-xs font-medium text-slate-500">
            {contextoSeleccionado === id_contexto_curso
              ? 'Permisos delegados a nivel de Curso (afectan transversalmente)'
              : `Permisos delegados específicamente para el componente ${etiquetaContexto}`}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {contextoSeleccionado === id_contexto_curso
              ? 'bg-navy text-white shadow-xs'
              : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'}"
            onclick={() => cambiarContexto(id_contexto_curso)}
          >
            Todo el Curso (General)
          </button>
          {#each componentes as comp (comp.id_componente)}
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {contextoSeleccionado === comp.id_contexto
                ? 'bg-navy text-white shadow-xs'
                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'}"
              onclick={() => cambiarContexto(comp.id_contexto)}
            >
              {comp.tipo}
            </button>
          {/each}
        </div>
      </div>
    {/if}

    {#if miembros.length === 0}
      <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-5">
        <Users class="h-5 w-5 shrink-0 text-ink-2" />
        <div class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-ink">Sin miembros en el equipo</span>
          <p class="text-sm text-ink-2">
            No hay otros docentes asignados a los componentes de este curso. Agrega miembros al equipo desde la
            gestión de equipo antes de delegar permisos.
          </p>
        </div>
      </div>
    {:else}
      <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm">
        <span class="text-[13px] text-ink-2">El titular no aparece en la matriz: conserva todos los permisos por definición.</span>
        <div class="ml-auto flex items-center gap-2.5">
          <span class="text-xs text-ink-2">{miembros.length} {miembros.length === 1 ? 'persona' : 'personas'} en el equipo</span>
          <span class="h-4 w-px bg-gray-200"></span>
          <button
            class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[13px] font-semibold text-navy transition-colors hover:bg-slate-50"
            onclick={alternarTodos}
          >
            {#if todosExpandidos}
              <ChevronsDownUp class="h-3.5 w-3.5" />
              Colapsar todo
            {:else}
              <ChevronsUpDown class="h-3.5 w-3.5" />
              Expandir todo
            {/if}
          </button>
        </div>
      </div>

      <!-- Acciones globales por integrante (otorgar / revocar todo el curso) -->
      <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <ShieldCheck class="h-4 w-4 text-uta-blue" />
            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Acciones rápidas globales por integrante</span>
          </div>
          <span class="text-xs text-slate-500">Aplica a todas las {grupos.length} secciones ({totalPermisosDisponibles} permisos) en {etiquetaContexto}</span>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {#each miembros as miembro (miembro.id_usuario)}
            {@const activos = totalActivosMiembro(miembro.id_usuario)}
            {@const total = totalPermisosDisponibles}
            <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-xs">
              <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 flex-col">
                  <span class="truncate text-sm font-semibold text-ink">{miembro.nombre}</span>
                  <span class="truncate text-xs text-slate-500">{subtituloMiembro(miembro)}</span>
                </div>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-medium text-slate-700">
                  {activos} / {total}
                </span>
              </div>
              <div class="flex items-center gap-2 pt-1 border-t border-slate-100">
                <button
                  type="button"
                  class="flex-1 rounded-md bg-uta-blue px-2.5 py-1.5 text-center text-xs font-semibold text-white shadow-xs hover:bg-navy disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  disabled={activos === total}
                  onclick={() => handleBatch(miembro.id_usuario, todosLosSlugs, true, 'todas las secciones')}
                >
                  Otorgar todos
                </button>
                <button
                  type="button"
                  class="flex-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-red-50 hover:border-red-200 hover:text-red-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                  disabled={activos === 0}
                  onclick={() => handleBatch(miembro.id_usuario, todosLosSlugs, false, 'todas las secciones')}
                >
                  Revocar todos
                </button>
              </div>
            </div>
          {/each}
        </div>
      </div>

      <div class="flex flex-col gap-2.5">
        {#each grupos as grupo, i (grupo.grupo)}
          <GrupoPermisosMatriz
            numero={i + 1}
            {grupo}
            {miembros}
            expandido={expandido[grupo.grupo] ?? true}
            onToggleExpandido={() => (expandido[grupo.grupo] = !expandido[grupo.grupo])}
            {isChecked}
            {getEstado}
            onToggle={handleToggle}
            onBatch={handleBatch}
          />
        {/each}
      </div>
    {/if}
  </div>
</DocenteLayout>
