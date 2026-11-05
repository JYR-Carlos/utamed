# Plan de Implementación: APP-11 · Consulta de Calificaciones, Rúbrica Evaluada y Feedback Docente

**Tarjeta Trello:** [APP-11 · [5 pts] Consulta de Calificaciones, Rúbrica Evaluada y Feedback Docente](https://trello.com/c/jAcPpymT/150-app-11-5-pts-consulta-de-calificaciones-r%C3%BAbrica-evaluada-y-feedback-docente)  
**Estimación:** 5 puntos Fibonacci  
**Release:** R3 (Actividades, Entregas y Mensajero)  
**Rama Git:** `app-movil` (o sugerida `feature/app-api-actividades-entregas-mensajero`)  
**Documento Backlog:** `docs/backlogs/2026-10-29_modulo_api_estudiante_app_movil.md`  
**Estado del plan:** Aprobado / Listo para desarrollo (2026-11-05)

---

## 1. Descripción del Objetivo

Desarrollar y exponer los endpoints del dominio de **Evaluación de Actividades** para estudiantes en la API móvil (`App\Api\V1\Controllers\Student\Activity`), permitiendo a los alumnos consultar la retroalimentación docente de actividades calificadas (nota grupal, ajuste individual de décimas, nota individual final, comentario del profesor, evaluador, fecha), el desglose estructurado de la rúbrica evaluada (criterios, escalas seleccionadas, puntajes obtenidos) y la descarga binaria segura de la entrega que fue calificada con guardrails estrictos de pertenencia grupal y anti-IDOR.

### Contexto y Alcance
En el modelo académico de UTAMED:
1. **Actividades Sumativas vs. Formativas**:
   - **Sumativas**: Se evalúan con nota numérica de 1.0 a 7.0 (`actividad_asignada_grupo.nota`). Cada estudiante del grupo puede tener un ajuste individual de décimas (`integrante_grupo.diferencia_decimas`, ej: `+0.3` o `-0.2`), resultando en una nota individual personalizada (`integrante_grupo.nota_individual = round(max(1.0, min(7.0, nota_grupal + diferencia_decimas)), 1)`).
   - **Formativas**: No conllevan nota numérica; se evalúan con una apreciación cualitativa (`evaluacion.evaluacion_obtenida`: `'Bueno'`, `'Regular'`, `'Malo'`) y mensaje de retroalimentación obligatorio.
2. **Modelo de Evaluación y Rúbricas (`agenda.evaluacion` y `agenda.agenda`)**:
   - Cuando el docente califica, se genera un registro en `agenda.agenda` con `tipo_mensaje = EVALUACIÓN`, mensaje de feedback (`mensaje`), y fecha de envío.
   - Vinculado a este mensaje existe `agenda.evaluacion` (`id_agenda`), que registra el puntaje obtenido (`puntaje_obtenido`), el resultado de la rúbrica (`resultado`: mapa JSON `{ "<id_nivel>": "<id_escala>" }`), la rúbrica utilizada (`id_rubrica`) y el evaluador (`id_usuario_evaluador`).
   - Si la actividad involucró una entrega de archivo por parte de los alumnos, la fila de `EVALUACIÓN` copia el `uuid_archivo_subido` de la entrega calificada.
3. **Descarga de la Entrega Calificada**:
   - Los estudiantes deben poder descargar el archivo que entregaron y que fue objeto de evaluación.
   - Dicha descarga debe estar restringida **estrictamente a los integrantes del grupo** al que pertenece la entrega (`IntegranteGrupo`), bloqueando cualquier intento de descarga por parte de alumnos de otros grupos o cursos (Anti-IDOR y Privacidad).

---

## 2. Criterios de Aceptación (DoD)

- [x] **DoD #1: Calificaciones Grupales e Individuales Ajustadas por Décimas**:
  - Retorna `nota_grupal` y `nota_individual` propia del estudiante autenticado.
  - Informa el ajuste específico de décimas (`diferencia_decimas`), explicando la discrepancia entre la nota del grupo y la individual.
  - Para formativas, omite notas numéricas y expone `evaluacion_cualitativa` (`Bueno`, `Regular`, `Malo`).
  - Incluye metadatos de retroalimentación: comentario docente (`retroalimentacion`), nombre del evaluador (`docente_evaluador`) y fecha de evaluación (`fecha_evaluacion`).
- [x] **DoD #2: Desglose Estructurado de la Rúbrica Evaluada**:
  - Identifica la rúbrica efectivamente utilizada en la evaluación (`id_rubrica`, `estado_rubrica`).
  - Cruza el mapa de selección del docente (`evaluacion.resultado`) con los niveles y escalas de la rúbrica, entregando una lista enriquecida `criterios_evaluados`.
  - Cada criterio especifica: ID, nombre, descripción, ponderación, puntaje máximo, escala/nivel seleccionado con sus puntos y descriptor, y la lista completa de escalas con bandera `seleccionada: boolean`.
  - Informa totales métricos: `total_criterios`, `puntaje_maximo`, `puntaje_obtenido` y `porcentaje_logro`.
  - Si la actividad formativa o sumativa no utilizó rúbrica, responde con `rubrica: null`.
- [x] **DoD #3: Descarga Segura de Entrega Calificada (Solo Integrantes del Grupo)**:
  - Valida que el estudiante pertenezca al curso con matrícula activa (`INSCRITO`).
  - Valida pertenencia de la entrega a la actividad y al curso.
  - Verifica que el alumno solicitante sea miembro activo del grupo asignado a la entrega (`IntegranteGrupo`). Si un alumno de otro grupo intenta descargarla, responde con `403 Forbidden` (`{"message": "Esta entrega no te pertenece."}`).
  - Verifica la existencia física del archivo en el almacenamiento y responde con binario seguro (`Content-Type`, `Content-Disposition`, `X-Content-Type-Options: nosniff`).
- [x] **DoD #4: Manejo de Estados y Guardrails Anti-IDOR**:
  - Deniega con `401 Unauthorized` si no hay token Bearer Sanctum.
  - Deniega con `403 Forbidden` si el usuario no tiene rol/perfil `Estudiante`.
  - Deniega con `403 Forbidden` si no está inscrito en el curso (`is_enrolled_in_course`).
  - Responde con `404 Not Found` (`{"message": "Actividad no encontrada en este curso."}`) si la actividad pertenece a otro curso.
  - Responde con `403 Forbidden` (`{"message": "Esta actividad no está disponible."}`) si la actividad tiene `visible = false`.
  - Responde con `404 Not Found` (`{"message": "Esta actividad aún no ha sido evaluada."}`) si se solicita la evaluación de una actividad pendiente.

---

## 3. Decisiones de Diseño y Arquitectura

> [!IMPORTANT]
> **Single-Action Controllers en el Dominio `Student/Activity`**:
> Conforme a la regla arquitectónica de `AGENTS.md` (reglas 1, 2 y 3):
> - Los controladores del módulo API móvil son invokables (`__invoke`).
> - Se organizan dentro de `App\Api\V1\Controllers\Student\Activity\`:
>   1. `ShowActivityEvaluationController.php`: para la consulta de calificaciones, rúbrica evaluada y feedback (`GET .../evaluacion`).
>   2. `DownloadActivitySubmissionController.php`: para la descarga de archivos entregados (`GET .../entregas/{agenda}/descargar`).
> - Las rutas en `routes/api.php` se declaran limpiamente sin sintaxis de arreglo, manteniéndose desacopladas de las carpetas internas.

> [!IMPORTANT]
> **Separación de Responsabilidades y Servicio de Dominio**:
> Toda la lógica de consulta de base de datos, cruce de notas con décimas, parsing y resolución de rúbrica evaluada, y verificación perimetral de archivos se implementa en métodos dedicados dentro de `App\Services\Student\ActividadesCursoEstudiante`:
> - `obtenerEvaluacion(Curso $curso, Actividad $actividad, Estudiante $estudiante): array`
> - `descargarEntrega(Curso $curso, Actividad $actividad, Agenda $agenda, Estudiante $estudiante, bool $enLinea = false): BinaryFileResponse`

> [!NOTE]
> **Tratamiento de Actividades Aún No Evaluadas**:
> Si un cliente móvil solicita el endpoint `GET .../evaluacion` para una actividad que aún no cuenta con evaluación registrada por el docente, la API aborta de forma semántica con `404 Not Found` (`{"message": "Esta actividad aún no ha sido evaluada."}`). Cabe destacar que en el listado de actividades (`GET .../actividades`, APP-10), cada actividad ya incluye el indicador booleano `grupo.ya_evaluada: boolean`, permitiendo a la app móvil deshabilitar o no mostrar el botón de ver evaluación si la actividad no ha sido calificada.

> [!NOTE]
> **Estructura Desglosada vs. Raw de la Rúbrica**:
> Para facilitar la renderización en clientes móviles (Flutter/React Native/Swift) sin exigir algoritmos complejos de cruce matricial en el cliente, el backend precalcula `criterios_evaluados` con la escala seleccionada directamente resuelta, junto a los puntos obtenidos y el porcentaje de logro, manteniendo a la vez la estructura completa de escalas.

---

## 4. Especificación de Endpoints y Payloads JSON

### Endpoint 1: Consulta de Calificaciones, Rúbrica Evaluada y Feedback Docente
- **Método y URI:** `GET /api/v1/estudiante/cursos/{curso}/actividades/{actividad}/evaluacion`
- **Nombre de Ruta:** `api.v1.estudiante.cursos.actividades.evaluacion`
- **Middleware:** `['auth:sanctum', 'is_student_api', 'force_password_change_api', 'is_enrolled_in_course']`
- **Respuesta Exitosa (200 OK) - Actividad Sumativa con Rúbrica y Entrega Evaluada:**
```json
{
  "data": {
    "curso": {
      "id_curso": 101,
      "nombre": "Medicina Interna I",
      "cod_curso": "MED-101-1"
    },
    "actividad": {
      "id_actividad": 501,
      "nombre": "Caso Clínico Semanal 1",
      "tipo_actividad": "SUMATIVA",
      "es_sumativa": true,
      "es_grupal": true,
      "ponderacion": 15.0,
      "exigencia": 60.0
    },
    "grupo": {
      "id_actividad_asignada_grupo": 201,
      "nombre_grupo": "Grupo 1",
      "es_individual": false
    },
    "calificacion": {
      "nota_grupal": 6.2,
      "diferencia_decimas": 0.3,
      "nota_individual": 6.5,
      "es_aprobatoria": true,
      "evaluacion_cualitativa": null,
      "puntaje_obtenido": 36.0,
      "puntaje_maximo": 40.0,
      "porcentaje_logro": 90.0
    },
    "feedback": {
      "id_evaluacion": 88,
      "fecha_evaluacion": "2026-11-04 15:30:00",
      "docente_evaluador": "DR. ROBERTO SILVA",
      "retroalimentacion": "Excelente análisis del diagnóstico diferencial y manejo farmacológico."
    },
    "rubrica": {
      "id_rubrica": 12,
      "estado_rubrica": "CERRADA",
      "total_criterios": 2,
      "puntaje_maximo": 40.0,
      "puntaje_obtenido": 36.0,
      "porcentaje_logro": 90.0,
      "criterios_evaluados": [
        {
          "id_criterio": "niv1",
          "nombre": "Diagnóstico Diferencial",
          "descripcion": "Precisión y fundamentación diagnóstica",
          "ponderacion": 50.0,
          "puntaje_maximo": 20.0,
          "puntaje_obtenido": 20.0,
          "escala_seleccionada": {
            "id_escala": "esc2",
            "nombre_nivel": "Destacado",
            "puntos": 20.0,
            "criterio": "Identifica y fundamenta exhaustivamente"
          },
          "escalas": [
            {
              "id_escala": "esc1",
              "nombre_nivel": "Insuficiente",
              "puntos": 10.0,
              "criterio": "No fundamenta adecuadamente",
              "seleccionada": false
            },
            {
              "id_escala": "esc2",
              "nombre_nivel": "Destacado",
              "puntos": 20.0,
              "criterio": "Identifica y fundamenta exhaustivamente",
              "seleccionada": true
            }
          ]
        }
      ]
    },
    "entrega_evaluada": {
      "id_agenda": 777,
      "uuid_archivo": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "nombre_original": "informe_caso_clinico_g1.pdf",
      "mime_type": "application/pdf",
      "peso_bytes": 1048576,
      "fecha_envio": "2026-11-02 23:15:00",
      "url_descarga": "http://localhost/api/v1/estudiante/cursos/101/actividades/501/entregas/777/descargar"
    }
  }
}
```

- **Respuesta Exitosa (200 OK) - Actividad Formativa Cualitativa (Sin Rúbrica ni Nota Numérica):**
```json
{
  "data": {
    "curso": {
      "id_curso": 101,
      "nombre": "Medicina Interna I",
      "cod_curso": "MED-101-1"
    },
    "actividad": {
      "id_actividad": 502,
      "nombre": "Taller Formativo de ECG",
      "tipo_actividad": "FORMATIVA",
      "es_sumativa": false,
      "es_grupal": false,
      "ponderacion": null,
      "exigencia": null
    },
    "grupo": {
      "id_actividad_asignada_grupo": 202,
      "nombre_grupo": "JUAN PÉREZ",
      "es_individual": true
    },
    "calificacion": {
      "nota_grupal": null,
      "diferencia_decimas": 0.0,
      "nota_individual": null,
      "es_aprobatoria": true,
      "evaluacion_cualitativa": "Bueno",
      "puntaje_obtenido": null,
      "puntaje_maximo": null,
      "porcentaje_logro": null
    },
    "feedback": {
      "id_evaluacion": 89,
      "fecha_evaluacion": "2026-11-04 16:00:00",
      "docente_evaluador": "DRA. MARÍA ROJAS",
      "retroalimentacion": "Buen reconocimiento de las ondas patológicas en derivaciones precordiales."
    },
    "rubrica": null,
    "entrega_evaluada": null
  }
}
```

---

### Endpoint 2: Descarga de Archivo de Entrega Calificada
- **Método y URI:** `GET /api/v1/estudiante/cursos/{curso}/actividades/{actividad}/entregas/{agenda}/descargar`
- **Query Params opcionales:** `ver=1` (para previsualización en navegador / webview cuando el mime type es soportado)
- **Nombre de Ruta:** `api.v1.estudiante.cursos.actividades.entregas.descargar`
- **Middleware:** `['auth:sanctum', 'is_student_api', 'force_password_change_api', 'is_enrolled_in_course']`
- **Respuesta Exitosa (200 OK):**
  - Stream binario de archivo (`BinaryFileResponse`).
  - Encabezados:
    - `Content-Type: <mime_type>` (ej. `application/pdf`, `image/jpeg`).
    - `Content-Disposition: attachment; filename="<nombre_original>"` (o `inline` si `ver=1`).
    - `X-Content-Type-Options: nosniff`.
- **Respuestas de Error:**
  - `401 Unauthorized`: sin token Sanctum válido.
  - `403 Forbidden`: `{"message": "Solo los estudiantes pueden acceder a este recurso."}`
  - `403 Forbidden`: `{"message": "No estás inscrito en este curso."}`
  - `403 Forbidden`: `{"message": "Esta entrega no te pertenece."}` (si el alumno pertenece a otro grupo).
  - `404 Not Found`: `{"message": "Actividad no encontrada en este curso."}`
  - `404 Not Found`: `{"message": "Entrega no encontrada en esta actividad."}`
  - `404 Not Found`: `{"message": "No hay archivo asociado a esta entrega."}`
  - `404 Not Found`: `{"message": "El archivo no existe en el servidor."}`

---

## 5. Cambios Propuestos en el Código

### Componente: Servicio de Dominio (`App\Services\Student`)

#### [MODIFY] [ActividadesCursoEstudiante.php](file:///home/chopancho/git_repos/utamed/app/Services/Student/ActividadesCursoEstudiante.php)
- Implementar el método público `obtenerEvaluacion(Curso $curso, Actividad $actividad, Estudiante $estudiante): array`:
  - Llama a `validarAccesoActividad($curso, $actividad)`.
  - Resuelve el grupo del estudiante para la actividad (`IntegranteGrupo` y `ActividadAsignadaGrupo`).
  - Si no existe grupo asignado, aborta con `404 ('No tienes un grupo asignado para esta actividad.')`.
  - Consulta las agendas del grupo con `tipo_mensaje = EVALUACIÓN` que tengan relación con `Evaluacion`, ordenadas de forma descendente por fecha/id para obtener la evaluación más reciente.
  - Si no existe evaluación ni nota registrada en el grupo o en el integrante, aborta con `404 ('Esta actividad aún no ha sido evaluada.')`.
  - Extrae y formatea:
    - `nota_grupal`, `diferencia_decimas`, `nota_individual = round(max(1.0, min(7.0, nota_grupal + decimas)), 1)`.
    - `evaluador` (nombre del docente emisor) y `retroalimentacion` (mensaje de agenda).
    - `evaluacion_cualitativa` y `puntaje_obtenido`.
  - Llama al helper privado `desglosarRubricaEvaluada(?Rubrica $rubrica, ?array $resultado, ?float $puntajeObtenido)` para estructurar los criterios, descriptores y escalas.
  - Resuelve los metadatos y la URL firmada/de descarga para la entrega evaluada (si la evaluación tiene `uuid_archivo_subido`).
- Implementar el método público `descargarEntrega(Curso $curso, Actividad $actividad, Agenda $agenda, Estudiante $estudiante, bool $enLinea = false): BinaryFileResponse`:
  - Valida pertenencia de actividad al curso y visibilidad.
  - Carga relación `actividadAsignadaGrupo` de `$agenda` y verifica que pertenezca a `$actividad`. Si no coincide, aborta `404`.
  - Verifica que `$estudiante` sea integrante del grupo correspondiente en `IntegranteGrupo`. Si no es miembro, aborta `403 ('Esta entrega no te pertenece.')`.
  - Carga `$agenda->archivo`. Si no tiene archivo o `uuid_archivo_subido` es nulo, aborta `404`.
  - Verifica existencia física en disco (`Storage::disk($disk)->exists($rutaFisica)`). Si no existe, aborta `404`.
  - Retorna `BinaryFileResponse` mediante `$agenda->archivo->respuestaHttp($rutaAbsoluta, $enLinea)`.

---

### Componente: Controladores API (`App\Api\V1\Controllers\Student\Activity`)

#### [NEW] [ShowActivityEvaluationController.php](file:///home/chopancho/git_repos/utamed/app/Api/V1/Controllers/Student/Activity/ShowActivityEvaluationController.php)
- Single-Action Controller que implementa `__invoke(Request $request, Curso $curso, Actividad $actividad): ActivityEvaluationResource`.
- Inyecta `ActividadesCursoEstudiante`.
- Obtiene `$estudiante = $request->user()->estudiante`.
- Invoca `$this->service->obtenerEvaluacion($curso, $actividad, $estudiante)`.
- Retorna `new ActivityEvaluationResource($datos)`.

#### [NEW] [DownloadActivitySubmissionController.php](file:///home/chopancho/git_repos/utamed/app/Api/V1/Controllers/Student/Activity/DownloadActivitySubmissionController.php)
- Single-Action Controller que implementa `__invoke(Request $request, Curso $curso, Actividad $actividad, Agenda $agenda): BinaryFileResponse`.
- Inyecta `ActividadesCursoEstudiante`.
- Lee el parámetro booleano `ver` (`$request->boolean('ver')`).
- Invoca `$this->service->descargarEntrega($curso, $actividad, $agenda, $request->user()->estudiante, $enLinea)`.
- Retorna directamente la respuesta binaria.

---

### Componente: Recursos JSON (`App\Api\V1\Resources\Student\Activity`)

#### [NEW] [ActivityEvaluationResource.php](file:///home/chopancho/git_repos/utamed/app/Api/V1/Resources/Student/Activity/ActivityEvaluationResource.php)
- Serializa la estructura unificada con las claves: `curso`, `actividad`, `grupo`, `calificacion`, `feedback`, `rubrica` y `entrega_evaluada`.

---

### Componente: Rutas API (`routes/api.php`)

#### [MODIFY] [routes/api.php](file:///home/chopancho/git_repos/utamed/routes/api.php)
- Importar los nuevos controladores:
  ```php
  use App\Api\V1\Controllers\Student\Activity\ShowActivityEvaluationController;
  use App\Api\V1\Controllers\Student\Activity\DownloadActivitySubmissionController;
  ```
- Registrar las dos rutas dentro del grupo con prefijo `estudiante`:
  ```php
  Route::get('cursos/{curso}/actividades/{actividad}/evaluacion', ShowActivityEvaluationController::class)
      ->middleware('is_enrolled_in_course')
      ->name('api.v1.estudiante.cursos.actividades.evaluacion');

  Route::get('cursos/{curso}/actividades/{actividad}/entregas/{agenda}/descargar', DownloadActivitySubmissionController::class)
      ->middleware('is_enrolled_in_course')
      ->name('api.v1.estudiante.cursos.actividades.entregas.descargar');
  ```

---

### Componente: Pruebas Automatizadas Pest (`tests/Feature/Api/V1/Student`)

#### [NEW] [ActivityEvaluationApiTest.php](file:///home/chopancho/git_repos/utamed/tests/Feature/Api/V1/Student/ActivityEvaluationApiTest.php)
- Implementar suite exhaustiva de pruebas funcionales y de seguridad con Pest:
  1. `rechaza con 401 si no hay token de autenticación`.
  2. `rechaza con 403 si el usuario no tiene rol o perfil estudiante`.
  3. `rechaza con 403 Forbidden si el estudiante no está inscrito en el curso`.
  4. `rechaza con 404 Not Found si la actividad pertenece a otro curso (anti-IDOR)`.
  5. `rechaza con 403 Forbidden si la actividad tiene visible = false`.
  6. `retorna 404 si el estudiante no tiene un grupo asignado en la actividad`.
  7. `retorna 404 si la actividad aún no ha sido evaluada`.
  8. `retorna 200 con evaluación sumativa, nota grupal y nota individual con ajuste de décimas (+0.3)`.
  9. `retorna 200 con rúbrica evaluada desglosando criterios, escalas seleccionadas y puntos`.
  10. `retorna 200 con evaluación formativa cualitativa (Bueno/Regular/Malo) sin nota numérica`.
  11. `retorna 200 con entrega evaluada asociada y URL de descarga`.
  12. `permite descargar el archivo de entrega calificada con encabezados correctos`.
  13. `rechaza con 403 Forbidden la descarga de entrega si el alumno pertenece a otro grupo del curso`.
  14. `retorna 404 al descargar entrega si el archivo no existe en el disco`.

---

## 6. Plan de Verificación

### Pruebas Automatizadas
Ejecutar la suite completa de pruebas específicas con Pest asegurando el entorno de base de datos (`DB_PORT=15432`):
```bash
DB_PORT=15432 php artisan test tests/Feature/Api/V1/Student/ActivityEvaluationApiTest.php
```
Ejecutar también las pruebas de actividades previas para confirmar que no haya regresiones:
```bash
DB_PORT=15432 php artisan test tests/Feature/Api/V1/Student/CourseActivitiesApiTest.php
```

### Verificación Manual
1. Generar token Bearer para un estudiante de prueba mediante `POST /api/v1/auth/login`.
2. Realizar petición `GET /api/v1/estudiante/cursos/{curso}/actividades/{actividad}/evaluacion` para una actividad calificada y corroborar:
   - Que `calificacion.nota_individual` refleje la suma de `nota_grupal` + `diferencia_decimas`.
   - Que la rúbrica marque exactamente el nivel calificado con `seleccionada: true`.
   - Que `entrega_evaluada.url_descarga` sea válida.
3. Descargar el archivo con el token Bearer del alumno y verificar la recepción del binario.
4. Intentar descargar el archivo con un alumno de otro grupo y verificar el rechazo inmediato con `403 Forbidden`.

---

## 7. Decisiones de Negocio y Arquitectura Cerradas

> [!NOTE]
> **Decisión #1: Comportamiento ante Actividades No Evaluadas (`404 Not Found`)**:
> - **Definición acordada:** Debe retornar `404 Not Found` (`{"message": "Esta actividad aún no ha sido evaluada."}`). No corresponde exponer el subrecurso de evaluación si la actividad aún no ha sido calificada. En el listado de actividades (`GET .../actividades`, APP-10), la app móvil ya cuenta con la bandera booleana `ya_evaluada` para controlar la navegación.
