<script lang="ts">
  /**
   * Menú del docente (dirección institucional UTA).
   *
   * Arriba los destinos generales; después los cursos del semestre vigente
   * (los anteriores están en «Ver todos los cursos») y al pie «Mi perfil» con
   * la puerta para cerrar sesión al lado, como en el menú del estudiante.
   * El activo lleva fondo dorado claro y una barra de oro de 3 px. El curso
   * abierto despliega debajo sus seis secciones (las mismas pestañas de su
   * cabecera).
   * Lo usan la columna de escritorio y el panel desplegable del teléfono;
   * en el teléfono suma el cambio de rol y «Reportar error», que en
   * escritorio están en la barra superior.
   */
  import { Link, page } from '@inertiajs/svelte';
  import { Calendar, ChevronRight, ClipboardCheck, House, LogOut, MessageSquare, User, UserCheck } from 'lucide-svelte';
  import IssueReport from '@/components/custom/IssueReport.svelte';
  import { cerrarSesion } from '@/lib/sesion';
  import { usePermissions } from '@/lib/composables/usePermissions';
  import { AMBIENTES, ambientesDisponibles } from '@/lib/ambientes';
  import { etiqueta, codigo } from '@/lib/inst';
  import { nombreSemestre, seccionDeUrl, seccionesCurso } from '@/lib/cursoDocente';
  import type { SidebarCourse } from '@/types';

  interface Props {
    movil?: boolean;
  }

  let { movil = false }: Props = $props();

  const { can } = usePermissions();

  const destinos = [
    { href: '/docente/dashboard', label: 'Inicio', icon: House },
    { href: '/docente/calendario', label: 'Calendario', icon: Calendar },
    // «Mensajes para» (cursos); la bandeja de actividades cuelga de ella.
    { href: '/docente/mensajeria', label: 'Mensajes', icon: MessageSquare, tambien: '/docente/mensajes' },
    { href: '/docente/calificaciones', label: 'Calificaciones', icon: ClipboardCheck },
    { href: '/docente/asistencia', label: 'Asistencia', icon: UserCheck },
  ];

  const cursos = $derived(($page.props.auth?.docente_courses as SidebarCourse[]) ?? []);

  // Semestre vigente = el más reciente con cursos; los anteriores quedan en «Mis cursos».
  const vigente = $derived.by(() => {
    const conPeriodo = cursos.filter((c) => c.agno_real != null && c.semestre_real != null);
    if (conPeriodo.length === 0) return null;
    const ultimo = conPeriodo.reduce((a, c) =>
      c.agno_real > a.agno_real || (c.agno_real === a.agno_real && c.semestre_real > a.semestre_real) ? c : a,
    );
    return {
      periodo: nombreSemestre(ultimo.semestre_real, ultimo.agno_real),
      cursos: conPeriodo.filter((c) => c.agno_real === ultimo.agno_real && c.semestre_real === ultimo.semestre_real),
    };
  });

  const otrosAmbientes = $derived(ambientesDisponibles($page.props.auth, can).filter((a) => a !== 'docente'));

  const url = $derived($page.url ?? '');

  function enRuta(ruta: string): boolean {
    return url === ruta || ['/', '?', '#'].some((c) => url.startsWith(ruta + c));
  }

  const enMisCursos = $derived(url === '/docente/cursos' || url.startsWith('/docente/cursos?'));
  const enPerfil = $derived(enRuta('/docente/perfil'));

  /** La etiqueta termina en «Grupo A»; el grupo ya va junto al código. */
  function nombreSinGrupo(curso: SidebarCourse): string {
    const base = curso.etiqueta ?? curso.nombre;
    return curso.letra_grupo ? base.replace(new RegExp(`\\s+Grupo\\s+${curso.letra_grupo}$`), '') : base;
  }

  function codigoCurso(curso: SidebarCourse): string {
    const cod = curso.cod_asignatura || String(curso.cod_curso ?? '');
    return curso.letra_grupo ? `${cod} · ${curso.letra_grupo}` : cod;
  }

  const fila =
    'relative flex min-h-11 items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-inst-ink no-underline transition-colors hover:bg-inst-sutil hover:text-inst-ink lg:min-h-10';
  const filaActiva = 'bg-inst-sel font-extrabold hover:bg-inst-sel';
  // Curso: código arriba y nombre abajo, sin el espacio entre ícono y texto de `fila`.
  const filaCurso =
    'relative flex min-h-11 flex-col justify-center rounded-md px-3 py-1.5 font-medium text-inst-ink no-underline transition-colors hover:bg-inst-sutil hover:text-inst-ink';
</script>

{#snippet marcaActiva()}
  <span aria-hidden="true" class="absolute top-2 bottom-2 left-0 w-[3px] rounded-sm bg-inst-oro"></span>
{/snippet}

<nav
  id={movil ? 'menu-docente-movil' : undefined}
  aria-label="Menú del docente"
  class="flex flex-col gap-5 bg-inst-tarjeta px-3 py-5 font-inst {movil
    ? 'border-b border-inst-linea py-3'
    : 'h-full overflow-y-auto border-r border-inst-linea'}"
