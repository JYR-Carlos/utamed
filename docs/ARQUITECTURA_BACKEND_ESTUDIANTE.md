# Arquitectura Backend: Dominio y Capacidades del Estudiante en UTAMED

Este documento constituye una guía técnica exhaustiva y legible sobre el funcionamiento, dominio, responsabilidades y árbol de clases PHP involucradas en la experiencia de un usuario con rol de **Estudiante** dentro de la plataforma UTAMED.

---

## 1. Fundamentos Arquitectónicos y Modelo de Identidad

### 1.1. Dualidad `Usuario` vs `Estudiante`
En UTAMED, la identidad institucional y el perfil académico están desacoplados:
* **`App\Models\Usuario\Usuario`**: Representa la entidad de autenticación y datos civiles (RUT, nombres, apellidos, correo institucional, passhash, estado activo).
* **`App\Models\Usuario\Estudiante`**: Representa el perfil académico vinculado a una carrera (`id_carrera`), cohorte (`agno_ingreso`) y datos de contacto personal editables (`correo_personal`, `celular`, `redes_sociales`).

```
[Oracle / Intranet UTA]
         │ (Sincronización / Ingestión)
         ▼
[usuario.usuario] 1 ──── 1 [usuario.estudiante] ──── * [administrativo.carrera]
                                  │
                   ┌──────────────┴──────────────┐
                   ▼                             ▼
        [curso.inscripcion_curso]   [curso.inscripcion_componente]
                   │                             │
                   ▼                             ▼
             [curso.curso]              [curso.componente]
```

### 1.2. Inmutabilidad Institucional vs. Contacto Personal
* **Datos Inmutables**: Nombre, RUT, correo institucional y carrera provienen de la Intranet UTA (`app/Models/External/`) y son estrictamente de solo lectura para el estudiante.
* **Datos Editables (T10)**: El estudiante solo puede actualizar sus datos de contacto voluntario (`correo_personal`, `celular`, y redes sociales estrictamente auditadas contra una lista blanca de dominios: YouTube, X, Instagram, LinkedIn).

### 1.3. Modelo de Entrega: Inertia.js y Datos Globales
La aplicación opera como un monolito moderno utilizando Laravel + Inertia.js. Cada petición HTTP pasa por el middleware `App\Http\Middleware\HandleInertiaRequests`, el cual inyecta globalmente en la sesión:
* `auth.user`: Objeto del usuario autenticado.
* `auth.estudiante`: Objeto del perfil de estudiante.
* `auth.roles`: Listado de roles activos del usuario.
* `auth.urlFotoPerfil`: URL firmada hacia el proxy de imágenes de credencial Intranet (`App\Services\FotoIntranetService`).
* `auth.estudiante_courses`: Colección optimizada de cursos inscritos para el menú lateral (sidebar), generada en lote por `App\Services\UserCoursesService::getEstudianteCourses()`.

---

## 2. Autorización, Scoping y Seguridad (RELBAC)

### 2.1. Middleware de Entrada
* **`App\Http\Middleware\IsStudent` (`is_estudiante`)**: Registrado en `bootstrap/app.php`. Intercepta toda petición bajo el prefijo `/estudiante/*` y comprueba `$user->hasRole('Estudiante')`. Si no posee el rol, redirige a `/dashboard` con error de acceso.

### 2.2. Contextos Jerárquicos y Relaciones
El control de acceso por roles (RELBAC) de UTAMED opera a nivel de contextos:
* Un curso define un contexto (`curso.id_contexto`).
* Un componente define un contexto hijo (`componente.id_contexto`).
* Las actividades y sus grupos cuelgan de estos componentes.

### 2.3. Guardarraíles de Consulta (Anti-IDOR y Scoping Obligatorio)
Para prevenir ataques de referencia directa (IDOR) o fugas de datos entre alumnos:
1. **Pertenencia a Curso**: Todo controlador valida que el alumno posea una inscripción activa (`estado_inscripcion = 'INSCRITO'`) en `curso.inscripcion_curso`.
2. **Pertenencia a Componente**: El acceso a actividades, mensajes y rendimiento se filtra por `curso.inscripcion_componente`. Si un curso aún no tiene componentes divididos, se recurre defensivamente a los componentes base del curso.
3. **Pertenencia a Grupo de Actividad**: En entregas y agendas, se exige existencia en `agenda.integrante_grupo` para la actividad dada (`actividad_asignada_grupo`).
4. **Validación Cruzada Actividad-Curso**: Se comprueba explícitamente `$actividad->componente->id_curso === $curso->id_curso` antes de servir cualquier detalle o archivo.
5. **Visibilidad**: Solo se exponen actividades con el flag `visible = true`.

