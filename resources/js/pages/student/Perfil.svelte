<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { useInitials } from '@/hooks';
  import {
    IdCard,
    Mail,
    AtSign,
    GraduationCap,
    CalendarDays,
    BookOpen,
    KeyRound,
    Lock,
    SquarePen,
    CheckCircle2,
  } from 'lucide-svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import InputError from '@/components/custom/common/InputError.svelte';
  import { Input } from '@/components/ui/input';
  import { Label } from '@/components/ui/label';

  type Red = 'youtube' | 'x' | 'instagram' | 'linkedin';

  interface Props {
    perfil: {
      nombre_completo: string;
      rut: string;
      email: string;
      username: string;
      carrera_nombre: string;
      agno_ingreso: number;
      total_cursos: number;
    };
    /** Contacto personal que el alumno mantiene (T10). */
    urlImagenPerfil: string;
    contacto: {
      correo_personal: string | null;
      celular: string | null;
      redes_sociales: Record<Red, string | null>;
    };
    semestreActual: number;
  }

  let { perfil, urlImagenPerfil, contacto, semestreActual }: Props = $props();

  const REDES: { id: Red; label: string; placeholder: string }[] = [
    { id: 'youtube', label: 'YouTube', placeholder: 'https://www.youtube.com/@tu-canal' },
    { id: 'x', label: 'X (Twitter)', placeholder: 'https://x.com/tu-usuario' },
    { id: 'instagram', label: 'Instagram', placeholder: 'https://www.instagram.com/tu-usuario' },
    { id: 'linkedin', label: 'LinkedIn', placeholder: 'https://www.linkedin.com/in/tu-perfil' },
  ];

  // svelte-ignore state_referenced_locally
  const form = useForm({
    correo_personal: contacto?.correo_personal ?? '',
    celular: contacto?.celular ?? '',
    redes_sociales: {
      youtube: contacto?.redes_sociales?.youtube ?? '',
      x: contacto?.redes_sociales?.x ?? '',
      instagram: contacto?.redes_sociales?.instagram ?? '',
      linkedin: contacto?.redes_sociales?.linkedin ?? '',
    } as Record<Red, string>,
  });

  let guardado = $state(false);

  function guardarContacto(e: SubmitEvent) {
    e.preventDefault();
    guardado = false;
    $form.patch('/estudiante/perfil/contacto', {
      preserveScroll: true,
      onSuccess: () => (guardado = true),
    });
  }

  const errores = $derived($form.errors as Record<string, string | undefined>);

  const { getInitials } = useInitials();
  const anoAcademico = new Date().getFullYear();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inicio', href: '/estudiante/dashboard' },
    { title: 'Mi Perfil', href: '/estudiante/perfil' },
  ];

  let imagenError = $state(false);
  let cambiarFotoPerfilModal = $state(false);
</script>

