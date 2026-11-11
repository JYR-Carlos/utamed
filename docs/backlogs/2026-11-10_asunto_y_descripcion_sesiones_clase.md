# Backlog de Trabajo: Asunto y Descripcion en Sesiones de Clase

* **Fecha**: 2026-11-10
* **Rama Base**: `main`
* **Metodologia**: Triada `OBSERVACIONES` > `INTERPRETACION E INVESTIGACION` > `PLANIFICACION`

---

## 1. Observaciones

Lista estructurada a partir de la inspeccion empirica directa del codigo fuente y contraste con el requerimiento:

### Modulo Asistencia Docente: Atributos Asunto y Descripcion en Sesiones de Clase
* **Elemento / Contexto**: Pantalla de asistencia docente (`resources/js/pages/docente/components/AsistenciaPanel.svelte`) y controlador REST/Inertia (`app/Http/Controllers/Docente/AsistenciaController.php`).
* **Problema reportado**: El profesor necesita adjuntar un asunto (tema de la clase) y una descripcion (notas u observaciones pedagogicas) al crear o editar sesiones de clase. El modelo de datos ya cuenta con las columnas correspondientes en `curso.clase`, pero ni el backend ni el frontend las implementan en sus flujos operativos.
* **Evidencia en codigo**:
  - `database/migrations/22_add_asunto_y_descripcion_to_curso_clase.php`: Migracion aplicada que añade `asunto varchar(150)` y `descripcion text` opcionales a `curso.clase`.
  - `app/Models/Base/Curso/BaseClase.php`: Declara `$fillable = ['dia', 'hora_inicio', 'hora_fin', 'id_componente', 'asunto', 'descripcion']`.
  - `app/Http/Controllers/Docente/AsistenciaController.php:376`: El metodo `validarSesion()` solo valida `dia`, `hora_inicio`, `hora_fin` y el array `asistencias`. Descarta cualquier valor enviado para `asunto` o `descripcion`.
  - `app/Http/Controllers/Docente/AsistenciaController.php:185`: `store()` crea `Clase` omitiendo `asunto` y `descripcion`.
  - `app/Http/Controllers/Docente/AsistenciaController.php:229`: `update()` solo actualiza `dia`, `hora_inicio` y `hora_fin`.
  - `app/Http/Controllers/Docente/AsistenciaController.php:137`: `index()` mapea las sesiones hacia JSON sin incluir `asunto` ni `descripcion`.
  - `resources/js/pages/docente/components/AsistenciaPanel.svelte:45`: La interfaz `Sesion` carece de `asunto` y `descripcion`.
  - `resources/js/pages/docente/components/AsistenciaPanel.svelte:70-220`: El formulario de registro/edicion solo contiene inputs para `fDia`, `fInicio` y `fFin`.

---

## 2. Interpretacion e Investigacion

### Contexto Estructural y Causa Raiz
Tras la ejecucion del refactor de asistencia (`FEAT-11` a `FEAT-14`), las sesiones de clase pasaron de ser una tupla implicita duplicada en `curso.asistencia` a convertirse en una entidad relacional explicita de primer orden: `curso.clase`.

Como consecuencia natural de esta evolucion arquitectonica, el modelo fue preparado mediante la migracion 22 con los atributos `asunto` y `descripcion`. Sin embargo, la capa de aplicacion quedo desacoplada de la evolucion del modelo relacional:
1. El controlador `AsistenciaController` opera como un cuello de botella donde las peticiones entrantes son filtradas estrictamente sin admitir los nuevos campos.
2. El frontend mantiene un contrato estricto que ignora las capacidades del modelo subyacente, impidiendo al docente llevar un registro tematico y pedagogico de lo impartido en cada sesion de aula o laboratorio.

---

## 3. Planificacion (Releases y Tablero Kanban)

### Releases Tematicas
* **R3**: Soporte Tematico y Pedagogico de Sesiones de Clase.
* **Rama Git sugerida**:
  ```bash
  git checkout main
  git pull origin main
  git checkout -b feature/asunto-descripcion-sesiones-clase
  ```

### Tarjetas Kanban (Formato C Expandido)

* ### `[FEAT-15]` [3 pts] [R3] [P1] Asistencia / Clases — Adjuntar asunto y descripción a las sesiones de clase en backend y frontend
  * **Ruta**: `/docente/cursos/{curso}/componentes/{componente}/asistencia` y `/docente/cursos/{curso}?tab=asistencia`
  * **Archivos**:
    * `app/Http/Controllers/Docente/AsistenciaController.php`
    * `resources/js/pages/docente/components/AsistenciaPanel.svelte`
    * `resources/js/modules/resources/calendario/types.ts`
    * `tests/Feature/Docente/ClaseAsistenciaTest.php`
  * **Problema & Causa**: Las columnas `asunto` y `descripcion` ya existen en `curso.clase` y `BaseClase`, pero `AsistenciaController` las omite en validacion, persistencia, actualizacion y salida JSON; `AsistenciaPanel.svelte` no provee campos de entrada ni visualizacion para estos atributos.
  * **Criterios de Aceptacion (DoD)**:
    * [ ] `validarSesion()` en `AsistenciaController` valida `asunto` como `nullable|string|max:150` y `descripcion` como `nullable|string`.
    * [ ] `AsistenciaController::store` persiste `asunto` y `descripcion` en `curso.clase`.
    * [ ] `AsistenciaController::update` actualiza `asunto` y `descripcion` en `curso.clase`.
    * [ ] `AsistenciaController::index` retorna `asunto` y `descripcion` dentro de cada elemento de `sesiones`.
    * [ ] Interfaz `Sesion` en `AsistenciaPanel.svelte` y `CalendarSesion` en `calendario/types.ts` incorporan `asunto?: string | null` y `descripcion?: string | null`.
    * [ ] Formulario de toma/edicion de asistencia en `AsistenciaPanel.svelte` incluye inputs accesibles para Asunto (input de texto con longitud maxima 150) y Descripcion (textarea multilineal).
    * [ ] El listado de sesiones en `AsistenciaPanel.svelte` visualiza el asunto de la clase cuando esta disponible para facilitar la navegacion del docente.
    * [ ] Suite de pruebas `tests/Feature/Docente/ClaseAsistenciaTest.php` incluye cobertura para creacion, actualizacion y lectura de `asunto` y `descripcion`.
    * [ ] `npm run check` (`svelte-check`) y suite de tests de PHPUnit/Pest finalizan sin errores.

---

## 4. Metricas de Planificacion
* **Total de tarjetas**: 1
* **Puntos por release**: R3 = 3 pts
* **Total de puntos de historia estimados**: 3 pts
