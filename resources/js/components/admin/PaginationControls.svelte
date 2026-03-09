<script lang="ts">
  /**
   * Componente de controles de paginación reutilizable.
   * Maneja ventana deslizante de botones con ellipsis.
   */
  interface Props {
    currentPage: number;
    lastPage: number;
    from: number;
    to: number;
    total: number;
    onPageChange: (page: number) => void;
  }

  let { currentPage, lastPage, from, to, total, onPageChange }: Props = $props();

  type PageBtn = { type: 'page'; n: number } | { type: 'ellipsis'; id: string };

  const pageButtons = $derived.by((): PageBtn[] => {
    if (lastPage <= 1) return [];
    const ws = Math.max(1, Math.min(currentPage - 2, lastPage - 4));
    const we = Math.min(lastPage, ws + 4);
    const btns: PageBtn[] = [];

    if (ws > 1) {
      btns.push({ type: 'page', n: 1 });
      if (ws > 2) btns.push({ type: 'ellipsis', id: 'l' });
    }
    for (let i = ws; i <= we; i++) btns.push({ type: 'page', n: i });
    if (we < lastPage) {
      if (we < lastPage - 1) btns.push({ type: 'ellipsis', id: 'r' });
      btns.push({ type: 'page', n: lastPage });
    }
    return btns;
  });

  function handlePrevious() {
    if (currentPage > 1) onPageChange(currentPage - 1);
  }

  function handleNext() {
    if (currentPage < lastPage) onPageChange(currentPage + 1);
  }
</script>

<div class="flex items-center justify-between flex-wrap gap-3">
  <!-- Info -->
  <span class="text-sm text-gray-500 shrink-0">
    {#if total === 0}
      Sin resultados
    {:else if to - from + 1 === total}
      {total} registro{total === 1 ? '' : 's'}
    {:else}
      {from}–{to} de {total}
    {/if}
  </span>

  <!-- Pagination buttons -->
  {#if lastPage > 1}
    <div class="flex items-center gap-1 ml-auto">
      <button
        onclick={handlePrevious}
        disabled={currentPage === 1}
        class="px-3 py-1.5 text-sm bg-white border border-gray-200 rounded-md hover:bg-gray-50 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
        aria-label="Página anterior"
      >
        ←
      </button>

      {#each pageButtons as btn}
        {#if btn.type === 'ellipsis'}
          <span class="px-1 text-gray-400 text-sm select-none">…</span>
        {:else}
          <button
            onclick={() => onPageChange(btn.n)}
            class={`min-w-[2rem] h-8 px-2 text-sm rounded-md border transition cursor-pointer ${btn.n === currentPage ? 'bg-blue-500 border-blue-500 text-white font-semibold' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'}`}
            aria-current={btn.n === currentPage ? 'page' : undefined}
          >
            {btn.n}
          </button>
        {/if}
      {/each}

      <button
        onclick={handleNext}
        disabled={currentPage === lastPage}
        class="px-3 py-1.5 text-sm bg-white border border-gray-200 rounded-md hover:bg-gray-50 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
        aria-label="Próxima página"
      >
        →
      </button>
    </div>
  {/if}
</div>
