{{--
  Correo para restablecer la contraseña (App\Notifications\RestablecerContrasena).

  Es la continuación de las pantallas de contraseña (dirección A, guía dm):
  el panel noche con su degradado, la etiqueta amarilla y el titular
  condensado; los tres pasos de PasosRecuperacion con los dos primeros
  hechos; el botón azul de acción; la ayuda en cite@ con el mismo texto de
  la pantalla de restablecer, y al pie los logos.

  Va con tablas y estilos en línea porque así lo leen Gmail y Outlook: donde
  no hay degradado queda el azul noche, y donde no carga Sofia Sans, la letra
  del sistema. Las imágenes salen del propio sitio y cada una lleva su texto.
--}}
@php
    $letra = "'Sofia Sans', 'Segoe UI', Helvetica, Arial, sans-serif";
    $letraCond = "'Sofia Sans Condensed', 'Arial Narrow', 'Segoe UI', Helvetica, Arial, sans-serif";
    $pasos = ['Pide el enlace', 'Abre tu correo', 'Elige la contraseña'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Elige tu contraseña nueva · UTAMED</title>
<link href="https://fonts.bunny.net/css?family=sofia-sans:400,500,600|sofia-sans-condensed:600,800" rel="stylesheet">
<style>
    body { margin: 0; padding: 0; }
    @media (max-width: 540px) {
        .caja { padding-left: 24px !important; padding-right: 24px !important; }
        .titular { font-size: 34px !important; line-height: 34px !important; }
        .boton { width: 100% !important; }
        .boton a { display: block !important; text-align: center !important; }
        .paso { font-size: 12px !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background:#f0f2f6;">
{{-- Lo que el buzón muestra junto al asunto. --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Tienes {{ $minutos }} minutos para elegir una contraseña nueva. Después entras con tu RUT.
</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f2f6;">
<tr>
<td align="center" style="padding:32px 12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

        {{-- Panel noche: marca, etiqueta y titular, como en la pantalla --}}
        <tr>
        <td class="caja" bgcolor="#161949" style="background-color:#161949;background-image:linear-gradient(160deg,#161949 0%,#161949 45%,#660959 100%);border-radius:16px 16px 0 0;padding:26px 40px 36px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="padding-right:10px;vertical-align:middle;">
                    <img src="{{ asset('icon.png') }}" width="28" height="28" alt="" style="display:block;border:0;">
                </td>
                <td style="vertical-align:middle;font-family:{!! $letraCond !!};font-size:22px;line-height:26px;font-weight:800;letter-spacing:0.02em;color:#ffffff;">
                    UTAMED
                </td>
            </tr>
            </table>
            <p style="margin:40px 0 12px;font-family:{!! $letra !!};font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#febd02;">
                Diseño Multimedia · Universidad de Tarapacá
            </p>
            <h1 class="titular" style="margin:0;font-family:{!! $letraCond !!};font-size:44px;line-height:42px;font-weight:600;text-transform:uppercase;color:#ffffff;">
                Elige tu<br>contraseña nueva
            </h1>
        </td>
        </tr>

        {{-- Cuerpo --}}
        <tr>
        <td class="caja" style="background:#ffffff;padding:32px 40px 32px;font-family:{!! $letra !!};">

            {{-- Los tres pasos de la pantalla: los dos primeros ya están hechos. --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 30px;">
            <tr>
            @foreach ($pasos as $i => $paso)
                @php $hecho = $i < 2; @endphp
                <td width="33%" valign="top" style="padding:0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="28" style="width:28px;">
                            <div style="width:25px;height:25px;border:1.5px solid {{ $hecho ? '#0a7552' : '#2441ca' }};border-radius:6px;background:{{ $hecho ? '#edfdf5' : '#2441ca' }};color:{{ $hecho ? '#0a7552' : '#ffffff' }};font-family:{!! $letra !!};font-size:14px;line-height:25px;font-weight:600;text-align:center;">
                                {!! $hecho ? '&#10003;' : $i + 1 !!}
                            </div>
                        </td>
                        @if ($i < 2)
                            <td style="padding:0 8px;">
                                <div style="height:2px;line-height:2px;font-size:0;background:#0a7552;">&nbsp;</div>
                            </td>
                        @else
                            <td>&nbsp;</td>
                        @endif
                    </tr>
                    </table>
                    <p class="paso" style="margin:8px 6px 0 0;font-size:13px;line-height:18px;font-weight:{{ $hecho ? 500 : 600 }};color:{{ $hecho ? '#4a4e6e' : '#2F3363' }};">
                        {{ $paso }}
                    </p>
                </td>
            @endforeach
            </tr>
            </table>

            <p style="margin:0 0 10px;font-size:17px;line-height:24px;font-weight:600;color:#2F3363;">
                Hola{{ $nombre ? ', '.$nombre : '' }}:
            </p>
            <p style="margin:0 0 26px;font-size:15px;line-height:24px;color:#4a4e6e;">
                Recibimos un pedido para restablecer la contraseña de tu cuenta en UTAMED. Para elegir una nueva, usa este botón:
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="boton">
            <tr>
            <td bgcolor="#2441ca" style="background:#2441ca;border-radius:10px;">
                <a href="{{ $url }}" style="display:inline-block;padding:14px 28px;font-family:{!! $letra !!};font-size:15px;line-height:20px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">
                    Elegir contraseña nueva
                </a>
            </td>
            </tr>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 22px;">
            <tr>
            <td style="background:#f6f7fa;border-left:4px solid #febd02;border-radius:0 10px 10px 0;padding:15px 20px;font-size:14px;line-height:21px;color:#4a4e6e;">
                El enlace vale <strong style="font-weight:600;color:#2F3363;">{{ $minutos }} minutos</strong> y sirve una sola vez. Después tendrás que pedir otro.<br>
                Al abrirlo eliges la contraseña nueva y vuelves a entrar con tu RUT.
            </td>
            </tr>
            </table>

            <p style="margin:0 0 22px;font-size:14px;line-height:21px;color:#4a4e6e;">
                ¿No lo pediste? Ignora este correo: tu contraseña no cambia mientras nadie use el enlace.
            </p>

            <p style="margin:0;padding-top:18px;border-top:1px solid #e0e5ec;font-size:13px;line-height:19px;color:#5c6080;">
                Si el botón no abre, copia este enlace en tu navegador:<br>
                <a href="{{ $url }}" style="color:#2441ca;word-break:break-all;">{{ $url }}</a>
            </p>
        </td>
        </tr>

        {{-- Ayuda, con el texto de la pantalla de restablecer --}}
        <tr>
        <td class="caja" style="background:#f6f7fa;border-top:1px solid #e0e5ec;padding:18px 40px 22px;font-family:{!! $letra !!};font-size:14px;line-height:21px;color:#4a4e6e;">
            ¿El enlace no abre o ya no usas ese correo? Escribe a
            <a href="mailto:cite@gestion.uta.cl" style="color:#2441ca;font-weight:500;text-decoration:none;">cite@gestion.uta.cl</a>
            e indica tu RUT y tu nombre completo.
        </td>
        </tr>

        {{-- Pie noche: isologotipo UTA (su texto es blanco) y cuadro dm --}}
        <tr>
        <td class="caja" bgcolor="#161949" style="background:#161949;border-radius:0 0 16px 16px;padding:24px 40px 24px;font-family:{!! $letra !!};">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="vertical-align:middle;padding-right:28px;">
                    <img src="{{ asset('img/correo/uta-isologotipo.png') }}" width="143" height="40" alt="Universidad de Tarapacá, Universidad del Estado" style="display:block;border:0;">
                </td>
                <td style="vertical-align:middle;">
                    <img src="{{ asset('img/correo/dm-logotipo.png') }}" width="48" height="48" alt="Diseño Multimedia" style="display:block;border:0;">
                </td>
            </tr>
            </table>
            <p style="margin:18px 0 0;font-size:12px;line-height:18px;color:#b4b8d9;">
                © {{ now()->year }} Universidad de Tarapacá · Escuela de Diseño e Innovación Tecnológica
            </p>
        </td>
        </tr>

        <tr>
        <td style="padding:18px 24px 0;text-align:center;font-family:{!! $letra !!};font-size:12px;line-height:18px;color:#5c6080;">
            Te llegó a tu correo institucional porque alguien pidió restablecer la contraseña de tu cuenta en UTAMED.
        </td>
        </tr>

    </table>
</td>
</tr>
</table>
</body>
</html>
