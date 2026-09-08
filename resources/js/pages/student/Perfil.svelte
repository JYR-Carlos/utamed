<script lang="ts">
  import StudentLayout from '@/layouts/StudentLayout.svelte';
  import type { BreadcrumbItem } from '@/types';
  import { useInitials } from '@/hooks';
  import { IdCard, Mail, AtSign, GraduationCap, CalendarDays, BookOpen } from 'lucide-svelte';

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
    semestreActual: number;
  }

  let { perfil, semestreActual }: Props = $props();

  const { getInitials } = useInitials();
  const anoAcademico = new Date().getFullYear();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/estudiante/dashboard' },
    { title: 'Mi Perfil', href: '/estudiante/perfil' },
  ];
</script>

<StudentLayout {breadcrumbs}>
  <div class="h-full px-5 md:px-10 lg:px-20 bg-white relative">
    <div class="relative mx-auto max-w-4xl px-4 py-6">
      <header class="flex flex-col gap-1 mb-8">
        <span
          class="inline-flex items-center gap-1.5 text-xs font-bold text-uta-blue bg-uta-blue-light border border-uta-blue/20 rounded-full px-3 py-0.5 w-fit"
        >
          Semestre {semestreActual} · {anoAcademico}
        </span>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
          Mi Perfil
        </h1>
        <p class="text-sm text-slate-500">Tus datos como estudiante en UTAmed.</p>
      </header>

      <section
        class="flex flex-wrap items-center gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-6"
      >
        <div
          class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-uta-blue-light text-lg font-bold text-uta-blue"
        >
          {getInitials(perfil.nombre_completo)}
        </div>
        <div class="flex flex-col gap-0.5">
          <span class="text-lg font-semibold tracking-tight text-slate-900">{perfil.nombre_completo}</span>
          <span class="text-sm text-slate-500">{perfil.carrera_nombre}</span>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light">
            <IdCard class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">RUT</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.rut}</span>
          </div>
        </div>

        <div class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light">
            <Mail class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Correo institucional</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.email}</span>
          </div>
        </div>

        <div class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light">
            <AtSign class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Usuario</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.username}</span>
          </div>
        </div>

        <div class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light">
            <GraduationCap class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Carrera</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.carrera_nombre}</span>
          </div>
        </div>

        <div class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light">
            <CalendarDays class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Año de ingreso</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.agno_ingreso}</span>
          </div>
        </div>

        <div class="flex items-center gap-3.5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-uta-blue-light">
            <BookOpen class="h-[18px] w-[18px] text-uta-blue" />
          </div>
          <div class="flex min-w-0 flex-col gap-0.5">
            <span class="text-[12.5px] text-slate-500">Cursos este período</span>
            <span class="truncate text-sm font-semibold text-slate-900">{perfil.total_cursos}</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</StudentLayout>
