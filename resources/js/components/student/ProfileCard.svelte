<script lang="ts">
  interface Props {
    nombre: string;
    apellido1: string;
    apellido2?: string;
    rut: string;
    carrera: string;
  }

  let { nombre, apellido1, apellido2 = '', rut, carrera }: Props = $props();

</script>

<div
  class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-purple-600/5 via-cyan-600/5 to-orange-600/5 p-[1px]"
>
  <div class="relative rounded-3xl bg-white backdrop-blur-xl p-6 border border-gray-200">

    <div class="relative space-y-4">
      <!-- Name -->
      <div>
        <h3 class="text-lg font-semibold text-gray-900">
          {nombre} {apellido1}
          {apellido2}
        </h3>
      </div>

      <!-- Metadata -->
      <div class="pt-3 border-t border-gray-200">
        <p class="text-xs text-gray-500 mb-1">RUT</p>
        <p class="text-sm font-mono text-gray-700">{rut}</p>
      </div>

      <div class="pt-3 border-t border-gray-200">
        <p class="text-xs text-gray-500 mb-1">Carrera</p>
        <p class="text-sm font-mono text-gray-700">{carrera}</p>
      </div>
    </div>
  </div>
</div>