---

## 3. Desglose por Dominios Funcionales y Árbol de Clases

A continuación se detallan los 9 dominios del backend que conforman el espacio del estudiante.

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Student/
│   │   │   ├── DashboardController.php
│   │   │   ├── PerfilController.php
│   │   │   ├── CourseController.php
│   │   │   ├── ProgramaController.php
│   │   │   ├── ActivityController.php
│   │   │   ├── AgendaController.php
│   │   │   ├── BitacoraController.php
│   │   │   └── MensajeriaController.php
│   │   ├── Settings/
│   │   │   ├── ProfileController.php
│   │   │   └── PasswordController.php
│   │   └── Sso/
│   │       └── SgeqSsoController.php
│   ├── Middleware/
│   │   ├── IsStudent.php
│   │   └── HandleInertiaRequests.php
│   └── Requests/
│       └── Archive/
│           └── AgendaFileRequest.php
├── Services/
│   ├── Student/
│   │   ├── BitacoraCursoEstudiante.php
│   │   ├── MensajeriaCursoEstudiante.php
│   │   ├── RendimientoCursoEstudiante.php
│   │   ├── ResumenActividadesEstudiante.php
│   │   └── StudentSyllabusPresenter.php
│   ├── Archive/
│   │   ├── Handlers/
│   │   │   └── AgendaArchiveHandler.php
│   │   ├── AgendaArchiveService.php
│   │   └── AbstractArchiveService.php
│   ├── Agenda/
│   │   └── LecturaAgendaService.php
│   ├── Sso/
│   │   └── SgeqSsoService.php
│   ├── UserCoursesService.php
│   ├── MensajeriaService.php
│   ├── FotoIntranetService.php
│   └── EstudianteService.php
├── Policies/
│   ├── EstudiantePolicy.php
│   ├── CursoPolicy.php
│   └── InscripcionCursoPolicy.php
└── Models/
    ├── Usuario/ (Usuario, Estudiante, Rol, UsuarioRolAsignacion, Contexto)
    ├── Curso/ (Curso, Componente, TipoComponente, InscripcionCurso, InscripcionComponente, Programa, Mensaje, InteraccionMensaje, Asistencia)
    ├── Agenda/ (Actividad, ActividadAsignadaGrupo, IntegranteGrupo, Agenda, LecturaAgenda, Evaluacion, Rubrica)
    └── Operaciones/ (Archivo)
