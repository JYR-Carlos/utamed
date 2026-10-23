# Guía de Pruebas: Guardarraíles de Componentes e Integración con Intranet

Esta guía detalla los pasos de verificación manual y automatizada para validar el conjunto de guardarraíles implementados en la oferta académica de UTAMED:
- **FIX-02 / G-01**: Condicionamiento del trigger de BD según origen de inscripción y banner informativo en UI.
- **FEAT-07**: Blindaje contra componentes con 0 horas en creación y clonación de cursos; detección de componentes sobrantes locales.
- **TEST-01**: Cobertura de pruebas unitarias y de integración de guardarraíles.
- **FIX-03**: Autorrecuperación de `CUR_CODIGO` en componentes y observabilidad en tiempo real del estado de conexión con Intranet.

---

## 1. Preparación del Entorno

### 1.1 Migraciones de Base de Datos
Asegurarse de tener aplicadas todas las migraciones, incluyendo la migración del trigger de inscripción condicionado (`2026_10_21_211234_condicionar_trigger_autoinscripcion_por_origen.php`) y la columna `intranet_cur_codigo` en `curso.componente`:

```bash
php artisan migrate
```

### 1.2 Ejecución del Seeder de Demostración
Se ha dispuesto un seeder especializado que genera los escenarios exactos requeridos (asignatura monocomponente, curso con componente huérfana y alumnos de prueba):

```bash
php artisan db:seed --class=Database\\Seeders\\GuardarrailesDemoSeeder
```