>
  {#if movil && (otrosAmbientes.length > 0)}
    <div class="flex flex-col gap-0.5">
      <span class="{etiqueta} px-3 pb-1.5">Cambiar de rol</span>
      {#each otrosAmbientes as id (id)}
        {@const ambiente = AMBIENTES[id]}
        <Link href={ambiente.href} class={fila}>
          <ambiente.icon size={18} aria-hidden="true" class="shrink-0 text-inst-ink-2" />
          {ambiente.label}
        </Link>
      {/each}
    </div>
  {/if}

  <div class="flex flex-col gap-0.5">
    {#each destinos as destino (destino.href)}
      {@const activo = enRuta(destino.href) || (!!destino.tambien && enRuta(destino.tambien))}
      <Link href={destino.href} class="{fila} {activo ? filaActiva : ''}" aria-current={activo ? 'page' : undefined}>
        {#if activo}{@render marcaActiva()}{/if}
        <destino.icon size={18} aria-hidden="true" class="shrink-0 text-inst-ink-2" />
        {destino.label}
      </Link>
    {/each}
  </div>

  <div class="flex flex-col gap-0.5">
    <span class="flex flex-col gap-0.5 px-3 pb-1.5">
      <span class={etiqueta}>Mis cursos</span>
      {#if vigente}<span class="text-xs leading-4 font-semibold text-inst-ink-2">{vigente.periodo}</span>{/if}
    </span>
    {#each vigente?.cursos ?? [] as curso (curso.id_curso)}
      {@const seccion = seccionDeUrl(url, curso.id_curso)}
      <Link
        href="/docente/cursos/{curso.id_curso}"
        class="{filaCurso} {seccion ? 'bg-inst-sutil' : ''}"
        aria-current={seccion ? 'location' : undefined}
        title={curso.etiqueta ?? curso.nombre}
      >
        <span class="{codigo} tracking-[0.04em]">{codigoCurso(curso)}</span>
        <span class="truncate text-[13px] leading-[18px]">{nombreSinGrupo(curso)}</span>
      </Link>
      {#if seccion}
        <div class="mt-0.5 mb-1.5 ml-4 flex flex-col gap-0.5 border-l-2 border-inst-linea">
          {#each seccionesCurso(curso.id_curso) as s (s.id)}
            {@const activa = s.id === seccion}
            <Link
              href={s.href}
              class="relative flex min-h-11 items-center rounded-md py-1.5 pr-3 pl-3.5 text-[13px] font-semibold text-inst-ink no-underline transition-colors hover:bg-inst-sutil hover:text-inst-ink lg:min-h-9 {activa
                ? 'bg-inst-sel font-extrabold hover:bg-inst-sel'
                : ''}"
              aria-current={activa ? 'page' : undefined}
            >
              {#if activa}{@render marcaActiva()}{/if}
              {s.label}
            </Link>
          {/each}
        </div>
      {/if}
    {:else}
      <p class="px-3 py-1 text-[13px] text-inst-ink-2">Sin cursos asignados este semestre</p>
    {/each}
    <Link
      href="/docente/cursos"
      class="{fila} text-[13px] font-bold {enMisCursos ? filaActiva : ''}"
      aria-current={enMisCursos ? 'page' : undefined}
    >
      {#if enMisCursos}{@render marcaActiva()}{/if}
      Ver todos los cursos
      <ChevronRight size={15} aria-hidden="true" class="shrink-0" />
    </Link>
  </div>

  <div class="mt-auto flex flex-col gap-2 border-t border-inst-linea pt-2">
    {#if movil}
      <IssueReport etiqueta="Sugerencias" class="min-h-11 w-full justify-start gap-3 rounded-md border-0 bg-transparent px-3 text-sm font-medium text-inst-ink hover:bg-inst-sutil max-sm:size-auto max-sm:px-3 [&>span]:inline" />
    {/if}
    <div class="flex items-center gap-1">
      <Link
        href="/docente/perfil"
        class="{fila} min-w-0 flex-1 {enPerfil ? filaActiva : ''}"
        aria-current={enPerfil ? 'page' : undefined}
      >
        {#if enPerfil}{@render marcaActiva()}{/if}
        <User size={18} aria-hidden="true" class="shrink-0 text-inst-ink-2" />
        Mi perfil
      </Link>
      <button
        type="button"
        onclick={cerrarSesion}
        aria-label="Cerrar sesión"
        title="Cerrar sesión"
        class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-md text-inst-ink-2 transition-colors hover:bg-inst-sutil hover:text-inst-ink lg:size-10"
      >
        <LogOut size={18} aria-hidden="true" />
      </button>
    </div>
  </div>
</nav>
