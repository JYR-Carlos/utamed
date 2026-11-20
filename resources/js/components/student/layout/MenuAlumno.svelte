<script lang="ts">
  /**
   * Menú lateral del alumno (guía dm): noche en claro y en oscuro.
   *
   * Inicio, los cursos del semestre vigente con su color y el acceso a todos
   * los cursos (los de semestres anteriores están ahí). Abajo, otros ambientes
   * si la cuenta tiene más de un rol, y la persona con su salida.
   * Lo usan la columna de escritorio y el cajón móvil de StudentLayout.
   *
   * Plegado (sólo escritorio) queda en una columna de íconos: los cursos
   * muestran su código y cada fila conserva su nombre para lectores de
   * pantalla y en el texto emergente.
   */
  import { Link, page, router } from '@inertiajs/svelte';
  import { CalendarDays, ChevronRight, House, LayoutGrid, LogOut, MessageSquare } from 'lucide-svelte';
  import { logout } from '@/routes';
  import { titleCase } from '@/lib/utils';
  import { usePermissions } from '@/lib/composables/usePermissions';
  import { AMBIENTES, ambientesDisponibles } from '@/lib/ambientes';
  import { cursosDelSemestreVigente, estiloCurso, numeroColorCurso } from '@/lib/colorCurso';
  import type { SidebarCourse } from '@/types';
  import FotoPerfil from '@/components/student/dm/FotoPerfil.svelte';

  let { plegado = false }: { plegado?: boolean } = $props();

  const { can } = usePermissions();

  const cursos = $derived(($page.props.auth?.estudiante_courses as SidebarCourse[]) ?? []);

  // Semestre vigente = el más reciente con cursos; los anteriores quedan en «Mis cursos».
  const vigentes = $derived(cursosDelSemestreVigente(cursos));

  const otrosAmbientes = $derived(
    ambientesDisponibles($page.props.auth, can).filter((a) => a !== 'estudiante'),
  );

  const url = $derived($page.url ?? '');
  const enInicio = $derived(url.startsWith('/estudiante/dashboard'));
  const enCalendario = $derived(url.startsWith('/estudiante/calendario'));
  const enMensajes = $derived(url.startsWith('/estudiante/mensajes'));
  const sinLeerBuzon = $derived(Number($page.props.auth?.estudiante_buzon_no_leidos ?? 0));
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

  function cerrarSesion() {
    router.flushAll();
    router.post(logout.url());
  }

  const fila = $derived(
    `relative flex min-h-11 items-center rounded-lg py-2 text-sm no-underline transition-colors lg:min-h-10 ${
      plegado ? 'justify-center px-1' : 'gap-3 px-3'
    }`,
  );
  /** Texto de la fila: plegado sólo lo leen los lectores de pantalla. */
  const texto = $derived(plegado ? 'sr-only' : '');
  const codigoCurso = (c: SidebarCourse) => c.cod_asignatura || c.cod_curso;
  const filaInactiva = 'text-dm-noche-texto-2 hover:bg-dm-noche-activo hover:text-dm-noche-texto';
  const filaActiva = 'bg-dm-noche-activo font-semibold text-dm-noche-texto';
</script>

{#snippet marcaActiva()}
  <span aria-hidden="true" class="absolute top-2 bottom-2 left-0 w-[3px] rounded-sm bg-dm-amarillo"></span>
{/snippet}

<nav
  aria-label="Menú principal"
  class="sobre-noche flex h-full flex-col gap-6 overflow-x-hidden overflow-y-auto border-r border-dm-noche-borde bg-dm-lat py-5 font-dm {plegado
    ? 'px-2'
    : 'px-3'}"