```

---

### Dominio 1: Identidad, Perfil y Configuración Personal

Gestiona los datos de identificación del estudiante, su fotografía oficial y sus preferencias de contacto.

* **Controladores**:
  * `App\Http\Controllers\Student\PerfilController`
  * `App\Http\Controllers\Settings\PasswordController`
* **Servicios**:
  * `App\Services\FotoIntranetService`: Obtiene de forma segura la URL firmada para la fotografía del estudiante desde el proxy de Intranet basado en su RUT.
  * `App\Services\EstudianteService`: Búsqueda, normalización de RUT (`App\Support\Rut`) y asignación de rol en contexto.
* **Modelos Involucrados**:
  * `App\Models\Usuario\Usuario`
  * `App\Models\Usuario\Estudiante`
  * `App\Models\Administrativo\Carrera`
* **Políticas**:
  * `App\Policies\EstudiantePolicy` (hereda de `BaseEstudiantePolicy`)
* **Capacidades del Estudiante**:
  * **Ver perfil**: Datos institucionales (nombre, RUT, correo, nombre de carrera, año de ingreso, total de cursos inscritos).
  * **Actualizar contacto**: Modificar `correo_personal`, `celular` y enlaces a redes sociales (`youtube`, `x`, `instagram`, `linkedin`). Validación con regex y whitelist de hosts en `PerfilController::updateContacto`.
  * **Gestión de credenciales**: Cambio voluntario de contraseña o forzado por vencimiento (`VIGENCIA_PASSWORD_MESES = 6` o primer ingreso tras sincronización Intranet) con throttle de 6 intentos/minuto.

---

### Dominio 2: Dashboard y Resumen General

Punto de entrada principal (`/estudiante/dashboard`). Consolida métricas, recordatorios y cursos activos.

* **Controladores**:
  * `App\Http\Controllers\Student\DashboardController`
* **Servicios**:
  * `App\Services\Student\ResumenActividadesEstudiante`:
    * `proximasAVencer($estudiante, $semestre, $agno)`: Detecta entregas pendientes con vencimiento dentro de los próximos 7 días, calculando la fecha real a partir de `fecha_limite + holgura_actividad + holgura_personal_grupo`.
    * `notasYRetroalimentaciones($estudiante)`: Rescata las últimas 5 evaluaciones o comentarios docentes recibidos.
  * `App\Services\MensajeriaService`:
    * `noLeidosPorComponente()`: Agrupa el conteo de mensajes sin leer del estudiante a nivel de curso.
  * `App\Services\Sso\SgeqSsoService`:
    * `resolverRol($usuario)`: Determina si el estudiante tiene permiso para ver el acceso al sistema de préstamos SGEQ.
* **Modelos Involucrados**:
  * `App\Models\Curso\InscripcionCurso`
  * `App\Models\Curso\Curso`
  * `App\Models\Curso\Componente`
  * `App\Models\Curso\DocenteComponente`
* **Capacidades del Estudiante**:
  * Visualizar tarjetas de cursos inscritos en el período académico vigente (semestre y año real).
  * Conocer al docente titular de su componente o sección (resuelto priorizando titularidad de componente sobre el titular del curso).
  * Visualizar un badge con mensajes no leídos por curso.
  * Recibir alertas de actividades a punto de vencer o entregas recientes calificadas.

---

### Dominio 3: Gestión de Cursos y Rendimiento Académico

Proporciona la vista del catálogo de cursos inscritos y la ficha principal del curso (`/estudiante/cursos/{curso}`).

* **Controladores**:
  * `App\Http\Controllers\Student\CourseController`
* **Servicios**:
  * `App\Services\Student\RendimientoCursoEstudiante`:
    * Calcula el promedio ponderado de actividades sumativas por componente.
    * Compara el desempeño individual (`mi_promedio`) con el rendimiento promedio anónimo del curso (`promedio_curso`).
    * Monitorea la asistencia acumulada en `curso.asistencia` frente a la regla institucional de asistencia mínima (**70%**).
    * Combina la ponderación total del curso según la configuración de la Sección IX del Syllabus.
  * `App\Services\Student\ResumenActividadesEstudiante`:
    * `paraAlumno($estudiante, $actividades)`: Determina el estado de cada actividad para el grupo del alumno (entregada, evaluada, pendiente).
    * `calendarioDelPeriodo($estudiante, $semestre, $agno)`: Reúne todas las fechas de entrega del semestre en un único flujo de calendario.
* **Modelos Involucrados**:
  * `App\Models\Curso\Curso`
  * `App\Models\Curso\Componente`
  * `App\Models\Curso\InscripcionCurso`
  * `App\Models\Curso\InscripcionComponente`
  * `App\Models\Curso\Asistencia`
* **Políticas**:
  * `App\Policies\CursoPolicy`
  * `App\Policies\InscripcionCursoPolicy` (`view`: el estudiante únicamente puede ver su propia inscripción).
* **Capacidades del Estudiante**:
  * Consultar cursos históricos o vigentes filtrando por semestre y año.
  * Acceder a la ficha de curso mediante pestañas con carga diferida (`Inertia::lazy`):
    * Pestaña Rendimiento: Consulta de notas por componente, desglose de exigencia y porcentaje de asistencia.
    * Pestaña Calendario: Fechas clave de entrega del curso y del semestre.
    * Pestaña Mensajería y Bitácora (ver dominios específicos más abajo).

---

### Dominio 4: Programa Académico y Syllabus

Permite conocer los objetivos de aprendizaje, contenidos, bibliografía y reglas de evaluación oficiales (`/estudiante/cursos/{curso}/programa`).

* **Controladores**:
  * `App\Http\Controllers\Student\ProgramaController`
  * Embebido directamente en `CourseController::show`
* **Servicios y Presentadores**:
  * `App\Services\Student\StudentSyllabusPresenter`:
    * Filtra y selecciona el syllabus visible para el alumno: prioriza `APROBADO`, con fallback a `BASICO_COMPLETO`.
    * Resuelve los docentes específicos asignados a la sección/componente donde está inscrito el alumno (no los del curso en general).
    * Deserializa y normaliza el payload JSONB (`data_syllabus`).
  * `App\Traits\ParsesSyllabus`:
    * Parsea y transforma las secciones estructuradas del syllabus para su despliegue en la interfaz Svelte.
  * Estructuras en `App\Syllabus\Secciones\*`: `ComponenteEvaluacion`, `RecursoSyllabus`, `SeccionVIICompleto`, `TituloItem`, `UnidadSyllabus`.
* **Modelos Involucrados**:
  * `App\Models\Curso\Programa`
  * `App\Models\Curso\Curso`
  * `App\Models\Curso\Componente`
* **Políticas**:
  * `App\Policies\ProgramaPolicy`
* **Capacidades del Estudiante**:
  * Leer el programa íntegro de la asignatura (Identificación, Presentación, Competencias, Unidades de Aprendizaje, Recursos Bibliográficos, Metodología y Normativa).
  * Verificar las reglas de ponderación de componentes y si la aprobación de cierto componente es obligatoria.

---

### Dominio 5: Actividades Evaluativas, Grupos y Calificaciones

Controla la interacción con las evaluaciones del curso (`/estudiante/cursos/{curso}/actividad/{actividad}`).

* **Controladores**:
  * `App\Http\Controllers\Student\ActivityController`
* **Servicios**:
  * `App\Services\Agenda\LecturaAgendaService`:
    * Registra y consulta la confirmación de lectura ("Visto por...") en hilos de entrega sin marcar como leído en simples vistas previas.
* **Modelos Involucrados**:
  * `App\Models\Agenda\Actividad`
  * `App\Models\Agenda\ActividadAsignadaGrupo`
  * `App\Models\Agenda\IntegranteGrupo`
  * `App\Models\Agenda\Rubrica`
  * `App\Models\Agenda\Evaluacion`
  * `App\Models\Operaciones\Archivo`
* **Enums**:
  * `App\Enums\DB\TipoActividad`: `SUMATIVA` vs `FORMATIVA`.
  * `App\Enums\DB\TipoMensaje`: `MENSAJE_AL_PROFESOR`, `ENTREGA_DE_ARCHIVO`, `FEEDBACK`, `EVALUACIÓN`, `CANCELACIÓN_DE_ENTREGA`.
* **Capacidades del Estudiante**:
  * Ver el enunciado de la actividad, fechas límites y tipo de entrega (individual o grupal).
  * Descargar el archivo adjunto del enunciado (`ActivityController::descargarEnunciado`).
  * Conocer la conformación de su grupo de trabajo y compañeros integrantes.
  * Consultar la rúbrica vigente asignada a la actividad.
  * Si la actividad fue evaluada:
    * Ver la rúbrica evaluada (puntajes y niveles asignados).
    * Ver la nota final del grupo y la nota individual propia (con ajuste por décimas otorgadas por el docente en `integrante_grupo.diferencia_decimas`).
    * Descargar el archivo específico que fue evaluado (`ActivityController::descargarEntrega`).

---

### Dominio 6: Agenda, Entregas y Almacenamiento Seguro

Canal de entrega de trabajos y diálogo directo sobre la actividad (`/estudiante/grupos-asignados/{grupo}/...`).

* **Controladores**:
  * `App\Http\Controllers\Student\AgendaController`
* **Form Requests**:
  * `App\Http\Requests\Archive\AgendaFileRequest`: Valida el archivo entrante antes de procesarlo.
* **Manejadores y Pipeline de Archivos**:
  * `App\Services\Archive\Handlers\AgendaArchiveHandler`:
    * Construye rutas deterministas: `agenda/{curso}/{componente}/{actividad}/{fecha}/{estudiantes}/`.
    * Genera nombres seguros sin exponer extensiones no validadas.
  * `App\Services\Archive\AgendaArchiveService`:
    * Ejecuta validación MIME, límites de tamaño (`FileRequirementBuilder`), compresión y persistencia en disco de almacenamiento.
* **Modelos Involucrados**:
  * `App\Models\Agenda\Agenda`
  * `App\Models\Agenda\ActividadAsignadaGrupo`
  * `App\Models\Agenda\IntegranteGrupo`
  * `App\Models\Operaciones\Archivo`
  * `App\Models\Agenda\LecturaAgenda`
* **Excepciones de Control**:
  * `FileValidationException`, `VirusDetectedException`, `CompressionException`, `StorageException`, `ArchiveException`.
* **Capacidades y Restricciones del Estudiante**:
  * **Enviar Consultas**: Puede remitir dudas categorizadas (`Consulta`, `Duda sobre Rúbrica`, `Otro`) asociadas a la actividad.
  * **Subir Entrega (`storeEntrega`)**:
    * Sube archivos respetando el plazo límite (`fecha_limite + holgura_actividad + holgura_personal_grupo`).
    * **Bloqueo 1**: Si el grupo ya fue evaluado, el backend deniega cualquier nueva subida o reemplazo.
    * **Bloqueo 2**: La subida física y validación pesada ocurren fuera de la transacción de base de datos; luego se ejecuta una transacción corta con `lockForUpdate()` para evitar condiciones de carrera (doble envío).
  * **Cancelar Entrega (`destroyEntrega`)**:
    * Solo puede cancelar la entrega más reciente efectuada por su propio grupo.
    * No puede cancelar si la entrega ya fue evaluada o si el plazo ya venció.
    * No elimina físicamente el registro: marca el archivo con `pendiente_de_borrado = true` y crea un registro histórico en agenda con `tipo_mensaje = CANCELACIÓN_DE_ENTREGA`.
  * **Marcar Leída (`marcarLeida`)**: Marca los mensajes del docente como leídos en `agenda.lectura_agenda` al abrir el modal de agenda.

---

### Dominio 7: Bitácora Cronológica del Curso

Vista temporal unificada de todos los eventos del alumno en un curso (`/estudiante/cursos/{curso}?seccion=bitacora`).

* **Controladores**:
  * `App\Http\Controllers\Student\BitacoraController`: Redirige a la sección bitácora de `CourseController::show`.
* **Servicios**:
  * `App\Services\Student\BitacoraCursoEstudiante`:
    * Reúne todas las filas de `agenda.agenda` de todos los grupos y actividades visibles del alumno en ese curso.
    * Proporciona un feed cronológico unificado (entregas, cancelaciones, retroalimentaciones, evaluaciones y dudas).
    * Filtra listas únicas de actividades y componentes para navegación rápida en la interfaz.
* **Capacidades del Estudiante**:
  * Examinar todo su historial académico dentro de un curso sin tener que ingresar actividad por actividad.

---

### Dominio 8: Mensajería por Componente (Avisos y Consultas)

Sistema de mensajería asíncrono a nivel de componente (`/estudiante/cursos/{curso}/mensajeria`). No confundir con la agenda de entregas.

* **Controladores**:
  * `App\Http\Controllers\Student\MensajeriaController`
* **Servicios**:
  * `App\Services\Student\MensajeriaCursoEstudiante`:
    * Gestiona la bandeja por curso: pestañas por componente (Cátedra, Taller, Laboratorio), conteo de no leídos y panel del componente activo.
  * `App\Services\MensajeriaService`:
    * Gestiona los canales en `curso.mensaje` e interacciones en `curso.interaccion_mensaje`.
* **Modelos Involucrados**:
  * `App\Models\Curso\Mensaje`
  * `App\Models\Curso\InteraccionMensaje`
  * `App\Models\Curso\Componente`
* **Enums**:
  * `App\Enums\DB\TipoMensajeCurso`:
    * `MENSAJE_PARA_TODO_EL_CURSO`: Difusión emitida por el docente a todos los alumnos del componente.
    * `MENSAJE_INDIVIDUAL`: Conversación privada sostenida entre el alumno y el equipo docente del componente.
* **Capacidades del Estudiante**:
  * Leer avisos y difusiones oficiales publicadas por el equipo docente de la sección.
  * Iniciar o responder conversaciones privadas con el equipo docente.
  * Elegir a qué docente dirigir el mensaje de cabecera; no obstante, todo el equipo docente del componente tiene visibilidad del canal para garantizar continuidad pedagógica.
  * Los mensajes se marcan automáticamente como leídos al abrir el panel del componente activo.

---

### Dominio 9: Integración Externa y Single Sign-On (SSO SGEQ)

Puente de identidad hacia el Sistema de Gestión y Préstamo de Equipos (`/sso/sgeq`).

* **Controladores**:
  * `App\Http\Controllers\Sso\SgeqSsoController`
* **Servicios**:
  * `App\Services\Sso\SgeqSsoService`:
    * Determina si el estudiante pertenece a una carrera autorizada configurada en `services.sgeq.carreras` (p. ej., Diseño Multimedia).
    * Emite un token JWT firmado mediante clave asimétrica RSA (`RS256`, con `App\Support\JwtRs256`).
    * El token transporta RUT normalizado, correo, nombres y rol `ALUMNO`.
    * Posee un identificador único `jti` (UUID) y TTL reducido (≤ 60 segundos) para un único uso (`one-time handshake`).
* **Capacidades del Estudiante**:
  * Saltar de forma transparente y sin doble autenticación desde el dashboard de UTAMED hacia el sistema de inventario y préstamo de equipos SGEQ si su carrera está autorizada.

---

## 4. Matriz de Capacidades y Restricciones del Estudiante

| Operación | Estado | Validación / Guardrail | Controlador / Servicio Responsable |
| :--- | :---: | :--- | :--- |
| **Ingreso al portal Estudiante** | Permitido | Middleware `IsStudent` verifica rol `'Estudiante'` | `IsStudent.php` |
| **Ver cursos inscritos** | Permitido | Inscripción activa `INSCRITO` en `InscripcionCurso` | `CourseController@index` |
| **Ver programa/syllabus** | Permitido | Programa en estado `APROBADO` o `BASICO_COMPLETO` | `ProgramaController@show` / `StudentSyllabusPresenter` |
| **Ver notas y asistencia** | Permitido | Limitado estrictamente a sus notas individuales y de grupo | `RendimientoCursoEstudiante::calcular` |
| **Ver promedio del curso** | Permitido | Dato puramente estadístico agregado y anónimo | `RendimientoCursoEstudiante::promedioPonderado` |
| **Descargar enunciado** | Permitido | Actividad visible perteneciente al curso inscrito | `ActivityController@descargarEnunciado` |
| **Subir entrega de tarea** | Permitido | Plazo vigente (holgura base + personal), grupo no evaluado | `AgendaController@storeEntrega` |
| **Cancelar entrega propia** | Permitido | Solo la última entrega activa, no evaluada y dentro de plazo | `AgendaController@destroyEntrega` |
| **Enviar mensaje al docente** | Permitido | Inscrito en el componente del mensaje | `MensajeriaController@enviar` |
| **Editar contacto personal** | Permitido | Regex en celular, formato RFC en correo, dominios autorizados en RRSS | `PerfilController@updateContacto` |
| **Cambiar datos de matrícula** | **Denegado** | De solo lectura institucional (origen Oracle Intranet) | Protegido en modelo `Usuario` y `Estudiante` |
| **Inscribir/desinscribir cursos** | **Denegado** | Exclusivo de administración / sincronización | `InscripcionCursoPolicy` |
| **Calificar o evaluar rúbricas** | **Denegado** | Exclusivo de Docentes y Ayudantes con permisos | `ActivityPolicy` / `EvaluacionPolicy` |
| **Ver entregas de otros grupos** | **Denegado** | Aislamiento por `IntegranteGrupo` y `ActividadAsignadaGrupo` | `ActivityController@show` |
| **Acceso a SGEQ** | Condicional | Permitido solo si `id_carrera` está habilitada en config | `SgeqSsoService@resolverRol` |

---

## 5. Recomendaciones Técnicas para Mantenedores del Backend

1. **Evitar consultas N+1 en datos compartidos**: Al añadir información al estudiante que deba verse en toda la plataforma, implementarlo en `UserCoursesService` o `HandleInertiaRequests` utilizando llamadas en lote (`whereIn`, `pluck`, `groupBy`) y closures diferidos para Inertia.
2. **Priorización de Docentes por Componente**: Para mostrar qué profesor le hace clases a un estudiante, **nunca** utilice directamente `curso.id_docente_titular`. Dicho campo es administrativo. Utilice la lógica de `StudentSyllabusPresenter::docentes()` o `DashboardController`, que resuelve los docentes asignados al componente específico en el que el estudiante está inscrito.
3. **Manejo de Transacciones en Entregas**: Mantenga siempre el procesamiento de archivos (`AgendaArchiveHandler::store`) fuera del bloque de transacción de base de datos para no saturar las conexiones de PostgreSQL durante la subida de archivos pesados.
4. **Fechas y Serialización**: La columna `fecha_limite` de actividades se almacena como `DATE` (sin hora). Al interactuar con Carbon, utilícese `serializeDate` con formato `'Y-m-d'` para prevenir desfases horarios entre el servidor UTC y los clientes en Chile continental (UTC-4 / UTC-3).
5. **Normalización de RUT**: Utilice siempre `App\Support\Rut::normalizar()` para cualquier búsqueda o emisión de tokens relacionados con el estudiante.