<StudentLayout {breadcrumbs}>
  <div class="h-full px-5 md:px-10 lg:px-20 bg-white relative">
    <div class="relative mx-auto max-w-4xl px-4 py-6">
      <header class="flex flex-col gap-1 mb-8">
        <!--
        <span
          class="inline-flex items-center gap-1.5 text-xs font-bold text-uta-blue bg-uta-blue-light border border-uta-blue/20 rounded-full px-3 py-0.5 w-fit"
        >
          Semestre {semestreActual} · {anoAcademico}
        </span>
      -->
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
          Mi Perfil
        </h1>
        <p class="text-sm text-slate-500">Tus datos como estudiante en UTAmed.</p>
      </header>

      <section
        class="flex flex-wrap justify-center sm:justify-between items-center gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-6"
      >
        <div class="flex flex-col sm:flex-row gap-5 justify-center items-center text-center">
          <div
            class="flex h-40 w-40 shrink-0 items-center justify-center overflow-hidden rounded-full bg-uta-blue-light text-lg font-bold text-uta-blue border-uta-blue"
          >
            {#if urlImagenPerfil && !imagenError}
              <img
                src={urlImagenPerfil}
                alt="Foto de perfil"
                class="h-full w-full object-cover"
                onerror={() => (imagenError = true)}
              />
            {:else}
              <img
                src="/img/usuarioutamed.png"
                alt="Foto de perfil genérica"
                class="object-cover"
              />
            {/if}
          </div>
          <div class="flex flex-col gap-0.5 px-7">
            <span class="text-lg font-semibold tracking-tight text-slate-900"
              >{perfil.nombre_completo}</span
            >
            <span class="text-sm text-slate-500">{perfil.carrera_nombre}</span>
          </div>
        </div>

        <div class="flex flex-col gap-5 justify-center items-center text-center">
          <Link
            href="/settings/password"
            class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-uta-blue/30 hover:bg-uta-blue-light hover:text-uta-blue"
          >
            <KeyRound class="h-4 w-4" />
            Cambiar contraseña
          </Link>
          <button
            class="ml-auto inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-uta-blue/30 hover:bg-uta-blue-light hover:text-uta-blue"
            onclick={() => {
              cambiarFotoPerfilModal = !cambiarFotoPerfilModal;
            }}
          >
            <SquarePen class="h-4 w-4" />
            Cambiar Foto de Perfil
          </button>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div
          class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light"
          >
            <IdCard class="h-4.5 w-4.5 text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">RUT</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.rut}</span>
          </div>
        </div>

        <div
          class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light"
          >
            <Mail class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Correo institucional</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.email}</span>
          </div>
        </div>

        <div
          class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light"
          >
            <AtSign class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Usuario</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.username}</span>
          </div>
        </div>

        <div
          class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light"
          >
            <GraduationCap class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Carrera</span>
            <span class="truncate text-sm font-semibold text-slate-900"
              >{perfil.carrera_nombre}</span
            >
          </div>
        </div>

        <div
          class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light"
          >
            <CalendarDays class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Año de ingreso</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.agno_ingreso}</span>
          </div>
        </div>

        <div
          class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light"
          >
            <BookOpen class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Cursos este período</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.total_cursos}</span>
          </div>
        </div>
      </section>

      <p class="mt-3 inline-flex items-center gap-1.5 text-[12.5px] text-slate-500">
        <Lock class="h-3.5 w-3.5" />
        Estos datos vienen de la Intranet y no se pueden editar aquí.
      </p>

      <!-- Contacto personal (T10): lo único que el alumno edita. -->
      <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex flex-col gap-1">
          <h2 class="text-base font-semibold text-slate-900">Datos de contacto</h2>
          <p class="text-[13px] text-slate-500">
            Sólo tus docentes pueden ver estos datos; tus compañeros no. Todos son opcionales.
          </p>
        </div>

        <form class="flex flex-col gap-5" onsubmit={guardarContacto}>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
              <Label for="correo_personal">Correo personal</Label>
              <Input
                id="correo_personal"
                type="email"
                autocomplete="email"
                placeholder="tu.correo@ejemplo.com"
                bind:value={$form.correo_personal}
              />
              <InputError message={errores.correo_personal} />
            </div>

            <div class="grid gap-2">
              <Label for="celular">Celular</Label>
              <Input
                id="celular"
                type="tel"
                autocomplete="tel"
                placeholder="+56 9 1234 5678"
                bind:value={$form.celular}
              />
              <InputError message={errores.celular} />
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {#each REDES as red (red.id)}
              <div class="grid gap-2">
                <Label for="red_{red.id}">{red.label}</Label>
                <Input
                  id="red_{red.id}"
                  type="url"
                  placeholder={red.placeholder}
                  bind:value={$form.redes_sociales[red.id]}
                />
                <InputError message={errores[`redes_sociales.${red.id}`]} />
              </div>
            {/each}
          </div>
          <InputError message={errores.redes_sociales} />

          <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="btn btn-primary" disabled={$form.processing}>
              Guardar contacto
            </button>
            {#if guardado}
              <p
                class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700"
                role="status"
              >
                <CheckCircle2 class="h-4 w-4" />
                Tus datos de contacto se guardaron.
              </p>
            {/if}
          </div>
        </form>
      </section>
    </div>
  </div>
</StudentLayout>

{#if cambiarFotoPerfilModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- Overlay -->
    <button
      type="button"
      aria-label="Cerrar modal"
      class="absolute inset-0 cursor-default bg-black/50"
      onclick={() => {
        cambiarFotoPerfilModal = false
      }}
    ></button>

    <!-- Modal -->
    <div class="relative z-10 mx-4 w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5">
        <h2 class="text-lg font-semibold text-gray-900">
          Cambio de Foto de Perfil
        </h2>
      </div>

      <!-- Content -->
      <div class="px-6 py-8 text-center">
        <p class="text-sm font-medium leading-6 text-gray-700">
          Para poder cambiar tu foto de perfil, debes hacerlo desde la Intranet.
        </p>
      </div>

      <!-- Footer -->
      <div class="flex flex-col-reverse gap-3 border-t border-gray-100 px-6 py-5 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-uta-blue/30 hover:bg-uta-blue-light hover:text-uta-blue"
          onclick={() => {
            cambiarFotoPerfilModal = false
          }}
        >
          Aceptar
        </button>

        <a
          href="https://portal.uta.cl/"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center justify-center gap-2 rounded-lg bg-yellow-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-yellow-600"
        >
          Ir a Intranet
        </a>
      </div>
    </div>
  </div>
{/if}