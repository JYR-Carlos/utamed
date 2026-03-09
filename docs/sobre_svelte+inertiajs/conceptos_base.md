# Borrador (reunion)
- Nombrar temas de los props y como se propagan, en qué parte del backend se envían y el frontend como lo llama.
- Como funciona el tema del log, autorización y proteccion ante errores.
- Explicar las funciones que el administrador posee y que archivos están implicados.

Orden de la reunión:
1. Nombrar la comunicación entre backend y frontend con inertiajs (props y rutas, wayfinder: como generar o que genera -> mostrar flujo).
2. Poner ejemplo usuario
3. Mostrar lo avanzando.
4. 
# Administrador
## Pages
### Usuarios

Las variables reutilizables (props) recibidas son:
``` TS
  interface Props {
    /** Usuarios paginados según tipo seleccionado */
    usuarios: PaginatedResponse<UsuarioItem>;
    /** Tipo de usuario a mostrar: estudiante, docente o administrador */
    tipo: 'estudiante' | 'docente' | 'administrador';
    /** Carreras disponibles (para asignar a estudiantes) */
    carreras: Carrera[];
    /** Roles disponibles para asignar a usuarios */
    availableRoles: any[];
    /** Permisos especiales disponibles por módulo */
    availablePermissions: Record<string, any[]>;
    /** Filtros de búsqueda/tipo */
    filters: { search?: string; tipo?: string };
  }
```


### Facultades
### Carreras
### Asignaturas
### Planes de Estudio
### Cursos Ofertados
### Inscripciones
### Syllabus