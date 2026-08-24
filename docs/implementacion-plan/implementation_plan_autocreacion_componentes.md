# Plan de Implementación: Creación Híbrida de Componentes e Inscripción Automática desde Intranet

## Objetivo
Implementar una arquitectura **Híbrida Asistida (Intranet-First $\rightarrow$ Fallback UTAMED)** para la creación de componentes de cursos ofertados y la inscripción de alumnos, eliminando la selección manual redundante en formularios y **garantizando que ninguna discrepancia o fallo se omita silenciosamente**, reportando advertencias claras al usuario en el Frontend.

---

## User Review Required

> [!IMPORTANT]
> **Decisiones Arquitectónicas Confirmadas y Aclaradas:**
>
> 1. **Jerarquía Híbrida (Intranet First $\rightarrow$ Fallback UTAMED)**:
>    * **Paso 1 (Intranet First)**: Al configurar un curso (Asignatura, Año, Semestre, Sección/Grupo), el sistema consulta Oracle `CARRERA_CURSO`. Si existen registros, detecta las componentes oficiales de la oferta semestral (`Cátedra`, `Taller`, `Laboratorio`) con sus códigos de acta.
>    * **Paso 2 (Fallback UTAMED)**: Si no hay conexión o aún no existe oferta en Oracle para ese periodo, se autoderivan las componentes a partir de las horas de la `Asignatura` (`horas_catedra > 0`, `horas_taller > 0`, `horas_laboratorio > 0`).
>
> 2. **Cero Selección Manual de Componentes en Frontend**:
>    * El usuario **no** seleccionará qué componentes crear. El formulario solo mostrará las componentes auto-detectadas (con su badge de procedencia: *Detectado en Intranet* o *Plan de Estudios*) y el usuario se concentrará exclusivamente en **asignar los docentes responsables** a cada una.
>
> 3. **Cero Skip Silencioso (Avisos Visibles)**:
>    * Ninguna omisión se ignorará silenciosamente. Si una componente en Oracle no puede mapearse a UTAMED o un alumno presenta datos inconsistentes, se recolectará en una lista estructurada de `advertencias` que se notificará visiblemente al usuario en la interfaz (modal / toast de resumen).

---

## Proposed Changes

### Backend (Lógica de Negocio, Endpoints y DTOs)