**Datos creados por el seeder:**
- **Carrera / Plan**: Carrera 23 (*Diseño Multimedia*), Plan 2016.
- **Asignatura Monocomponente**: `DM-MONO` (*Taller Monocomponente de Prototipado*, 4 hrs Taller, 0 hrs Cátedra, 0 hrs Laboratorio).
- **Asignatura Regular**: `DM-DEMO` (*Diseño de Experiencia de Usuario*, 3 hrs Cátedra, 2 hrs Taller).
- **Curso con Componente Sobrante**: `DEMO Guardarraíles - UX Grupo A` (con Cátedra oficial #555101 y Laboratorio local huérfano sin acta).
- **Docente de prueba**: `33333333-3` (`demo_docente_guardarrail` / `password123`).
- **Alumnos de prueba**:
  - Manual: `11111111-1` (`demo_alumno_manual` / `password123`).
  - Federado: `22222222-2` (`demo_alumno_federado` / `password123`).

---

## 2. Casos de Prueba Manuales

### Caso 1: Autorrecuperación de CUR_CODIGO y Estado de Intranet en Asistente (FIX-03)
**Objetivo**: Verificar que el asistente de creación de curso consulta a Intranet, muestra el estado de la conexión en un chip visual y asocia automáticamente los códigos oficiales de acta (`CUR_CODIGO`) a cada componente.

1. Iniciar sesión como Administrador o Coordinador con permisos para gestionar cursos.
2. Navegar a **Gestión de Cursos** > **Nuevo Curso** (abrir Asistente / Wizard).
3. Seleccionar la carrera **Diseño Multimedia** (Plan 2016) y la asignatura regular **`DM-DEMO`** (Diseño de Experiencia de Usuario).
4. Avanzar al paso de configuración de componentes:
   - **Verificación Visual**: En la cabecera o sección de componentes, observar el chip de estado de conexión:
     - 🟢 **En línea y sincronizado con Intranet**: Si el servicio de Intranet responde y devolvió las actas correspondientes.
     - 🟡 **Sin actas registradas en Intranet**: Si la Intranet está disponible pero no hay actas para ese grupo/período.
     - 🔴 **Intranet no disponible**: Si el servicio no responde o se encuentra desconectado.
   - **Verificación de Campos**: Cada componente sugerida muestra su `CUR_CODIGO` precargado correspondiente desde Intranet.
5. Completar los campos requeridos (docente titular, grupo, fechas) y hacer clic en **Guardar Curso**.
6. **Resultado esperado**:
   - El curso se crea exitosamente.
   - La notificación flash confirma la creación e informa el número de actas oficiales vinculadas (o advierte si se creó en modo local por falta de conexión).
   - En la base de datos (`curso.componente`), la columna `intranet_cur_codigo` almacena el valor numérico correspondiente para cada componente.

---

### Caso 2: Bloqueo de Componentes sin Horas en Asignatura Monocomponente (FEAT-07)
**Objetivo**: Prevenir que un curso incorpore componentes espurias o tipos de actividad que tienen 0 horas asignadas en el plan de estudios.

1. En el Asistente de Creación de Curso, seleccionar la asignatura monocomponente **`DM-MONO`** (*Taller Monocomponente de Prototipado*).
2. Avanzar al paso de componentes:
   - **Verificación en UI**: El selector solo debe permitir la componente **Taller** (la única con horas > 0). Las opciones de **Cátedra** y **Laboratorio** deben encontrarse deshabilitadas o no estar disponibles para añadir.
3. (Opcional - Prueba de Seguridad por API): Enviar una petición POST a `/admin/cursos` con `id_asignacion_plan` de `DM-MONO` incluyendo una componente de Cátedra o Laboratorio.
   - **Resultado esperado**: El backend (`StoreCursoRequest`) rechaza la petición con código **HTTP 422 (Unprocessable Entity)** indicando que la asignatura no contempla horas para dicho tipo de componente.
4. Crear el curso con únicamente la componente de Taller permitida.
   - **Resultado esperado**: El curso se guarda correctamente conteniendo únicamente 1 componente activa.

---

### Caso 3: Copia y Clonación Segura de Cursos (FEAT-07 & FIX-03)
**Objetivo**: Garantizar que la clonación de un curso no arrastre componentes con 0 horas y resuelva adecuadamente los códigos de Intranet para el nuevo grupo.

1. En el listado de cursos, ubicar el curso monocomponente creado en el Caso 2 (o cualquier curso con una sola componente válida).
2. Abrir el menú de acciones del curso y seleccionar **Copiar Curso** (o clonar a otro período / grupo B).
3. Especificar el nuevo grupo de destino (por ejemplo, Grupo B) y el período académico.
4. Confirmar la clonación.
5. **Resultado esperado**:
   - El nuevo curso clonado se crea manteniendo de forma estricta las componentes válidas de la asignatura.
   - Si el curso original tenía componentes residuales o con 0 horas en el plan, estas son omitidas durante la copia.
   - Los `intranet_cur_codigo` se re-consultan y asignan para el nuevo grupo de destino sin colisiones.

---

### Caso 4: Sincronización y Detección de Componentes Sobrantes Locales (FEAT-07)
**Objetivo**: Advertir al operador cuando existen componentes locales en UTAMED que no tienen acta ni respaldo en la Intranet institucional, utilizando lenguaje institucional claro.

1. En el listado de cursos, buscar el curso sembrado: **`DEMO Guardarraíles - UX Grupo A`**.
   *(Nota: Este curso fue sembrado intencionalmente con una Cátedra oficial vinculada al acta #555101 y un Laboratorio local con `intranet_cur_codigo = null`)*.
2. Hacer clic en la acción **Sincronizar con Intranet**.
3. En el modal de sincronización:
   - **Resultado esperado en UI**:
     - Se visualiza un panel de alerta amarillo/ámbar indicando la existencia de **componentes no registradas en Intranet** (componentes sobrantes locales).
     - La redacción no utiliza el término informal *"espurias"*, sino la terminología institucional: *"Se detectaron componentes en UTAMED que no figuran en las actas de Intranet"*.
     - Se identifican claramente las componentes afectadas (en este caso, *Laboratorio*).
     - El botón de sincronización permite proceder actualizando únicamente las componentes oficiales sin corromper las locales.

---

### Caso 5: Disparo Condicional del Trigger de Inscripción y Aviso en UI (FIX-02 / G-01)
**Objetivo**: Validar que la inscripción manual autoinscribe en todas las componentes y que la UI advierte explícitamente esta regla al usuario.

1. Navegar a **Inscripciones** > **Inscribir Estudiante Manualmente**.
2. **Verificación en UI**:
   - Observar el banner informativo destacado:
     > *"Al inscribir un alumno en un curso manualmente, el sistema lo inscribirá automáticamente en todas sus componentes activas."*
3. Seleccionar el curso `DEMO Guardarraíles - UX Grupo A`.
4. Inscribir al alumno de prueba manual: **`11111111-1`** (*Alumno Manual*).
5. Guardar la inscripción.
6. **Verificación en Base de Datos / UI**:
   - Revisar las inscripciones del estudiante en el curso.
   - Comprobar que existen registros en `curso.alumno_curso` y en `curso.alumno_componente` para **todas** las componentes activas del curso.
   - El trigger `curso.trg_auto_inscribir_componentes` actuó correctamente porque `es_origen_intranet` fue `false`.
   - *(En caso de sincronización federada masiva desde Intranet donde `es_origen_intranet = true`, el trigger se inhibe y respeta la granularidad exacta dictada por las actas de Intranet)*.

---

## 3. Verificación Automatizada (Tests)

Para ejecutar la batería completa de pruebas unitarias y de integración que cubren estos escenarios:

```bash
# Ejecutar suite de pruebas de guardarraíles
./vendor/bin/pest tests/Feature/StoreCursoGuardTest.php \
                 tests/Feature/CopiarCursoMonocomponenteTest.php \
                 tests/Feature/SincronizarComponentesEspuriasTest.php \
                 tests/Feature/InscripcionTriggerOrigenTest.php
```

**Resultado esperado:**
- Todos los tests deben pasar exitosamente (`PASS`), cubriendo:
  - Rechazo de componentes no asignadas o con 0 horas.
  - Clonación limpia de cursos monocomponente.
  - Reporte de componentes sobrantes locales en sincronización de Intranet.
  - Comportamiento diferenciado del trigger según origen de inscripción (manual vs federado).

---

## 4. Limpieza de Datos de Prueba (Opcional)

Si se desea remover los datos generados por el seeder de pruebas al finalizar las verificaciones:

```bash
php artisan tinker --execute="
\App\Models\Curso\Curso::where('nombre', 'like', 'DEMO Guardarraíles%')->each(fn(\$c) => \$c->forceDelete());
\App\Models\Administrativo\Asignatura::whereIn('cod_asignatura', ['DM-MONO', 'DM-DEMO'])->each(fn(\$a) => \$a->forceDelete());
\App\Models\Usuario\Usuario::whereIn('rut', ['11111111-1', '22222222-2', '33333333-3'])->each(fn(\$u) => \$u->forceDelete());
"
```
