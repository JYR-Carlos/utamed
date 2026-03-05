# Auditoria
- Base de Datos (comportamiento a nivel de datos)
- Logger (comportamiento de controladores u acciones del usuario)

## Base de Datos
Uno de los riesgos que posee la auditoria es que las tablas de registros sea más grande a comparación de las que audita. Para ello se puede optar por tablar de auditoria particionadas por mes o una fecha determinada.

Las particiones deben estar definidas antes que la inserción de los datos (tabla)

- Se propone un esquema de auditoria en el cual se pueda centralizar los registros de logs y de comportamiento en la base de datos, considerandose solo aquellos que son claves como warn, error u otros. Los de lectura no serán registrados.

Este esquema contiene las tablas:
1. log_sistema: 
   - Permite identificar el evento relacionandolo con el error de código
   - Describe niveles de los logs (ERROR, WARNING, INFO)
   - Registra el mensaje especificado por el desarrollador
   - El contexto se basa en la variable afectada, IP o metadata adicional
