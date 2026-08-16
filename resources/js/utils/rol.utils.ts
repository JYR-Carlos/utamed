/**
 * Nombres de rol para mostrar en la interfaz.
 *
 * La tabla `rol` guarda identificadores pensados para el código
 * («AsignaturasManagerDelegable»), y el asistente de permisos los mostraba
 * tal cual: CamelCase en inglés dentro de una interfaz en español, en una
 * pantalla donde el administrador decide quién puede hacer qué.
 *
 * Mientras la tabla no tenga columnas de nombre visible y descripción,
 * la traducción vive aquí. Al añadirlas en el backend, este mapa se
 * sustituye por los datos del endpoint sin tocar el componente.
 */

/** Roles cuyo identificador no es legible tal cual. */
const ROLE_LABELS: Record<string, string> = {
    AsignaturasManager: 'Gestor de asignaturas',
    AsignaturasManagerDelegable: 'Gestor de asignaturas (puede delegar)',
};

/** Qué habilita cada rol, en una línea. */
const ROLE_DESCRIPTIONS: Record<string, string> = {
    AsignaturasManager: 'Crea y edita el catálogo de asignaturas.',
    AsignaturasManagerDelegable:
        'Crea y edita asignaturas, y puede conceder ese mismo permiso a otras personas.',
    Ayudante: 'Apoya la gestión del curso sin poder cerrar actas.',
    'Ayudante Visualizar Curso': 'Consulta el curso sin poder modificarlo.',
    'Director de Departamento': 'Gestiona las carreras y los cursos de su departamento.',
    'Docente Componente': 'Imparte un componente del curso y califica sus actividades.',
    'Docente Componente Colegiado':
        'Imparte un componente compartido con otros docentes del mismo curso.',
    'Docente Titular': 'Responsable del curso: equipo docente, syllabus y acta.',
    'Docente Titular Restringido': 'Responsable del curso, sin poder modificar el equipo docente.',
    'Docente Visualizador': 'Consulta el curso sin poder modificarlo.',
    Estudiante: 'Cursa asignaturas y entrega actividades.',
    'Estudiante Participa en Actividad': 'Participa en una actividad concreta del curso.',
    'Jefe de Carrera': 'Gestiona los planes, cursos y seguimiento de su carrera.',
    Supervisor: 'Consulta transversal sin permisos de edición.',
};

/**
 * Separa un identificador CamelCase en palabras. Es el respaldo para roles
 * nuevos que aún no estén en el mapa, de modo que al menos no aparezcan
 * pegados.
 */
function splitCamelCase(nombre: string): string {
    return nombre.replace(/([a-z\d])([A-Z])/g, '$1 $2').replace(/\s+/g, ' ').trim();
}

/** Nombre del rol tal como debe leerlo una persona. */
export function roleLabel(nombre: string): string {
    return ROLE_LABELS[nombre] ?? splitCamelCase(nombre);
}

/** Qué concede el rol, o cadena vacía si aún no está documentado. */
export function roleDescription(nombre: string): string {
    return ROLE_DESCRIPTIONS[nombre] ?? '';
}
