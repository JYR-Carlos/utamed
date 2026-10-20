# Regla de Sincronización Obligatoria con Trello

## 1. Consulta Proactiva Obligatoria del Tablero
Antes de implementar cualquier cambio, corrección de bug, mejora visual, refactor o nueva funcionalidad solicitada por el usuario:
* **Consultar el tablero de Trello activo** (`arreglos-reunion-23-09-2026` o el configurado en el servidor MCP de Trello).
* Verificar si la petición del usuario corresponde a una tarjeta existente (o conjunto de tarjetas) buscando por palabras clave, código identificador (`[BUG-01]`, `[FEAT-02]`, `SEC-01`, etc.), ruta, componente o descripción técnica en las listas (`Por definir`, `Pendiente`, `En progreso`, `En revisión`, `Hecho`).
* Si existe una tarjeta relacionada:
  - Referenciarla explícitamente en la respuesta y en los planes de trabajo (código, título y enlace Trello).
  - Leer y respetar estrictamente su descripción y Criterios de Aceptación (DoD).
  - Al iniciar el desarrollo activo en el código, mover la tarjeta a la lista **`En progreso`**.

## 2. Definición Estricta de Estados y Transiciones

### `En revisión` = Revisión Humana / Técnica
* **Significado**: La funcionalidad, corrección o refactor fue implementado a nivel de código y validado técnicamente por el agente. La tarea queda dispuesta para inspección, pruebas y revisión de código por un humano.
* **Acciones obligatorias al mover a `En revisión`**:
  1. Mover la tarjeta a la lista **`En revisión`**.
  2. Marcar como completados (`[x]`) los ítems correspondientes en el checklist de *Criterios de Aceptación (DoD)*.
  3. Registrar un comentario en la tarjeta resumiendo los cambios implementados, archivos afectados, commits y rama utilizada.

### `Hecho` (Listo) = Aprobado por el Usuario
* **Significado**: La tarjeta ha sido formalmente **aprobada y validada por el usuario**.
* **Regla Inquebrantable**: El agente **NUNCA** debe mover una tarjeta a `Hecho` de forma autónoma.
* **Condición de Transición**: Solo se transiciona a `Hecho` cuando el usuario manifieste explícitamente su conformidad (ej. *"aprobado"*, *"listo"*, *"pasa a hecho"*, *"funciona perfecto"*).

### `Pendiente`
* Tarjetas priorizadas y refinadas en cola de trabajo, listas para ser tomadas para desarrollo.

### `Por definir (preguntar al director)`
* Tarjetas cuyo alcance, causales o reglas de negocio están incompletas o supeditadas a decisiones institucionales o del director.

## 3. Nuevos Requerimientos o Bugs no Catalogados
Si la solicitud del usuario no coincide con ninguna tarjeta del tablero:
* Advertir al usuario que no existe una tarjeta previa en el tablero.
* Sugerir o dar de alta la nueva tarjeta en la lista adecuada (`Pendiente` o `Por definir`), manteniendo el formato estándar del proyecto con estimación Fibonacci y Criterios de Aceptación (DoD).
