# Directrices para Claude (CLAUDE.md) - UTAMED

## Regla de Oro: Sincronización Obligatoria con Trello

Antes de iniciar cualquier tarea, corrección de bug, ajuste de interfaz o nueva funcionalidad solicitada por el usuario:

### 1. Consulta Proactiva del Tablero
* **Siempre consultar el tablero activo de Trello** (`arreglos-reunion-23-09-2026` o el tablero configurado en las herramientas MCP de Trello).
* Contrastar la solicitud del usuario contra las tarjetas existentes en todas las listas (`Por definir`, `Pendiente`, `En progreso`, `En revisión`, `Hecho`).
* Si la solicitud coincide con una tarjeta:
  - Citarla formalmente en la respuesta (código, título y enlace).
  - Ajustar el trabajo estrictamente a su descripción y a sus **Criterios de Aceptación (DoD)**.
  - Al comenzar la implementación activa, mover la tarjeta a **`En progreso`**.

### 2. Definición Estricta de Estados
* **`En revisión` = Revisión humana / técnica**:
  - Indica que la solución fue codificada, testeada y terminada técnicamente por el asistente.
  - Está lista para que un humano (desarrollador, QA o revisor) la inspeccione.
  - **Obligatorio al mover a `En revisión`**:
    1. Mover la tarjeta a la lista `En revisión`.
    2. Marcar los ítems completados en el checklist de *Criterios de Aceptación (DoD)*.
    3. Registrar un comentario en la tarjeta con el resumen técnico de cambios, archivos modificados y commits.
* **`Hecho` (Listo) = Aprobado por el usuario**:
  - **REGLA ESTRICTA**: El asistente **NUNCA** mueve una tarjeta a `Hecho` de forma autónoma.
  - Solo se moverá a `Hecho` si el usuario indica explícitamente su aprobación final (ej. *"listo"*, *"aprobado"*, *"validado"*, *"pasa a hecho"*).
* **`Pendiente`**: Tarjetas en espera de desarrollo.
* **`Por definir (preguntar al director)`**: Tarjetas bloqueadas o pendientes de clarificación institucional.

### 3. Tareas no Catalogadas
* Si la petición del usuario no corresponde a ninguna tarjeta previa, informar al usuario y ofrecer crearla en el tablero siguiendo el formato estándar de estimación Fibonacci y DoD.
