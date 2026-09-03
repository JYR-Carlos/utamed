<script lang="ts">
  import axios from "axios";
  
  import type { BibliografiaSyllabus } from "@/types/syllabus.types";

  export let isOpen = false;
  export let unidades: any[] = [];
  export let idCurso: number | null = null;
  export let idPrograma: number | null = null;
  export let onClose: () => void;
  export let onSave: (data: BibliografiaSyllabus) => void;

  let isUploading = false;
  let uploadError = "";
  let uploadType = "url";

  let formData: BibliografiaSyllabus = {
    titulo: "",
    autor: "",
    cita: "",
    editorial: "",
    anio: new Date().getFullYear(),
    es_bibliografia_uta: false,
    url: "",
    uuid_archivo: null,
    id_unidad: null,
  };

  function resetForm() {
    formData = {
      titulo: "",
      autor: "",
      cita: "",
      editorial: "",
      anio: new Date().getFullYear(),
      es_bibliografia_uta: false,
      url: "",
      uuid_archivo: null,
      id_unidad: null,
    };
    uploadError = "";
  }

  function handleClose() {
    resetForm();
    onClose();
  }

  async function handleFileUpload(e: Event) {
    const input = e.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    isUploading = true;
    uploadError = "";

    const payload = new FormData();
    payload.append("archivo", file);
    if (idCurso) {
      payload.append("id_curso", String(idCurso));
    }
    if (formData.id_unidad) {
      payload.append("id_unidad", String(formData.id_unidad));
    }
    if (idPrograma) {
      payload.append("id_programa", String(idPrograma));
    }
    if (formData.titulo) {
      payload.append("titulo", formData.titulo);
    }
    if (formData.autor) {
      payload.append("autor", formData.autor);
    }

    try {
      const response = await axios.post("/api/bibliografias/archivo", payload, {
        headers: { "Content-Type": "multipart/form-data" }
      });
      formData.uuid_archivo = response.data.uuid_archivo;
    } catch (err: any) {
      uploadError = err.response?.data?.message || "Error al subir el archivo.";
      input.value = "";
    } finally {
      isUploading = false;
    }
  }

  function handleUrlPaste(e: ClipboardEvent) {
    const pastedText = e.clipboardData?.getData('text');
    if (pastedText && (formData.url === 'https://' || !formData.url)) {
      e.preventDefault();
      let url = pastedText.trim();
      if (!url.startsWith('http://') && !url.startsWith('https://')) {
          url = 'https://' + url;
      }
      formData.url = url;
    }
  }
  function handleUrlFocus() {
    if (!formData.url) formData.url = 'https://';
  }
  function handleUrlBlur() {
    if (formData.url === 'https://') formData.url = '';
  }

  function handleSave() {
    if (!formData.autor.trim()) {
      formData.autor = "S/A";
    }
    
    if (formData.url) {
      let trimmed = formData.url.trim();
      if (!trimmed.startsWith("http://") && !trimmed.startsWith("https://")) {
        trimmed = "https://" + trimmed;
      }
      formData.url = trimmed;
    }
    
    if (!formData.es_bibliografia_uta) {
      if (!formData.url && !formData.uuid_archivo) {
        alert("Debe proveer una URL externa o subir un archivo físico.");
        return;
      }
      if (formData.url && formData.uuid_archivo) {
        alert("Debe proveer solo una URL o un archivo físico, no ambos.");
        return;
      }
    } else {
      formData.url = "";
      formData.uuid_archivo = null;
    }

    onSave({ ...formData });
    handleClose();
  }
</script>

{#if isOpen}
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
      <h2 class="text-xl font-bold text-slate-800">Agregar Bibliografía</h2>
      <button type="button" onclick={handleClose} class="text-slate-400 hover:text-slate-600">✕</button>
    </div>

    <div class="p-6 overflow-y-auto flex-1 space-y-5">
      
      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-1">Título *</label>
        <input type="text" bind:value={formData.titulo} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Título del libro o documento">
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-1">Autor</label>
        <input type="text" bind:value={formData.autor} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Deja vacío para S/A">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1">Año *</label>
          <input type="number" bind:value={formData.anio} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" min="1900" max="2100">
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1">Editorial (Opcional)</label>
          <input type="text" bind:value={formData.editorial} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
      </div>
      
      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-1">Unidad asociada (Opcional)</label>
        <select bind:value={formData.id_unidad} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
          <option value={null}>-- General / Todo el curso --</option>
          {#each unidades as uni}
            <option value={uni.numero}>Unidad {uni.numero}: {uni.titulo}</option>
          {/each}
        </select>
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-1">Cita (Opcional)</label>
        <textarea bind:value={formData.cita} rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Formato APA, IEEE, etc."></textarea>
      </div>

      <div class="pt-4 border-t border-slate-200">
        <label class="flex items-center space-x-2 cursor-pointer mb-4">
          <input type="checkbox" bind:checked={formData.es_bibliografia_uta} class="rounded border-slate-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
          <span class="text-sm font-semibold text-slate-700">Este recurso pertenece a las bases de datos oficiales de la UTA</span>
        </label>

        {#if !formData.es_bibliografia_uta}
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-4">
            
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1">Quiero proveer un...</label>
              <select bind:value={uploadType} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white">
                <option value="url">Enlace / URL Externa</option>
                <option value="file">Archivo Físico (PDF, DOCX, etc.)</option>
              </select>
            </div>
            
            {#if uploadType === 'url'}
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">URL Externa</label>
                <input type="url" bind:value={formData.url} onpaste={handleUrlPaste} onfocus={handleUrlFocus} onblur={handleUrlBlur} class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 {formData.url === 'https://' ? 'text-slate-400' : 'text-slate-900'}" placeholder="https://...">
              </div>
            {:else}
              <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Subir Archivo Físico</label>
                <input type="file" onchange={handleFileUpload} disabled={isUploading} class="w-full text-sm border border-slate-300 rounded-lg file:mr-4 file:py-2 file:px-4 file:rounded-l-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer disabled:opacity-50" accept=".pdf,.doc,.docx,.ppt,.pptx">
                {#if isUploading}
                  <p class="text-xs text-blue-600 mt-2 font-medium">Subiendo archivo, por favor espera...</p>
                {/if}
                {#if formData.uuid_archivo}
                  <p class="text-xs text-green-600 mt-2 font-medium">✓ Archivo adjuntado correctamente.</p>
                {/if}
                {#if uploadError}
                  <p class="text-xs text-red-600 mt-2 font-medium">{uploadError}</p>
                {/if}
              </div>
            {/if}
          </div>
        {:else}
          <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm text-blue-800">Los estudiantes recibirán instrucciones automáticas para buscar este título en los portales oficiales de la universidad (Ej: e-Libro, EBSCO).</p>
          </div>
        {/if}
      </div>
    </div>

    <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3 bg-slate-50">
      <button type="button" onclick={handleClose} class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
        Cancelar
      </button>
      <button type="button" onclick={handleSave} disabled={isUploading || !formData.titulo} class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
        Guardar Bibliografía
      </button>
    </div>
  </div>
</div>
{/if}
