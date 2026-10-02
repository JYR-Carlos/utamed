import type { Rubrica } from '@/types/rubrica';

export interface InteraccionItem {
  id_interaccion: number;
  fecha_emision: string;
  tipo_interaccion: string;
  emisor: string;
  mensaje: string;
  es_de_docente: boolean;
  es_propio?: boolean;
  uuid_archivo?: string | null;
  es_retroalimentacion?: boolean;
  es_entrega?: boolean;
  tiene_evaluacion?: boolean;
  adjunta_rubrica?: boolean;
  rubrica?: Rubrica | null;
  puntaje_obtenido?: number | null;
  resultado?: Record<string, string> | null;
  archivo?: {
    nombre_original: string | null;
    peso_bytes: number | null;
    mime_type?: string | null;
    visualizable?: boolean;
  } | null;
  entrega_evaluada?: {
    id_agenda: number;
    fecha_envio: string;
    nombre_original: string | null;
  } | null;
  fue_cancelada?: boolean;
  fecha_cancelacion?: string | null;
  cancelado_por?: string | null;
  /** Sólo en el último mensaje del hilo: quiénes lo han visto (T07). */
  visto_por?: Array<{ nombre: string; fecha_lectura: string }>;
}

export interface RubricaDetalleEvent {
  rubrica?: Rubrica | null;
  puntaje_obtenido?: number | null;
  retroalimentacion?: string;
  resultado?: Record<string, string> | null;
  evaluador?: string;
  fecha?: string;
}