>
  <Link
    href="/estudiante/dashboard"
    class="flex items-center gap-2.5 rounded-lg py-1 no-underline {plegado ? 'justify-center' : 'px-3'}"
  >
    <img src="/icon.png" alt="" width="32" height="32" class="size-8 shrink-0 object-contain" />
    <span class="font-dm-cond text-2xl leading-7 font-extrabold tracking-[0.02em] text-dm-noche-texto {texto}">UTAMED</span>
  </Link>

  <div class="flex flex-col gap-1">
    <Link
      href="/estudiante/dashboard"
      class="{fila} {enInicio ? filaActiva : filaInactiva}"
      aria-current={enInicio ? 'page' : undefined}
      title={plegado ? 'Inicio' : undefined}
    >
      {#if enInicio}{@render marcaActiva()}{/if}
      <House size={20} aria-hidden="true" class="shrink-0" />
      <span class={texto}>Inicio</span>
    </Link>
    <!-- Las fechas de todos los cursos del semestre (OBS-23). -->
    <Link
      href="/estudiante/calendario"
      class="{fila} {enCalendario ? filaActiva : filaInactiva}"
      aria-current={enCalendario ? 'page' : undefined}
      title={plegado ? 'Calendario' : undefined}
    >
      {#if enCalendario}{@render marcaActiva()}{/if}
      <CalendarDays size={20} aria-hidden="true" class="shrink-0" />
      <span class={texto}>Calendario</span>
    </Link>
    <!-- Buzón (OBS-48): la mensajería de los ramos y la agenda de las actividades en una lista. -->
    <Link
      href="/estudiante/mensajes"
      class="{fila} {enMensajes ? filaActiva : filaInactiva}"
      aria-current={enMensajes ? 'page' : undefined}
      title={plegado ? (sinLeerBuzon > 0 ? `Mensajes (${sinLeerBuzon} sin leer)` : 'Mensajes') : undefined}
    >
      {#if enMensajes}{@render marcaActiva()}{/if}
      <span class="relative shrink-0">
        <MessageSquare size={20} aria-hidden="true" />
        {#if plegado && sinLeerBuzon > 0}
          <span aria-hidden="true" class="absolute -top-1 -right-1 size-2.5 rounded-full bg-dm-amarillo ring-2 ring-dm-lat"></span>
        {/if}
      </span>
      <span class={texto}>Mensajes</span>
      {#if sinLeerBuzon > 0}
        <span class="{plegado ? 'sr-only' : 'ml-auto'} min-w-6 rounded-full bg-dm-amarillo px-1.5 text-center text-xs leading-5 font-bold text-dm-noche"
          >{sinLeerBuzon}<span class="sr-only">{' '}sin leer</span></span
        >
      {/if}
    </Link>
  </div>

  <div class="flex flex-col gap-1">
    <span
      class="pb-1 text-xs leading-4 font-bold tracking-[0.06em] text-dm-noche-texto-2 uppercase {plegado
        ? 'sr-only'
        : 'px-3'}"
    >
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
        {#if plegado}
          <span aria-hidden="true" class="flex flex-col items-center gap-1">
            <span class="size-2 rounded-full bg-(--curso-m)"></span>
            <span class="font-dm-cond text-xs leading-none font-bold tracking-[0.02em]">{codigoCurso(curso)}</span>
          </span>
          <span class="sr-only">{nombreSinGrupo(curso)}</span>
        {:else}
          <span aria-hidden="true" class="size-2.5 shrink-0 rounded-full bg-(--curso-m)"></span>
          <span class="truncate">{nombreSinGrupo(curso)}</span>
        {/if}
      </Link>
    {:else}
      <p class="py-1 text-sm text-dm-noche-texto-2 {plegado ? 'sr-only' : 'px-3'}">Sin cursos inscritos este semestre</p>
    {/each}
    <Link
      href="/estudiante/cursos"
      class="{fila} font-semibold {enMisCursos ? filaActiva : filaInactiva}"
      aria-current={enMisCursos ? 'page' : undefined}
      title={plegado ? 'Ver todos los cursos' : undefined}
    >
      {#if enMisCursos}{@render marcaActiva()}{/if}
      {#if plegado}<LayoutGrid size={20} aria-hidden="true" class="shrink-0" />{/if}
      <span class={texto}>Ver todos los cursos</span>
      {#if !plegado}<ChevronRight size={16} aria-hidden="true" class="shrink-0" />{/if}
    </Link>
  </div>

  <div class="mt-auto flex flex-col gap-4">
    {#if otrosAmbientes.length > 0}
      <div class="flex flex-col gap-1">
        <span
          class="pb-1 text-xs leading-4 font-bold tracking-[0.06em] text-dm-noche-texto-2 uppercase {plegado
            ? 'sr-only'
            : 'px-3'}"
        >
          Otros ambientes
        </span>
        {#each otrosAmbientes as id (id)}
          {@const ambiente = AMBIENTES[id]}
          <Link href={ambiente.href} class="{fila} {filaInactiva}" title={plegado ? ambiente.label : undefined}>
            <ambiente.icon size={18} aria-hidden="true" class="shrink-0" />
            <span class={texto}>{ambiente.label}</span>
          </Link>
        {/each}
      </div>
    {/if}

    <div class="flex items-center gap-2 border-t border-dm-noche-linea pt-3 {plegado ? 'flex-col' : ''}">
      <Link
        href="/estudiante/perfil"
        title={plegado ? 'Mi perfil' : undefined}
        class="flex min-h-11 min-w-0 items-center gap-3 rounded-lg no-underline hover:bg-dm-noche-activo {plegado
          ? 'justify-center px-1'
          : 'flex-1 px-2'}"
      >
        <FotoPerfil src={foto} decorativa class="size-8 rounded-full bg-dm-noche-activo" />
        <span class="flex min-w-0 flex-col {texto}">
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
