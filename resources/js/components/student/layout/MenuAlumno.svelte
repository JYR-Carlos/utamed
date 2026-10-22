<script lang="ts">
  /**
   * Menú lateral del alumno (guía dm): noche en claro y en oscuro.
   *
   * Inicio, los cursos del semestre vigente con su color y el acceso a todos
   * los cursos (los de semestres anteriores están ahí). Abajo, otros ambientes
   * si la cuenta tiene más de un rol, y la persona con su salida.
   * Lo usan la columna de escritorio y el cajón móvil de StudentLayout.
   */
  import { Link, page, router } from '@inertiajs/svelte';
  import { ChevronRight, House, LogOut } from 'lucide-svelte';
  import { logout } from '@/routes';
  import { getInitials } from '@/hooks/useInitials';
  import { titleCase } from '@/lib/utils';
  import { usePermissions } from '@/lib/composables/usePermissions';
  import { AMBIENTES, ambientesDisponibles } from '@/lib/ambientes';
  import { estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import type { SidebarCourse } from '@/types';

  const { can } = usePermissions();

  const cursos = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? []);

  // Semestre vigente = el más reciente con cursos; los anteriores quedan en «Mis cursos».
  const vigentes = $derived.by(() => {
    const conPeriodo = cursos.filter((c) => c.agno_real != null && c.semestre_real != null);
    if (conPeriodo.length === 0) return [];
    const ultimo = conPeriodo.reduce((a, c) =>
      c.agno_real > a.agno_real || (c.agno_real === a.agno_real && c.semestre_real > a.semestre_real) ? c : a,
    );
    return conPeriodo.filter((c) => c.agno_real === ultimo.agno_real && c.semestre_real === ultimo.semestre_real);
  });

  const otrosAmbientes = $derived(
    ambientesDisponibles($page.props.auth, can).filter((a) => a !== 'estudiante'),
  );

  const url = $derived($page.url ?? '');
  const enInicio = $derived(url.startsWith('/estudiante/dashboard'));
  const enMisCursos = $derived(url === '/estudiante/cursos' || url.startsWith('/estudiante/cursos?'));

  function esActivo(curso: SidebarCourse): boolean {
    const ruta = `/estudiante/cursos/${curso.id_curso}`;
    return url === ruta || ['/', '?', '#'].some((c) => url.startsWith(ruta + c));
  }

  /** La etiqueta termina en «Grupo A»; en el menú basta el nombre del curso. */
  function nombreSinGrupo(curso: SidebarCourse): string {
    const base = curso.etiqueta ?? curso.nombre;
    return curso.letra_grupo ? base.replace(new RegExp(`\\s+Grupo\\s+${curso.letra_grupo}$`), '') : base;
  }

  const usuario = $derived($page.props.auth?.user);
  const nombre = $derived(
    titleCase(
      usuario?.nombre1 && usuario?.apellido1
        ? `${usuario.nombre1} ${usuario.apellido1}`
        : usuario?.nombre1 || usuario?.username || 'Estudiante',
    ),
  );
  const foto = $derived($page.props.auth?.urlFotoPerfil as string | undefined);
  let fotoFallo = $state(false);

  function cerrarSesion() {
    router.flushAll();
    router.post(logout.url());
  }

  const fila =
    'relative flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm no-underline transition-colors lg:min-h-10';
  const filaInactiva = 'text-dm-noche-texto-2 hover:bg-dm-noche-activo hover:text-dm-noche-texto';
  const filaActiva = 'bg-dm-noche-activo font-semibold text-dm-noche-texto';
</script>

