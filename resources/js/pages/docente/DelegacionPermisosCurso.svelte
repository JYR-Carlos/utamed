<script lang="ts">
  /**
   * Delegación de permisos — /docente/cursos/{curso}/delegacion-permisos.
   *
   * Matriz persona × permiso con control tri-estado (✕ Revocado | — Neutro | ✓ Asignado):
   * - Guardado optimista por celda con feedback inmediato y rollback automático ante error.
   * - Fix de checks en cascada: al bajar a 'neutro' o 'revocado', sólo los dependientes que
   *   realmente estaban en 'asignado' (más el permiso principal) se marcan con spinner/check.
   * - Regla de colisión padre-hijo: al revocar ('revocado') en Todo el Curso teniendo
   *   asignaciones directas en componentes hijos, pide confirmación antes de neutralizar hijos.
   */
  import { untrack } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import CursoDocenteLayout from '@/layouts/CursoDocenteLayout.svelte';
  import { ShieldCheck, Users, ChevronsDownUp, ChevronsUpDown, Layers, AlertTriangle, ChevronDown, ChevronRight } from 'lucide-svelte';
  import { Toaster, toast } from 'svelte-sonner';
  import GrupoPermisosMatriz, { type CeldaInfo } from '@/components/docente/permisos/GrupoPermisosMatriz.svelte';
  import type { ValorPermiso, EstadoTransitorio, TipoBloqueo } from '@/components/docente/permisos/PermisoTriState.svelte';
  import {
    formatSlugAction,
    resolveRequiredToGrant,
    resolveCascadeToRevoke,
  } from '@/utils/permisos';

  interface Permiso {
    slug: string;
    nombre: string;
  }

  interface Grupo {
    grupo: string;
    permisos: Permiso[];
  }

  interface PermisoEstado {
    activo: boolean;
    origen: 'directo' | 'heredado' | 'revocado' | 'revocado_padre' | 'inactivo';
    estado?: ValorPermiso;
    activoPorRol?: boolean;
    bloqueadoPor?: TipoBloqueo;
    motivoBloqueo?: string | null;
  }

  interface Miembro {
    id_usuario: number;
    id_docente: number;
    nombre: string;
    es_titular: boolean;
    tipo_componente?: string | null;
    permisos_por_contexto?: Record<number, Record<string, PermisoEstado>>;
    permisos?: Record<string, boolean>;
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

  interface ColisionHijo {
    tipo: string;
    id_contexto: number;
    slugs: string[];
  }

  interface ConfirmacionPendiente {
    tipo: 'single' | 'batch';
    idUsuario: number;
    persona: string;
    slug?: string;
    slugs?: string[];
    nombreSeccion?: string;
    colisiones: ColisionHijo[];
  }

  interface Props {
    curso: Curso;
    componentes?: ComponenteItem[];
    id_contexto_curso?: number;
    puedeDelegarCurso?: boolean;
    delegablesPorContexto?: Record<number, string[]>;
    miembros: Miembro[];
    grupos: Grupo[];
    dependencias?: Record<string, string[]>;
  }

  let {
    curso,
    componentes = [],
    id_contexto_curso = curso.id_contexto ?? 0,
    puedeDelegarCurso = true,
    delegablesPorContexto = {},
    miembros,
    grupos,
    dependencias = {},
  }: Props = $props();

  // ── Estado local ────────────────────────────────────────────────────────

  let contextoSeleccionado = $state<number>(
    untrack(() =>
      puedeDelegarCurso ? id_contexto_curso : (componentes[0]?.id_contexto ?? id_contexto_curso),
    ),
  );

  let permisosLocales = $state<Record<number, Record<string, PermisoEstado>>>({});
  let estadosCelda = $state<Record<string, EstadoTransitorio>>({});
  let confirmacionHijos = $state<ConfirmacionPendiente | null>(null);
  let accionesRapidasAbiertas = $state(false);

  function normalizarEstado(raw?: PermisoEstado): CeldaInfo {
    if (!raw) {
      return { estado: 'neutro', activoPorRol: false, bloqueadoPor: null, motivoBloqueo: null };
    }
    const estado: ValorPermiso =
      raw.estado ??
      (raw.origen === 'revocado' || raw.origen === 'revocado_padre'
        ? 'revocado'
        : raw.activo
          ? 'asignado'
          : 'neutro');
    const bloqueadoPor: TipoBloqueo =
      raw.bloqueadoPor !== undefined
        ? raw.bloqueadoPor
        : raw.origen === 'heredado'
          ? 'heredado'
          : raw.origen === 'revocado_padre'
            ? 'revocado'
            : null;
    return {
      estado,
      activoPorRol: raw.activoPorRol ?? false,
      bloqueadoPor,
      motivoBloqueo: raw.motivoBloqueo ?? null,
    };
  }

  $effect(() => {
    const map: Record<number, Record<string, PermisoEstado>> = {};
    for (const m of miembros) {
      if (m.permisos_por_contexto?.[contextoSeleccionado]) {
        const cloned: Record<string, PermisoEstado> = {};
        for (const [slug, val] of Object.entries(m.permisos_por_contexto[contextoSeleccionado])) {
          cloned[slug] = { ...val };
        }
        map[m.id_usuario] = cloned;
      } else if (m.permisos) {
        map[m.id_usuario] = Object.fromEntries(
          Object.entries(m.permisos).map(([k, v]) => [
            k,
            { activo: v, origen: v ? 'directo' : 'inactivo', estado: v ? 'asignado' : 'neutro' },
          ]),
        );
      } else {
        map[m.id_usuario] = {};
      }
    }
    permisosLocales = map;
  });

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
  const delegablesActivosSet = $derived.by(() => {
    const slugs = delegablesPorContexto[contextoSeleccionado];
    return slugs ? new Set(slugs) : null;
  });
  const gruposVisibles = $derived.by(() => {
    if (!delegablesActivosSet || delegablesActivosSet.size === 0) return grupos;
    return grupos
      .map((g) => ({
        ...g,
        permisos: g.permisos.filter((p) => delegablesActivosSet.has(p.slug)),
      }))
      .filter((g) => g.permisos.length > 0);
  });
  const todosExpandidos = $derived(gruposVisibles.every((g) => expandido[g.grupo]));
  const todosLosSlugs = $derived(gruposVisibles.flatMap((g) => g.permisos.map((p) => p.slug)));
  const totalPermisosDisponibles = $derived(todosLosSlugs.length);

  function cambiarContexto(nuevoContexto: number) {
    contextoSeleccionado = nuevoContexto;
  }

  function totalPorEstadoMiembro(idUsuario: number, valor: ValorPermiso): number {
    return todosLosSlugs.filter((slug) => getCelda(idUsuario, slug).estado === valor).length;
  }

  function subtituloMiembro(m: Miembro): string {
    const rol = m.es_titular ? 'Docente de componente' : 'Docente colegiado';
    return m.tipo_componente ? `${rol} · ${m.tipo_componente}` : rol;
  }

  function claveCelda(idUsuario: number, slug: string): string {
    return `${idUsuario}:${slug}`;
  }

  function getCelda(idUsuario: number, slug: string): CeldaInfo {
    return normalizarEstado(permisosLocales[idUsuario]?.[slug]);
  }

  function getEstado(idUsuario: number, slug: string): EstadoTransitorio {
    return estadosCelda[claveCelda(idUsuario, slug)] ?? 'reposo';
  }

  function nombreMiembro(idUsuario: number): string {
    return miembros.find((m) => m.id_usuario === idUsuario)?.nombre ?? 'este integrante';
  }

  /** Detecta localmente asignaciones directas en componentes hijos antes de revocar en Curso Completo. */
  function detectarColisionesHijos(idUsuario: number, slugsAfectados: string[]): ColisionHijo[] {
    if (contextoSeleccionado !== id_contexto_curso) return [];
    const miembro = miembros.find((m) => m.id_usuario === idUsuario);
    if (!miembro?.permisos_por_contexto) return [];

    const colisiones: ColisionHijo[] = [];
    for (const comp of componentes) {
      const mapaComp = miembro.permisos_por_contexto[comp.id_contexto];
      if (!mapaComp) continue;
      const activosDirectos = slugsAfectados.filter((s) => {
        const entry = mapaComp[s];
        return entry && entry.origen === 'directo' && (entry.estado === 'asignado' || entry.activo);
      });
      if (activosDirectos.length > 0) {
        colisiones.push({ tipo: comp.tipo, id_contexto: comp.id_contexto, slugs: activosDirectos });
      }
    }
    return colisiones;
  }

  function aplicarEstadoOptimista(idUsuario: number, slug: string, nuevoEstado: ValorPermiso) {
    const actual = permisosLocales[idUsuario]?.[slug];
    permisosLocales[idUsuario][slug] = {
      activo: nuevoEstado === 'asignado',
      origen: nuevoEstado === 'asignado' ? 'directo' : nuevoEstado === 'revocado' ? 'revocado' : 'inactivo',
      estado: nuevoEstado,
      activoPorRol: actual?.activoPorRol ?? false,
      bloqueadoPor: null,
      motivoBloqueo: null,
    };
    estadosCelda[claveCelda(idUsuario, slug)] = 'guardando';
  }

  function handleChange(idUsuario: number, slug: string, nuevoEstado: ValorPermiso, confirmarHijos = false) {
    const celda = getCelda(idUsuario, slug);
    if (celda.bloqueadoPor !== null) {
      toast.error(celda.motivoBloqueo || `"${formatSlugAction(slug)}" está bloqueado por nivel superior.`);
      return;
    }

    const persona = nombreMiembro(idUsuario);

    // Resolver grafo de dependencias
    const slugsGrafo =
      nuevoEstado === 'asignado'
        ? resolveRequiredToGrant(slug, dependencias)
        : resolveCascadeToRevoke(slug, dependencias);

    // Pre-check de colisión padre-hijo al revocar en Curso Completo
    if (nuevoEstado === 'revocado' && !confirmarHijos) {
      const colisiones = detectarColisionesHijos(idUsuario, slugsGrafo);
      if (colisiones.length > 0) {
        confirmacionHijos = { tipo: 'single', idUsuario, persona, slug, colisiones };
        return;
      }
    }

    // Filtrar:
    // - nunca mutar celdas bloqueadas por padre
    // - FIX BUG CHECKS EN CASCADA: al pasar a 'neutro' o 'revocado', sólo mutar el slug principal
    //   y los dependientes que realmente estaban en 'asignado' (no tocar los ya neutros/revocados).
    const slugsAMutar = slugsGrafo.filter((s) => {
      const info = getCelda(idUsuario, s);
      if (info.bloqueadoPor !== null) return false;
      if (s === slug) return true;
      if (nuevoEstado === 'asignado') return info.estado !== 'asignado';
      return info.estado === 'asignado';
    });

    if (slugsAMutar.length === 0) return;

    const prevPermisos = JSON.parse(JSON.stringify(permisosLocales[idUsuario] ?? {}));

    for (const s of slugsAMutar) {
      const targetEstado: ValorPermiso =
        s === slug ? nuevoEstado : nuevoEstado === 'asignado' ? 'asignado' : 'neutro';
      aplicarEstadoOptimista(idUsuario, s, targetEstado);
    }

    router.post(
      toggleUrl,
      {
        id_usuario: idUsuario,
        slug,
        estado: nuevoEstado,
        id_contexto: contextoSeleccionado,
        confirmar_cascada_hijos: confirmarHijos,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          for (const s of slugsAMutar) {
            estadosCelda[claveCelda(idUsuario, s)] = 'confirmado';
          }
          const accionPrincipal = formatSlugAction(slug);
          const slugsExtra = slugsAMutar.filter((s) => s !== slug);
          const extra =
            slugsExtra.length > 0
              ? nuevoEstado === 'asignado'
                ? ` (junto a ${slugsExtra.length} requisitos)`
                : ` (y ${slugsExtra.length} dependientes neutralizados)`
              : '';
          const verbo =
            nuevoEstado === 'asignado'
              ? 'asignado a'
              : nuevoEstado === 'revocado'
                ? 'revocado explícitamente a'
                : 'restablecido a neutro para';
          toast.success(`"${accionPrincipal}"${extra} ${verbo} ${persona} (${etiquetaContexto}).`);

          setTimeout(() => {
            for (const s of slugsAMutar) {
              if (estadosCelda[claveCelda(idUsuario, s)] === 'confirmado') {
                estadosCelda[claveCelda(idUsuario, s)] = 'reposo';
              }
            }
          }, 1500);
        },
        onError: (errs) => {
          permisosLocales[idUsuario] = prevPermisos;
          for (const s of slugsAMutar) {
            estadosCelda[claveCelda(idUsuario, s)] = 'error';
          }
          toast.error(Object.values(errs)[0] || 'No se pudo actualizar el permiso.');
          setTimeout(() => {
            for (const s of slugsAMutar) {
              if (estadosCelda[claveCelda(idUsuario, s)] === 'error') {
                estadosCelda[claveCelda(idUsuario, s)] = 'reposo';
              }
            }
          }, 2500);
        },
      },
    );
  }

  function handleBatch(
    idUsuario: number,
    slugs: string[],
    nuevoEstado: ValorPermiso,
    nombreSeccion?: string,
    confirmarHijos = false,
  ) {
    if (slugs.length === 0) return;

    const slugsModificables = slugs.filter((s) => getCelda(idUsuario, s).bloqueadoPor === null);
    if (slugsModificables.length === 0) {
      toast.info('Los permisos seleccionados están bloqueados por nivel superior (Curso).');
      return;
    }

    const persona = nombreMiembro(idUsuario);
    const slugsGrafo =
      nuevoEstado === 'asignado'
        ? resolveRequiredToGrant(slugsModificables, dependencias)
        : resolveCascadeToRevoke(slugsModificables, dependencias);

    if (nuevoEstado === 'revocado' && !confirmarHijos) {
      const colisiones = detectarColisionesHijos(idUsuario, slugsGrafo);
      if (colisiones.length > 0) {
        confirmacionHijos = { tipo: 'batch', idUsuario, persona, slugs: slugsModificables, nombreSeccion, colisiones };
        return;
      }
    }

    const slugsSolicitadosSet = new Set(slugsModificables);
    const slugsAMutar = slugsGrafo.filter((s) => {
      const info = getCelda(idUsuario, s);
      if (info.bloqueadoPor !== null) return false;
      if (slugsSolicitadosSet.has(s)) return info.estado !== nuevoEstado;
      if (nuevoEstado === 'asignado') return info.estado !== 'asignado';
      return info.estado === 'asignado';
    });

    if (slugsAMutar.length === 0) {
      toast.info('Los permisos seleccionados ya se encuentran en ese estado.');
      return;
    }

    const prevPermisos = JSON.parse(JSON.stringify(permisosLocales[idUsuario] ?? {}));
    for (const s of slugsAMutar) {
      aplicarEstadoOptimista(idUsuario, s, nuevoEstado);
    }

    router.post(
      batchUrl,
      {
        id_usuario: idUsuario,
        slugs: slugsModificables,
        estado: nuevoEstado,
        id_contexto: contextoSeleccionado,
        confirmar_cascada_hijos: confirmarHijos,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          for (const s of slugsAMutar) {
            estadosCelda[claveCelda(idUsuario, s)] = 'confirmado';
          }
          const verbo =
            nuevoEstado === 'asignado'
              ? 'asignados'
              : nuevoEstado === 'revocado'
                ? 'revocados'
                : 'restablecidos a neutro';
          toast.success(
            `${slugsAMutar.length} permisos ${verbo}${nombreSeccion ? ' (' + nombreSeccion + ')' : ''} a ${persona} (${etiquetaContexto}).`,
          );
          setTimeout(() => {
            for (const s of slugsAMutar) {
              if (estadosCelda[claveCelda(idUsuario, s)] === 'confirmado') {
                estadosCelda[claveCelda(idUsuario, s)] = 'reposo';
              }
            }
          }, 1500);
        },
        onError: (errs) => {
          permisosLocales[idUsuario] = prevPermisos;
          for (const s of slugsAMutar) {
            estadosCelda[claveCelda(idUsuario, s)] = 'error';
          }
          toast.error(Object.values(errs)[0] || 'No se pudieron actualizar los permisos en lote.');
          setTimeout(() => {
            for (const s of slugsAMutar) {
              if (estadosCelda[claveCelda(idUsuario, s)] === 'error') {
                estadosCelda[claveCelda(idUsuario, s)] = 'reposo';
              }
            }
          }, 2500);
        },
      },
    );
  }

  function ejecutarConfirmacionHijos() {
    if (!confirmacionHijos) return;
    const pending = confirmacionHijos;
    confirmacionHijos = null;

    if (pending.tipo === 'single' && pending.slug) {
      handleChange(pending.idUsuario, pending.slug, 'revocado', true);
    } else if (pending.tipo === 'batch' && pending.slugs) {
      handleBatch(pending.idUsuario, pending.slugs, 'revocado', pending.nombreSeccion, true);
    }
  }

  function alternarTodos() {
    const next = !todosExpandidos;
    expandido = Object.fromEntries(grupos.map((g) => [g.grupo, next]));
  }

  const subtituloCurso = $derived(
    [
      curso.cod_curso,
      curso.letra_grupo ? `Grupo ${curso.letra_grupo}` : null,
      curso.semestre_real && curso.agno_real ? `${curso.semestre_real}º semestre ${curso.agno_real}` : null,
    ]
      .filter(Boolean)
      .join(' · '),
  );
