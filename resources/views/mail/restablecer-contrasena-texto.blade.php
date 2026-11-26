{{-- Versión en texto del correo para restablecer la contraseña. Va sin escapar: no es HTML. --}}
Elige tu contraseña nueva · UTAMED

Hola{!! $nombre ? ', '.$nombre : '' !!}:

Recibimos un pedido para restablecer la contraseña de tu cuenta en UTAMED. Para elegir una nueva, abre este enlace:

{!! $url !!}

El enlace vale {{ $minutos }} minutos y sirve una sola vez. Después tendrás que pedir otro.
Al abrirlo eliges la contraseña nueva y vuelves a entrar con tu RUT.

¿No lo pediste? Ignora este correo: tu contraseña no cambia mientras nadie use el enlace.

¿El enlace no abre o ya no usas ese correo? Escribe a cite@gestion.uta.cl e indica tu RUT y tu nombre completo.

© {{ now()->year }} Universidad de Tarapacá · Escuela de Diseño e Innovación Tecnológica
