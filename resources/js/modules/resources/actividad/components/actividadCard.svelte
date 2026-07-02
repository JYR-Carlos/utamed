<script lang="ts">
  /**
   * actividadCard — Tarjeta de solo lectura de una actividad (vista
   * estudiante, pages/student/Activities/ActividadesView.svelte).
   *
   * El tipo de la prop es local porque el payload del controlador de
   * estudiante difiere del tipo Actividad del docente (usa flags booleanos
   * es_sumativa/con_entrega en vez de los enums tipo_actividad/tipo_entrega).
   */
  import { formatDate } from '@/utils/formatters';

  interface Props {
    actividad: {
      id_actividad: number;
      nombre: string;
      es_grupal: boolean;
      es_sumativa: boolean;
      con_entrega: boolean;
      max_integrantes: number;
      fecha_limite: string;
      visible: boolean;
    };
    idCurso: number;
  }

  let { actividad, idCurso }: Props = $props();
</script>

<div
  class="bg-white border border-gray-200 rounded-xl p-6  hover:border-blue-500 hover:shadow-lg transition-all flex flex-col"
>
  <div class="flex justify-between items-start mb-4">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 flex-1">
      <h3 class="text-md font-semibold text-gray-900">{actividad.nombre}</h3>
      <div class="flex gap-2">
        <!--ACTIVIDAD GRUPAL O INDIVIDUAL-->
        {#if actividad.es_grupal}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-6"
          >
            <title>Grupal</title>
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
            />
          </svg>
        {:else}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-6"
          >
            <title>Individual</title>
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
            />
          </svg>
        {/if}

        <!--ACTIVIDAD CON O SIN ENTREGA-->
        {#if actividad.con_entrega}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-6"
          >
            <title>Con entrega</title>
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
            />
          </svg>
        {:else}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-6"
          >
            <title>Sin entrega</title>
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
            />
          </svg>
        {/if}

        <!--ACTIVIDAD SUMATIVA O FORMATIVA-->
        {#if actividad.es_sumativa}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="32"
            height="32"
            fill="#000000"
            viewBox="0 0 256 256"
          >
            <title>Sumativa</title>
            <path
              d="M224,48V208a16,16,0,0,1-16,16H136a8,8,0,0,1,0-16h72V48H48v96a8,8,0,0,1-16,0V48A16,16,0,0,1,48,32H208A16,16,0,0,1,224,48ZM125.66,154.34a8,8,0,0,0-11.32,0L64,204.69,45.66,186.34a8,8,0,0,0-11.32,11.32l24,24a8,8,0,0,0,11.32,0l56-56A8,8,0,0,0,125.66,154.34Z"
            ></path></svg
          >
        {:else}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="32"
            height="32"
            fill="#000000"
            viewBox="0 0 256 256"
            ><title>Formativa</title>
              <path
              d="M216,48H40a8,8,0,0,0-8,8V208a16,16,0,0,0,16,16H88a16,16,0,0,0,16-16V160h48v16a16,16,0,0,0,16,16h40a16,16,0,0,0,16-16V56A8,8,0,0,0,216,48ZM88,208H48V128H88Zm0-96H48V64H88Zm64,32H104V64h48Zm56,32H168V128h40Zm0-64H168V64h40Z"
            ></path></svg
          >
        {/if}
      </div>
    </div>
  </div>

  <div class="flex flex-col sm:grid sm:grid-cols-2 gap-3 my-4 flex-1 p-6 bg-purple-50 rounded-lg">
    <div class="flex gap-2 text-sm justify-between sm:justify-normal">
      <span class="text-gray-600 font-medium">Tipo:</span>
      <span class="text-gray-900 font-medium"
        >{actividad.es_sumativa ? 'Sumativa' : 'Formativa'}</span
      >
    </div>
    <div class="flex gap-2 text-sm justify-between sm:justify-normal">
      <span class="text-gray-600 font-medium">Tipo Entrega:</span>
      <span class="text-gray-900 font-medium capitalize">
        {actividad.con_entrega ? 'Entrega Obligatoria' : 'Sin Entrega'}
      </span>
    </div>
    <div class="flex gap-2 text-sm items-center justify-between sm:justify-normal">
      <span class="text-gray-600 font-medium">Modalidad:</span>
      <span class="flex items-center font-medium text-gray-900">
        {#if actividad.es_grupal}
          Grupal ({actividad.max_integrantes})
        {:else}
          Individual
        {/if}
      </span>
    </div>
    <div class="flex gap-2 text-sm justify-between sm:justify-normal">
      <span class="text-gray-600 font-medium">Fecha Límite:</span>
      <span class="text-blue-600 font-semibold">{formatDate(actividad.fecha_limite)}</span>
    </div>
  </div>
</div>
