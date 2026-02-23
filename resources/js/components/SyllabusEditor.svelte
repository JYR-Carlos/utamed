<script lang="ts">
  import { Button } from "@/components/ui/button";
  import { Input } from "@/components/ui/input";
  import { Label } from "@/components/ui/label";
  import { Textarea } from "@/components/ui/textarea";
  import { Plus, Trash2, GripVertical, Save, Loader2 } from "lucide-svelte";
  import { createEventDispatcher } from "svelte";

  export let modelValue: any[] = [];
  export let readOnly = false;
  export let isLoading = false;

  const dispatch = createEventDispatcher();

  function addSection() {
    modelValue = [
      ...modelValue,
      {
        nombre_seccion: "Nueva Sección",
        numeral_romano: "",
        es_lista: false,
        orden: modelValue.length + 1,
        contenidos: []
      }
    ];
  }

  function removeSection(index: number) {
    if (confirm("¿Estás seguro de eliminar esta sección?")) {
      modelValue = modelValue.filter((_: any, i: number) => i !== index);
    }
  }

  function addContent(sectionIndex: number) {
    const section = modelValue[sectionIndex];
    section.contenidos = [
      ...(section.contenidos || []),
      {
        texto_contenido: "",
        orden_item: (section.contenidos?.length || 0) + 1
      }
    ];
    modelValue = [...modelValue];
  }

  function removeContent(sectionIndex: number, contentIndex: number) {
    const section = modelValue[sectionIndex];
    section.contenidos = section.contenidos.filter((_: any, i: number) => i !== contentIndex);
    modelValue = [...modelValue];
  }

  function save() {
    dispatch('save', modelValue);
  }
</script>

<div class="space-y-6">
  {#each modelValue as section, i}
    <div class="border rounded-lg p-4 bg-white shadow-sm hover:shadow-md transition-all relative group">
      <!-- Section Header -->
      <div class="flex items-center gap-4 mb-4">
        <div class="cursor-move text-gray-400">
          <GripVertical size={20} />
        </div>
        <div class="flex-1 grid grid-cols-[auto_1fr] gap-4">
            <div class="w-24">
                <Label class="text-xs text-gray-500">Numeral</Label>
                 <Input 
                    bind:value={section.numeral_romano} 
                    placeholder="I, II..." 
                    class="font-serif font-bold text-lg"
                    disabled={readOnly}
                />
            </div>
            <div>
                 <Label class="text-xs text-gray-500">Título de Sección</Label>
                 <Input 
                    bind:value={section.nombre_seccion} 
                    placeholder="Título de la sección" 
                    class="font-serif font-bold text-xl border-none px-0 focus-visible:ring-0"
                    disabled={readOnly}
                />
            </div>
        </div>
        
        {#if !readOnly}
          <Button variant="ghost" size="icon" class="text-red-500 opacity-0 group-hover:opacity-100 transition-opacity" onclick={() => removeSection(i)}>
            <Trash2 size={18} />
          </Button>
        {/if}
      </div>

      <!-- Section Content -->
      <div class="pl-12 space-y-3">
        {#if section.contenidos}
            {#each section.contenidos as content, j}
            <div class="flex items-start gap-2 group/content">
                <div class="flex-1">
                    <Textarea 
                        bind:value={content.texto_contenido} 
                        placeholder="Escribe el contenido aquí..." 
                        class="resize-none min-h-[80px] text-justify leading-relaxed"
                        disabled={readOnly}
                    />
                </div>
                {#if !readOnly}
                    <Button variant="ghost" size="icon" class="text-red-400 opacity-0 group-hover/content:opacity-100 transition-opacity h-8 w-8" onclick={() => removeContent(i, j)}>
                        <Trash2 size={14} />
                    </Button>
                {/if}
            </div>
            {/each}
        {/if}

        {#if !readOnly}
            <Button variant="outline" size="sm" class="mt-2 text-primary border-dashed" onclick={() => addContent(i)}>
            <Plus size={14} class="mr-2" /> Agregar Contenido
            </Button>
        {/if}
      </div>
    </div>
  {/each}

  {#if !readOnly}
    <div class="flex justify-center py-4">
        <Button variant="secondary" class="w-full border-dashed border-2 py-8" onclick={addSection} disabled={isLoading}>
            <Plus size={24} class="mr-2" /> Agregar Nueva Sección
        </Button>
    </div>

    <div class="sticky bottom-4 flex justify-end">
        <Button size="lg" class="shadow-xl" onclick={save} disabled={isLoading}>
            {#if isLoading}
                <Loader2 size={18} class="mr-2 animate-spin" /> Guardando...
            {:else}
                <Save size={18} class="mr-2" /> Guardar Programa
            {/if}
        </Button>
    </div>
  {/if}
</div>
