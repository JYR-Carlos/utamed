<script lang="ts">
  let { open = false, onClose }: {
    open: boolean;
    onClose: () => void;
  } = $props();
</script>

{#if open}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
      <!-- Header -->
      <div class="flex items-center justify-between border-b px-6 py-4">
        <div>
          <p class="text-xl font-bold">
            Instrucciones de Uso
          </p>
        </div>

        <button
          class="rounded-lg p-2 transition hover:bg-gray-100"
          onclick={onClose}
        >
          ✕
        </button>
      </div>

      <!-- Body -->
      <div class="space-y-4 px-6 py-5">
      
        <div class="rounded-xl border p-4 bg-blue-50">
          <div class="mb-2 flex items-center gap-2">
            <div
              class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-700 text-sm font-bold text-white"
            >
              1
            </div>

            <p class="font-semibold">
              Revisar Enunciado
            </p>
          </div>

          <p class="text-sm text-gray-600">
          <span class="mt-1 text-sm text-gray-700">
            La actividad llega inicialmente en estado
            <b>DISPONIBLE</b>.
          </span>
            El estudiante debe presionar el botón
            <b>“Ver Enunciado”</b> para visualizar las instrucciones
            de la actividad.
          </p>

          <p class="mt-2 text-sm text-gray-600">
            Al realizar esta acción, el estado cambia automáticamente a
            <b>PENDIENTE</b>.
          </p>
        </div>

        <div class="rounded-xl border p-4 bg-orange-50">
          <div class="mb-2 flex items-center gap-2">
            <div
              class="flex h-7 w-7 items-center justify-center rounded-full bg-orange-700 text-sm font-bold text-white"
            >
              2
            </div>

            <p class="font-semibold">
              Registrar Entrega
            </p>
          </div>

          <p class="text-sm text-gray-600">
            Luego de revisar el enunciado, el estudiante debe presionar
            <b>“Agregar Entrega”</b>.
          </p>

          <p class="mt-2 text-sm text-gray-600">
            Esta acción simula el envío exitoso de la actividad.
          </p>
        </div>

        <div class="rounded-xl border border-green-200 bg-green-50 p-4">
          <div class="mb-2 flex items-center gap-2">
            <div
              class="flex h-7 w-7 items-center justify-center rounded-full bg-green-600 text-sm font-bold text-white"
            >
              3
            </div>

            <p class="font-semibold text-green-800">
              Retroalimentación del Docente
            </p>
          </div>

          <p class="text-sm text-green-700">
            Después de registrar la entrega:
          </p>

          <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-green-700">
            <li>El estado cambia a <b>APROBADO</b> o <b>REPROBADO</b>.</li>
            <li>La calificación es mostrada únicamente en actividades sumativas.</li>
          </ul>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex justify-end border-t px-6 py-4">
        <button
          class="rounded-xl bg-black px-5 py-2 text-sm font-medium text-white transition hover:opacity-90"
          onclick={onClose}
        >
          Entendido
        </button>
      </div>
    </div>
  </div>
{/if}