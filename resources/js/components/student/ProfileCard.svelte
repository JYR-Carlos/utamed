<script lang="ts">
  interface Props {
    nombre: string;
    apellido1: string;
    apellido2?: string;
    rut: string;
  }

  let { nombre, apellido1, apellido2 = '', rut }: Props = $props();

  const initials = `${nombre[0]}${apellido1[0]}`.toUpperCase();
</script>

<div
  class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-600/5 via-cyan-600/5 to-orange-600/5 p-[1px]"
>
  <div class="relative rounded-3xl bg-white backdrop-blur-xl p-6 border border-gray-200">
    <div class="absolute inset-0 bg-gradient-to-br from-white/50 to-transparent rounded-3xl" />

    <div class="relative space-y-4">
      <!-- Avatar -->
      <div
        class="w-12 h-12 rounded-2xl bg-gradient-to-br from-purple-600 to-cyan-600 flex items-center justify-center text-white font-bold text-lg"
      >
        {initials}
      </div>

      <!-- Name -->
      <div>
        <h3 class="text-lg font-semibold text-gray-900">
          {nombre}
        </h3>
        <p class="text-sm text-gray-600">
          {apellido1}
          {apellido2}
        </p>
      </div>

      <!-- Metadata -->
      <div class="pt-3 border-t border-gray-200">
        <p class="text-xs text-gray-500 mb-1">RUT</p>
        <p class="text-sm font-mono text-gray-700">{rut}</p>
      </div>
    </div>
  </div>
</div>
