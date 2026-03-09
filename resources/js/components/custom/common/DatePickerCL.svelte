<script lang="ts">
  /**
   * DatePickerCL — selector de fechas en español chileno (DD/MM/AAAA).
   *
   * Props:
   *   value        — fecha actual en formato "YYYY-MM-DD" o vacío/null
   *   onchange     — callback que recibe la fecha seleccionada como "YYYY-MM-DD"
   *   placeholder  — texto del botón cuando no hay fecha (por defecto "Seleccionar fecha")
   *   disabled     — deshabilita el control
   *   id           — id del botón trigger (para labels)
   *   minValue     — fecha mínima como "YYYY-MM-DD"
   */

  import { CalendarDate, today, getLocalTimeZone, parseDate } from '@internationalized/date';
  import type { DateValue } from '@internationalized/date';
  import { Calendar as CalendarIcon } from 'lucide-svelte';
  import { Calendar } from '@/components/ui/calendar';
  import * as Popover from '@/components/ui/popover';
  import { Button } from '@/components/ui/button';
  import { cn } from '@/lib/utils';

  interface Props {
    value?: string | null;
    onchange?: (iso: string | null) => void;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
    minValue?: string | null;
  }

  let { value = null, onchange, placeholder = 'Seleccionar fecha', disabled = false, id, minValue = null }: Props = $props();

  /** "YYYY-MM-DD" → CalendarDate */
  function isoToCalendar(iso: string | null | undefined): CalendarDate | undefined {
    if (!iso) return undefined;
    try {
      const d = parseDate(iso.slice(0, 10));
      return new CalendarDate(d.year, d.month, d.day);
    } catch {
      return undefined;
    }
  }

  /** CalendarDate → "YYYY-MM-DD" */
  function calendarToIso(d: DateValue | undefined): string | null {
    if (!d) return null;
    return `${d.year}-${String(d.month).padStart(2, '0')}-${String(d.day).padStart(2, '0')}`;
  }

  /** "YYYY-MM-DD" → "DD/MM/AAAA" para mostrar */
  function formatDisplay(iso: string | null | undefined): string {
    if (!iso) return '';
    const [year, month, day] = iso.slice(0, 10).split('-');
    return `${day}/${month}/${year}`;
  }

  // Componente controlado: calendarValue se deriva del prop value externo.
  // Se usa $derived para que siempre esté sincronizado sin necesidad de $effect.
  const calendarValue = $derived(isoToCalendar(value));
  const minCalendar = $derived(isoToCalendar(minValue));

  let open = $state(false);

  function handleSelect(d: DateValue | undefined) {
    onchange?.(calendarToIso(d));
    open = false;
  }
</script>

<Popover.Root bind:open>
  <Popover.Trigger>
    {#snippet child({ props })}
      <Button
        {id}
        variant="outline"
        {disabled}
        class={cn('w-full justify-start text-left font-normal', !value && 'text-muted-foreground')}
        {...props}
      >
        <CalendarIcon class="mr-2 h-4 w-4 shrink-0" />
        {value ? formatDisplay(value) : placeholder}
      </Button>
    {/snippet}
  </Popover.Trigger>
  <Popover.Content class="w-auto p-0" align="start">
    <Calendar
      type="single"
      locale="es-CL"
      captionLayout="dropdown-months"
      weekdayFormat="short"
      value={calendarValue}
      minValue={minCalendar}
      onValueChange={handleSelect}
    />
  </Popover.Content>
</Popover.Root>