#### 1. DTOs de Reporte y Feedback
- **[MODIFY] [`app/DTOs/External/ResultadoInscripcionAutomatica.php`](file:///c:/Users/dyri0n/Code/utamed/app/DTOs/External/ResultadoInscripcionAutomatica.php)**:
  - Agregar colección de `$advertencias` (array de strings o structs) para reportar componentes omitidas, alumnos con RUT inválido o discrepancias de grupo.
  - Agregar helper `toArray()` con serialización completa.
- **[NEW] [`app/DTOs/External/ResultadoSincronizacionComponentes.php`](file:///c:/Users/dyri0n/Code/utamed/app/DTOs/External/ResultadoSincronizacionComponentes.php)**:
  - DTO para el resultado de sincronización de componentes: `origen` (`INTRANET` vs `PLAN_ESTUDIO`), `componentes_creadas`, `componentes_existentes`, `advertencias`.

#### 2. Servicios de Negocio
- **[MODIFY] [`app/Services/IntranetService.php`](file:///c:/Users/dyri0n/Code/utamed/app/Services/IntranetService.php)**:
  - **Nuevo Método `sincronizarComponentes(Curso $curso, bool $inscribirAlumnos = false): ResultadoSincronizacionComponentes`**:
    - Consulta `resolverComponentesIntranet($curso)`.
    - Si encuentra en Oracle $\rightarrow$ Crea/asocia las componentes `Componente` en PostgreSQL con sus contextos.
    - Si Oracle está caído o vacío $\rightarrow$ Fallback a `$curso->asignacionPlan->asignatura` (horas cátedra, taller, lab) y añade advertencia informativa.
    - Si `$inscribirAlumnos = true`, encadena inmediatamente `inscribirAutomaticamente($curso)`.
  - **Refactorización de `inscribirAutomaticamente()`**:
    - Reemplazar el `Log::info("Skip silencioso...")` por registro explícito en `$resultado->advertencias[] = "La componente {$compIntranet->curso_tipo_asig->value} ({$compIntranet->cur_codigo}) no tiene equivalente configurado en UTAMED."`.
- **[MODIFY] [`app/Services/CursoService.php`](file:///c:/Users/dyri0n/Code/utamed/app/Services/CursoService.php)**:
  - Modificar `create()` para que las componentes se infieran automáticamente (vía `IntranetService` o horas de asignatura) sin exigir `id_tipo_componente_principal` obligatorio desde el payload del frontend.

#### 3. Controladores y Rutas API
- **[NEW/MODIFY] Endpoints en [`routes/admin.php`](file:///c:/Users/dyri0n/Code/utamed/routes/admin.php) y [`CursoController.php`](file:///c:/Users/dyri0n/Code/utamed/app/Http/Controllers/Admin/CursoController.php)**:
  - `GET /admin/intranet/cursos/preview-componentes`: Endpoint de preview para el wizard (recibe `id_asignatura`, `agno`, `semestre`, `letra_grupo` y retorna origen `INTRANET` o `PLAN` y lista de componentes estimadas).
  - `POST /admin/cursos/{id_curso}/sincronizar-intranet`: Sincroniza componentes e inscribe alumnos para un curso ya existente.

---

### Frontend (Páginas, Modales y Llamadas)

#### 1. Wizard de Creación de Curso
- **[MODIFY] [`resources/js/modules/resources/curso/components/cursoWizardModal.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/modules/resources/curso/components/cursoWizardModal.svelte)**:
  - **Eliminar sección manual**: Remover los botones de selección "¿Qué componentes tendrá este curso?" (`tipo-comp-section`).
  - **Carga reactiva de componentes**: Al seleccionar Asignatura + Año + Semestre + Grupo, invocar `/admin/intranet/cursos/preview-componentes`.
  - **Badge visual de origen**:
    - 🟢 *Detectado en Intranet (Oracle)*
    - 🔵 *Derivado de Horas del Plan de Estudios*
  - **Asignación docente enfocada**: Mostrar la lista de componentes detectadas y permitir seleccionar el docente titular para cada una.

#### 2. Vista de Gestión de Cursos
- **[MODIFY] [`resources/js/pages/admin/Cursos.svelte`](file:///c:/Users/dyri0n/Code/utamed/resources/js/pages/admin/Cursos.svelte)**:
  - Botón de acción rápida: *"Sincronizar con Intranet"* por curso o masivo.
  - Modal/Toast de reporte de sincronización:
    - Cantidad de alumnos inscritos.
    - Componentes vinculadas.
    - Lista desplegable de **Advertencias** (si alguna componente o alumno requiere atención manual).

---

## Verification Plan

### Automated Tests (Pest / PHPUnit)
- `tests/Unit/Services/IntranetServiceSincronizacionComponentesTest.php`:
  - Prueba de sincronización con componentes desde Intranet.
  - Prueba de fallback a horas de `Asignatura` cuando Oracle no retorna datos.
  - Prueba de reporte de advertencias (cero skips silenciosos).
- `tests/Feature/CursoCreacionHibridaTest.php`:
  - Creación de curso con componentes automáticas y docentes asignados.
- `tests/Integration/External/Intranet03_InscripcionAutomaticaRealTest.php`:
  - Validar flujo completo con Oracle real.

### Manual Verification
1. Abrir el Wizard de Creación de Cursos en `/admin/cursos`.
2. Seleccionar una carrera y asignatura con Cátedra + Taller.
3. Verificar que las componentes se cargan automáticamente con su badge correspondiente.
4. Asignar docentes y marcar "Inscribir automáticamente a los alumnos".
5. Confirmar que el curso se crea, las componentes quedan creadas en BD y el reporte muestra el resumen con advertencias visibles.
