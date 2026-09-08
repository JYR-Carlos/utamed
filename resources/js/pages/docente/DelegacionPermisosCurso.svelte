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
  import { ShieldCheck, Users, ChevronsDownUp, ChevronsUpDown, CheckCircle2, AlertTriangle } from 'lucide-svelte';
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

  interface Curso {
    id_curso: number;
    cod_curso: string;
    nombre: string;
    letra_grupo?: string | null;
    semestre_real?: number | null;
    agno_real?: number | null;
  }

  interface Props {
    curso: Curso;
    miembros: Miembro[];
    grupos: Grupo[];
    id_contexto: number;
  }

  let { curso, miembros, grupos }: Props = $props();

  // ── Estado local ────────────────────────────────────────────────────────

  let permisosLocales = $state<Record<number, Record<string, boolean>>>(
    Object.fromEntries(miembros.map((m) => [m.id_usuario, { ...m.permisos }])),
  );

  /** Estado visual transitorio por celda. Ausente = 'reposo'. */
  let estadosCelda = $state<Record<string, Estado>>({});

  let expandido = $state<Record<string, boolean>>(
    Object.fromEntries(grupos.map((g) => [g.grupo, true])),
  );

  let banner = $state<{ tipo: 'success' | 'error'; texto: string } | null>(null);

  const toggleUrl = $derived(`/docente/cursos/${curso.id_curso}/delegacion-permisos/toggle`);
  const todosExpandidos = $derived(grupos.every((g) => expandido[g.grupo]));

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
      { id_usuario: idUsuario, slug, otorgar },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          estadosCelda[key] = 'confirmado';
          banner = {
            tipo: 'success',
            texto: `Permiso actualizado — "${accion}" ${otorgar ? 'concedido a' : 'revocado a'} ${persona}.`,
          };
          setTimeout(() => {
            if (estadosCelda[key] === 'confirmado') estadosCelda[key] = 'reposo';
          }, 1500);
        },
        onError: (errs) => {
          permisosLocales[idUsuario][slug] = !otorgar;
          estadosCelda[key] = 'error';
          banner = { tipo: 'error', texto: Object.values(errs)[0] || 'No se pudo actualizar el permiso.' };
          setTimeout(() => {
            if (estadosCelda[key] === 'error') estadosCelda[key] = 'reposo';
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
    { title: curso.nombre, href: `/docente/cursos/${curso.id_curso}` },
    { title: 'Delegación de permisos', href: '' },
  ]);

  const subtituloCurso = $derived(
    [curso.cod_curso, curso.letra_grupo ? `Grupo ${curso.letra_grupo}` : null, curso.semestre_real && curso.agno_real ? `${curso.semestre_real}º semestre ${curso.agno_real}` : null]
      .filter(Boolean)
      .join(' · '),
  );
</script>

<DocenteLayout {breadcrumbs}>
  <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 bg-white p-4 md:p-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <h1 class="text-2xl font-semibold tracking-tight text-[#1A1A24] md:text-[28px]">Delegación de permisos</h1>
        <div class="flex flex-wrap items-center gap-2.5">
          <span class="text-sm text-[#5A5E6E]">{subtituloCurso}</span>
          <span class="inline-flex items-center gap-1.5 rounded-full border border-[#C9D6E6] bg-[#E8EDF5] px-2.5 py-0.5 text-xs font-semibold text-[#002F6C]">
            <ShieldCheck class="h-[13px] w-[13px]" />
            Actúas como Docente Titular
          </span>
        </div>
      </div>
    </header>

    {#if banner}
      <div
        class="flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 text-sm {banner.tipo === 'success'
          ? 'border-[#A7F3D0] bg-[#ECFDF5] text-[#065F46]'
          : 'border-[#FECACA] bg-[#FEF2F2] text-[#991B1B]'}"
      >
        {#if banner.tipo === 'success'}
          <CheckCircle2 class="h-4 w-4 shrink-0 text-[#059669]" />
        {:else}
          <AlertTriangle class="h-4 w-4 shrink-0 text-[#DC2626]" />
        {/if}
        <span class="min-w-0 flex-1">{banner.texto}</span>
        <button
          class="shrink-0 {banner.tipo === 'success' ? 'text-[#047857]' : 'text-[#B91C1C]'} text-xs font-semibold"
          onclick={() => (banner = null)}
        >
          Cerrar
        </button>
      </div>
    {/if}

    {#if miembros.length === 0}
      <div class="flex items-start gap-3 rounded-xl border border-[#E5E7EB] bg-[#FAFBFC] p-5">
        <Users class="h-5 w-5 shrink-0 text-[#5A5E6E]" />
        <div class="flex flex-col gap-1">
          <span class="text-sm font-semibold text-[#1A1A24]">Sin miembros en el equipo</span>
          <p class="text-sm text-[#5A5E6E]">
            No hay otros docentes asignados a los componentes de este curso. Agrega miembros al equipo desde la
            gestión de equipo antes de delegar permisos.
          </p>
        </div>
      </div>
    {:else}
      <div class="flex flex-wrap items-center gap-3 rounded-xl border border-[#E5E7EB] bg-white p-3.5 shadow-sm">
        <span class="text-[13px] text-[#5A5E6E]">El titular no aparece en la matriz: conserva todos los permisos por definición.</span>
        <div class="ml-auto flex items-center gap-2.5">
          <span class="text-xs text-[#5A5E6E]">{miembros.length} {miembros.length === 1 ? 'persona' : 'personas'} en el equipo</span>
          <span class="h-4 w-px bg-[#E5E7EB]"></span>
          <button
            class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[13px] font-semibold text-[#002F6C] transition-colors hover:bg-[#F8FAFC]"
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
          />
        {/each}
      </div>
    {/if}
  </div>
</DocenteLayout>
