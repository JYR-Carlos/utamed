<script lang="ts">
  import { Calendar } from 'lucide-svelte';
  import { useInitials } from '@/hooks';

  interface Props {
    nombre: string;
    apellido1: string;
    apellido2?: string;
    rut: string;
    carrera: string;
    semestre: number;
    agno: number;
  }

  let { nombre, apellido1, apellido2 = '', rut, carrera, semestre, agno }: Props = $props();

  const { getInitials } = useInitials();

  let nombreCompleto = $derived(`${nombre} ${apellido1} ${apellido2}`.trim());
  let periodoLabel = $derived(`${semestre === 1 ? '1er' : '2º'} semestre ${agno}`);
</script>

<section
  class="flex flex-wrap items-center gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
>
  <div
    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#22213F]/10 text-base font-bold text-[#22213F]"
  >
    {getInitials(nombreCompleto)}
  </div>

  <div class="flex flex-col gap-0.5">
    <span class="text-lg font-semibold tracking-tight text-slate-900">{nombreCompleto}</span>
    <span class="text-sm text-slate-500">{carrera} · RUT {rut}</span>
  </div>

  <div
    class="ml-auto flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5"
  >
    <Calendar class="h-3.5 w-3.5 text-slate-500" />
    <span class="text-[12.5px] text-slate-500">{periodoLabel}</span>
  </div>
</section>
