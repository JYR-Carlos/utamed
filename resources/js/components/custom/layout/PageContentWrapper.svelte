<script lang="ts">
  import type { Snippet } from 'svelte';

  interface Props {
    class?: string;
    children: Snippet;
  }

  let { class: className = '', children }: Props = $props();
</script>

<div class="w-full md:px-6 py-6 md:py-8">
  <div class="mx-auto w-full max-w-7xl">
    <div class={className}>
      {@render children?.()}
    </div>
  </div>
</div>