{#snippet marcaActiva()}
  <span aria-hidden="true" class="absolute top-2 bottom-2 left-0 w-[3px] rounded-sm bg-dm-amarillo"></span>
{/snippet}

<nav
  aria-label="Menú principal"
  class="sobre-noche flex h-full flex-col gap-6 overflow-y-auto border-r border-dm-noche-borde bg-dm-noche px-3 py-5 font-dm"
>
  <Link href="/estudiante/dashboard" class="flex items-center gap-2.5 rounded-lg px-3 py-1 no-underline">
    <img src="/icon.png" alt="" width="32" height="32" class="size-8 shrink-0 object-contain" />
    <span class="font-dm-cond text-2xl leading-7 font-extrabold tracking-[0.02em] text-dm-noche-texto">UTAMED</span>
  </Link>

  <div class="flex flex-col gap-1">
    <Link
      href="/estudiante/dashboard"
      class="{fila} {enInicio ? filaActiva : filaInactiva}"
      aria-current={enInicio ? 'page' : undefined}
    >
      {#if enInicio}{@render marcaActiva()}{/if}
      <House size={20} aria-hidden="true" class="shrink-0" />
      Inicio
    </Link>
  </div>

  <div class="flex flex-col gap-1">
    <span class="px-3 pb-1 text-xs leading-4 font-bold tracking-[0.06em] text-dm-noche-texto-2 uppercase">
      Mis cursos
    </span>
    {#each vigentes as curso (curso.id_curso)}
      {@const activo = esActivo(curso)}
      <Link
        href="/estudiante/cursos/{curso.id_curso}"
        class="{fila} {activo ? filaActiva : filaInactiva}"
        style={estiloCurso(numeroColorCurso(curso.id_curso, cursos))}
        aria-current={activo ? 'page' : undefined}
        title={curso.etiqueta ?? curso.nombre}
      >
        {#if activo}{@render marcaActiva()}{/if}
        <span aria-hidden="true" class="size-2.5 shrink-0 rounded-full bg-(--curso-m)"></span>
        <span class="truncate">{nombreSinGrupo(curso)}</span>
      </Link>
    {:else}
      <p class="px-3 py-1 text-sm text-dm-noche-texto-2">Sin cursos inscritos este semestre</p>
    {/each}
    <Link
      href="/estudiante/cursos"
      class="{fila} font-semibold {enMisCursos ? filaActiva : filaInactiva}"
      aria-current={enMisCursos ? 'page' : undefined}
    >
      {#if enMisCursos}{@render marcaActiva()}{/if}
      Ver todos los cursos
      <ChevronRight size={16} aria-hidden="true" class="shrink-0" />
    </Link>
  </div>

  <div class="mt-auto flex flex-col gap-4">
    {#if otrosAmbientes.length > 0}
      <div class="flex flex-col gap-1">
        <span class="px-3 pb-1 text-xs leading-4 font-bold tracking-[0.06em] text-dm-noche-texto-2 uppercase">
          Otros ambientes
        </span>
        {#each otrosAmbientes as id (id)}
          {@const ambiente = AMBIENTES[id]}
          <Link href={ambiente.href} class="{fila} {filaInactiva}">
            <ambiente.icon size={18} aria-hidden="true" class="shrink-0" />
            {ambiente.label}
          </Link>
        {/each}
      </div>
    {/if}

    <div class="flex items-center gap-2 border-t border-dm-noche-linea pt-3">
      <Link
        href="/estudiante/perfil"
        class="flex min-h-11 min-w-0 flex-1 items-center gap-3 rounded-lg px-2 no-underline hover:bg-dm-noche-activo"
      >
        <span
          aria-hidden="true"
          class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-dm-noche-activo text-[13px] font-bold text-dm-noche-texto"
        >
          {#if foto && !fotoFallo}
            <img src={foto} alt="" class="size-full object-cover" onerror={() => (fotoFallo = true)} />
          {:else}
            {getInitials(nombre)}
          {/if}
        </span>
        <span class="flex min-w-0 flex-col">
          <span class="truncate text-sm font-semibold text-dm-noche-texto">{nombre}</span>
          <span class="text-[13px] leading-[18px] text-dm-noche-texto-2">Estudiante · Ver perfil</span>
        </span>
      </Link>
      <button
        type="button"
        onclick={cerrarSesion}
        aria-label="Cerrar sesión"
        title="Cerrar sesión"
        class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-dm-noche-texto-2 transition-colors hover:bg-dm-noche-activo hover:text-dm-noche-texto"
      >
        <LogOut size={18} aria-hidden="true" />
      </button>
    </div>
  </div>
</nav>
