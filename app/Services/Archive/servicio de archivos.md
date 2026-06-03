# FormRequests
- Validan exclusivamente las peticiones que entran por la web (HTTP).
- Orquestan qué configuraciones de archivos se necesitan llamando al Builder (pueden pedir un solo tipo o combinar múltiples).
- Aportan el contexto específico de la petición a través de reglas adicionales (metadata como títulos, descripciones, IDs).
- Retornan errores automáticos (422) al usuario si la validación falla.
- **No hacen:** validaciones fuera del entorno web (como en comandos de consola o jobs).
- **No hacen:** el cálculo lógico ni la mezcla manual de arrays de mimes/pesos (eso lo delegan al Builder).

# FileRequirementBuilder
- Lee tu archivo de configuración (`config/filetypes.php`).
- Ejecuta la lógica pesada de estructurar y unificar reglas (ej. crear los Closures para validar pesos independientes).
- Genera dos salidas: reglas nativas para Laravel y perfiles estructurados para el Helper.
- **No hace:** ninguna validación real del archivo físico ni lanza excepciones.

# FileValidationHelper
- Ejecuta la validación dura y estricta del archivo físico.
- Compara el archivo contra los perfiles que le entregó el Builder.
- Lanza tus excepciones personalizadas de dominio (`FileValidationException`).
- **No hace:** peticiones HTTP ni toca los FormRequests.

# Service / preValidate()
- Actúa como la aduana final del sistema; por aquí pasa todo archivo sin importar su origen.
- Llama al Builder para obtener los perfiles permitidos en ese servicio específico.
- Usa el Helper para re-confirmar las reglas de negocio y comprobar que el archivo sea válido e íntegro.
- Garantiza que el almacenamiento no se corrompa jamás.
- **No hace:** devolver errores web amigables.