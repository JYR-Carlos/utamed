<script lang="ts">
  /**
   * Barra superior del docente (dirección institucional UTA): azul marino en
   * ambos temas, con la marca UTAMED sobre placa blanca.
   *
   * Escritorio: marca, «Sugerencias» (el reporte de problemas), cambio de rol
   * (si la cuenta tiene más de uno), el interruptor claro/oscuro y la persona con su menú. Teléfono: botón del menú, marca,
   * tema y persona; el cambio de rol baja al menú desplegable.
   */
  import { Link, page, router } from '@inertiajs/svelte';
  import { ChevronDown, Menu, X } from 'lucide-svelte';
  import IssueReport from '@/components/custom/IssueReport.svelte';
  import BotonTema from '@/components/ingreso/BotonTema.svelte';
  import UserMenuContent from '@/components/custom/common/UserMenuContent.svelte';
  import FotoPerfil from '@/components/student/dm/FotoPerfil.svelte';
  import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
  } from '@/components/ui/dropdown-menu';
  import { usePermissions } from '@/lib/composables/usePermissions';
  import { AMBIENTES, ambientesDisponibles } from '@/lib/ambientes';
  import { titleCase } from '@/lib/utils';

  interface Props {
    menuAbierto: boolean;
    onAlternarMenu: () => void;
  }

  let { menuAbierto, onAlternarMenu }: Props = $props();

  const { can } = usePermissions();

  const ambientes = $derived(ambientesDisponibles($page.props.auth, can));

  const usuario = $derived($page.props.auth.user);
  const urlFotoPerfil = $derived($page.props.auth?.urlFotoPerfil as string | undefined);
  const nombre = $derived(
    titleCase(
      usuario?.nombre1 && usuario?.apellido1
        ? `${usuario.nombre1} ${usuario.apellido1}`
        : usuario?.nombre1 || usuario?.username || 'Docente',
    ),
  );

  const botonBarra =
    'flex min-h-11 min-w-11 shrink-0 cursor-pointer items-center justify-center gap-2 rounded-md border border-inst-barra-borde bg-transparent px-3 text-[13px] font-bold text-inst-barra-texto transition-colors hover:bg-white/10 lg:min-h-9 lg:min-w-9';
</script>

<header
  class="sobre-barra flex min-h-14 shrink-0 items-center gap-2.5 bg-inst-barra px-3 py-1.5 text-inst-barra-texto lg:min-h-16 lg:gap-5 lg:px-6 lg:py-2.5"
>
  <button
    type="button"
    onclick={onAlternarMenu}
    aria-expanded={menuAbierto}
    aria-controls="menu-docente-movil"
    aria-label={menuAbierto ? 'Cerrar el menú' : 'Abrir el menú'}
    class="{botonBarra} px-0 lg:hidden"
  >
    {#if menuAbierto}
      <X size={20} aria-hidden="true" />
    {:else}
      <Menu size={20} aria-hidden="true" />
    {/if}
  </button>

  <Link href="/docente/dashboard" class="flex min-h-11 min-w-0 items-center gap-2 text-inst-barra-texto no-underline hover:text-inst-barra-texto lg:gap-3">
    <span aria-hidden="true" class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white lg:size-10">
      <img src="/icon.png" alt="" width="30" height="30" class="size-6.5 object-contain lg:size-7.5" />
    </span>
    <span class="flex min-w-0 flex-col">
      <span class="font-inst-titulo text-base leading-[22px] font-bold tracking-[0.08em] lg:text-lg">UTAMED</span>
      <span class="hidden text-xs leading-4 text-inst-barra-texto-2 lg:block">Entorno virtual de enseñanza y aprendizaje</span>
    </span>
  </Link>

  <div class="ml-auto flex items-center gap-2.5">
    <IssueReport class="{botonBarra} max-lg:hidden" />

    {#if ambientes.length > 1}
      <DropdownMenu>
        <DropdownMenuTrigger class="{botonBarra} max-lg:hidden">
          Rol: {AMBIENTES.docente.label}
          <ChevronDown size={16} aria-hidden="true" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-60">
          <DropdownMenuLabel class="text-xs font-bold tracking-[0.06em] uppercase">Cambiar de rol</DropdownMenuLabel>
          <DropdownMenuSeparator />
          {#each ambientes as id (id)}
            {@const ambiente = AMBIENTES[id]}
            <DropdownMenuItem
              onSelect={() => router.visit(ambiente.href)}
              class="min-h-11 cursor-pointer gap-2.5 font-semibold {id === 'docente' ? 'bg-inst-sel' : ''}"
            >
              <ambiente.icon size={16} aria-hidden="true" />
              {ambiente.label}
            </DropdownMenuItem>
          {/each}
        </DropdownMenuContent>
      </DropdownMenu>
    {/if}

    <!-- El mismo interruptor sol/luna del alumno: claro u oscuro en un clic. -->
    <BotonTema class="border border-inst-barra-borde text-inst-barra-texto hover:bg-white/10 lg:h-9" />

    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label="Menú de {nombre}"
        class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-md text-left text-inst-barra-texto lg:border-l lg:border-white/20 lg:pl-3"
      >
        <FotoPerfil src={urlFotoPerfil} decorativa centrada class="size-8.5 rounded-full border border-white/25 bg-inst-oro" />
        <span class="hidden min-w-0 flex-col lg:flex">
          <span class="max-w-48 truncate text-sm leading-[18px] font-bold">{nombre}</span>
          <span class="text-xs leading-4 text-inst-barra-texto-2">Docente</span>
        </span>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" class="w-56">
        <UserMenuContent user={usuario} />
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</header>