</script>

<CursoDocenteLayout idCurso={curso.id_curso} seccion="gestion" subpagina="Delegación de permisos" nombre={curso.etiqueta ?? curso.nombre}>
  <Toaster richColors position="bottom-right" />
  <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 bg-white p-4 md:p-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-2xl font-semibold tracking-tight text-ink md:text-[28px]">Delegación de permisos</h1>
        <div class="flex flex-wrap items-center gap-2.5">
          <span class="text-sm text-ink-2">{subtituloCurso}</span>
          <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-uta-blue-light px-2.5 py-0.5 text-xs font-semibold text-navy">
            <ShieldCheck class="h-[13px] w-[13px]" />
            {puedeDelegarCurso ? 'Actúas como Docente Titular' : 'Actúas como Titular de Componente'}
          </span>
        </div>
        {#if !puedeDelegarCurso}
          <p class="mt-1 text-xs text-ink-2">
            Solo puedes gestionar permisos en los componentes donde eres titular ({componentes.map((c) => c.tipo).join(', ')}).
            Los componentes donde participas como colegiado y los permisos generales del curso los administra su respectivo titular.
          </p>
        {/if}
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
              ? 'Permisos delegados o revocados a nivel de Curso (afectan transversalmente a todos los componentes)'
              : `Permisos específicos para ${etiquetaContexto} (las asignaciones o revocaciones de Curso se heredan bloqueadas)`}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          {#if puedeDelegarCurso}
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {contextoSeleccionado === id_contexto_curso
                ? 'bg-navy text-white shadow-xs'
                : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'}"
              onclick={() => cambiarContexto(id_contexto_curso)}
            >
              Todo el Curso (General)
            </button>
          {/if}
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
        <div class="flex flex-wrap items-center gap-3 text-xs text-ink-2">
          <span><strong class="text-red-700">✕</strong> Revocado (bloquea incluso rol base)</span>
          <span>·</span>
          <span><strong class="text-slate-700">—</strong> Neutro (punto azul = activo por rol)</span>
          <span>·</span>
          <span><strong class="text-navy">✓</strong> Asignado explícitamente</span>
        </div>
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

      <!-- Acciones globales por integrante (colapsable, colapsado al inicio) -->
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/70 shadow-sm">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-2 px-4 py-3 text-left transition-colors hover:bg-slate-100/70"
          onclick={() => (accionesRapidasAbiertas = !accionesRapidasAbiertas)}
          aria-expanded={accionesRapidasAbiertas}
        >
          <div class="flex items-center gap-2">
            {#if accionesRapidasAbiertas}
              <ChevronDown class="h-4 w-4 text-slate-600" />
            {:else}
              <ChevronRight class="h-4 w-4 text-slate-600" />
            {/if}
            <ShieldCheck class="h-4 w-4 text-uta-blue" />
            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Acciones rápidas globales por integrante</span>
          </div>
          <span class="text-xs text-slate-500">
            {gruposVisibles.length} secciones ({totalPermisosDisponibles} permisos) en {etiquetaContexto}
          </span>
        </button>
        {#if accionesRapidasAbiertas}
          <div class="border-t border-slate-200 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {#each miembros as miembro (miembro.id_usuario)}
                {@const asignados = totalPorEstadoMiembro(miembro.id_usuario, 'asignado')}
                {@const neutros = totalPorEstadoMiembro(miembro.id_usuario, 'neutro')}
                {@const revocados = totalPorEstadoMiembro(miembro.id_usuario, 'revocado')}
                {@const total = totalPermisosDisponibles}
                <div class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-xs">
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 flex-col">
                      <span class="truncate text-sm font-semibold text-ink">{miembro.nombre}</span>
                      <span class="truncate text-xs text-slate-500">{subtituloMiembro(miembro)}</span>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-medium text-slate-700">
                      {asignados}✓ · {revocados}✕ / {total}
                    </span>
                  </div>
                  <div class="flex items-center gap-1.5 border-t border-slate-100 pt-1.5">
                    <button
                      type="button"
                      class="flex-1 rounded-md bg-uta-blue px-2 py-1.5 text-center text-xs font-semibold text-white shadow-xs hover:bg-navy disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                      disabled={asignados === total}
                      onclick={() => handleBatch(miembro.id_usuario, todosLosSlugs, 'asignado', 'todas las secciones')}
                    >
                      Otorgar
                    </button>
                    <button
                      type="button"
                      class="flex-1 rounded-md border border-slate-300 bg-white px-2 py-1.5 text-center text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                      disabled={neutros === total}
                      onclick={() => handleBatch(miembro.id_usuario, todosLosSlugs, 'neutro', 'todas las secciones')}
                    >
                      Neutro
                    </button>
                    <button
                      type="button"
                      class="flex-1 rounded-md border border-red-200 bg-white px-2 py-1.5 text-center text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                      disabled={revocados === total}
                      onclick={() => handleBatch(miembro.id_usuario, todosLosSlugs, 'revocado', 'todas las secciones')}
                    >
                      Revocar
                    </button>
                  </div>
                </div>
              {/each}
            </div>
          </div>
        {/if}
      </div>

      <div class="flex flex-col gap-2.5">
        {#each gruposVisibles as grupo, i (grupo.grupo)}
          <GrupoPermisosMatriz
            numero={i + 1}
            {grupo}
            {miembros}
            expandido={expandido[grupo.grupo] ?? true}
            onToggleExpandido={() => (expandido[grupo.grupo] = !expandido[grupo.grupo])}
            {getCelda}
            {getEstado}
            onChange={handleChange}
            onBatch={handleBatch}
          />
        {/each}
      </div>
    {/if}
  </div>
  {#if confirmacionHijos}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-5 shadow-xl">
        <div class="flex items-start gap-3">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700">
            <AlertTriangle class="h-5 w-5" />
          </div>
          <div class="flex flex-col gap-1.5">
            <h2 class="text-base font-semibold text-ink">Confirmar revocación a nivel de Curso</h2>
            <p class="text-xs leading-relaxed text-ink-2">
              <strong>{confirmacionHijos.persona}</strong> tiene permisos asignados directamente en los siguientes
              componentes. Al revocar a nivel de Curso, esas asignaciones directas serán neutralizadas para mantener
              consistencia jerárquica:
            </p>
            <ul class="mt-1 space-y-1 rounded-lg border border-red-100 bg-red-50/50 p-2.5 text-xs text-red-900">
              {#each confirmacionHijos.colisiones as col (col.id_contexto)}
                <li>
                  <strong>{col.tipo}:</strong>
                  {col.slugs.map((s) => formatSlugAction(s)).join(', ')}
                </li>
              {/each}
            </ul>
          </div>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button
            type="button"
            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-gray-50"
            onclick={() => (confirmacionHijos = null)}
          >
            Cancelar
          </button>
          <button
            type="button"
            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700"
            onclick={ejecutarConfirmacionHijos}
          >
            Confirmar y revocar en Curso
          </button>
        </div>
      </div>
    </div>
  {/if}
</CursoDocenteLayout>
