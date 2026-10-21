# Directrices Generales de Agentes - UTAMED

## Sincronización Obligatoria con Trello

Antes de atender cualquier solicitud de desarrollo, arreglo de error, refactor o ajuste visual:

1. **Consultar Proactivamente el Tablero Activo**:
   - Inspeccionar el tablero de Trello (`arreglos-reunion-23-09-2026` o el tablero configurado en el MCP de Trello).
   - Verificar si la petición coincide con una tarjeta existente en cualquiera de las listas (`Por definir`, `Pendiente`, `En progreso`, `En revisión`, `Hecho`).
   - Citar la tarjeta (código, título, enlace Trello) y basar la solución en su descripción y Criterios de Aceptación (DoD).
   - Al iniciar el trabajo activo en código, mover la tarjeta a `En progreso`.

2. **Convención Estricta de Estados**:
   - **`En revisión` = Revisión humana / técnica**:
     - Indica que el agente completó la implementación y verificaciones automáticas.
     - Obligatorio: Mover a `En revisión`, marcar los checks de DoD cumplidos (`[x]`) y dejar un comentario con el resumen de cambios, commits y archivos afectados.
   - **`Hecho` (Listo) = Aprobado por el usuario**:
     - **Regla estricta**: El agente **NUNCA** mueve una tarjeta a `Hecho` por cuenta propia.
     - Solo se mueve a `Hecho` cuando el usuario dé su aprobación explícita (*"aprobado"*, *"listo"*, *"validado"*, *"pasa a hecho"*).
   - **`Pendiente`**: Cola de trabajo de tarjetas refinadas y listas para tomar.
   - **`Por definir (preguntar al director)`**: Requerimientos con dependencias externas o reglas institucionales pendientes.

3. **Requerimientos Nuevos**:
   - Si no existe tarjeta para lo solicitado, advertirlo al usuario y coordinar su alta en el tablero con formato estándar y Criterios de Aceptación (DoD).
