/**
 * Módulo Programa — barrel principal
 */

// Components
export * from './components/index';

// Services
export * from './services/programaApi';

// Types
export type {
    Programa,
    Curso,
    ContenidoPrograma,
    SeccionPrograma,
    ProgramaFull,
    WizardUnidad,
    WizardActividad,
    WizardComponente,
    SyllabusType,
    GuardarProgramaPayload,
    GenerarProgramaPayload,
} from './types/programa.types';
