<script lang="ts">
  import type { Snippet } from 'svelte';

  interface Props {
    class?: string;
    children: Snippet;
  }

  let { class: className = '', children }: Props = $props();
</script>

<div class="w-full py-6 md:p-8">
  <div class={className}>
    {@render children?.()}
  </div>
</div>
