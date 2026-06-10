<script lang="ts">
  // Definimos los tres estados permitidos
  type EstadoActividad = 'activa' | 'no_asignada' | 'no_disponible';

  interface Props {
    estado: EstadoActividad;
  }

  let { estado }: Props = $props();

  // Diccionario con los textos y colores para cada estado
  const estadosConfig = {
    activa: {
      texto: 'Activa',
      alerta: 'bg-emerald-50/60 border-emerald-100 text-emerald-900',
      punto: 'bg-emerald-500',
    },
    no_asignada: {
      texto: 'No asignada',
      alerta: 'bg-amber-50/60 border-amber-100 text-amber-900',
      punto: 'bg-amber-500',
    },
    no_disponible: {
      texto: 'No disponible',
      alerta: 'bg-slate-50/60 border-slate-200 text-slate-500',
      punto: 'bg-slate-400',
    },
  };

  // Obtenemos la configuración actual reactivamente
  const info = $derived(estadosConfig[estado]);
</script>

<div class="flex items-center gap-4 rounded-xl p-4 border shadow-sm transition-all {info.alerta}">
  <div class="h-3 w-3 rounded-full shrink-0 {info.punto}"></div>

  <div>
    <span class="block text-xs font-medium opacity-75 uppercase tracking-wider"
      >Estado de la actividad</span
    >
    <span class="block text-base font-bold">{info.texto}</span>
  </div>
</div>
