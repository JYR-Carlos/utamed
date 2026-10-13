<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · UTAmed</title>
    {{-- Estilos en línea a propósito: esta página tiene que verse aunque falle el
         build de Vite o la propia aplicación. --}}
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;
             background:#FAFBFC;color:#1A1A24;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
        main{width:100%;max-width:440px;background:#fff;border:1px solid #E5E7EB;border-radius:14px;
             padding:32px 28px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
        .marca{font-size:13px;font-weight:700;letter-spacing:.04em;color:#002F6C;margin:0 0 18px}
        .codigo{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;color:#5A5E6E;margin:0 0 6px}
        h1{font-size:22px;line-height:1.25;margin:0 0 10px}
        p.detalle{font-size:14.5px;line-height:1.5;color:#3F4352;margin:0 0 24px}
        .acciones{display:flex;flex-wrap:wrap;gap:10px}
        a.boton,button.boton{font:inherit;font-size:14px;font-weight:600;border-radius:10px;padding:10px 16px;
             text-decoration:none;cursor:pointer;border:1px solid #D6D9E0;background:#fff;color:#1A1A24}
        a.principal{background:#002F6C;border-color:#002F6C;color:#fff}
        a.boton:hover,button.boton:hover{filter:brightness(.96)}
        a.principal:hover{background:#00214d;filter:none}
    </style>
</head>
<body>
    <main role="main">
        <p class="marca">UTAmed</p>
        <p class="codigo">Error {{ $codigo ?? '' }}</p>
        <h1>@yield('titulo')</h1>
        <p class="detalle">@yield('detalle')</p>
        <div class="acciones">
            <a class="boton principal" href="{{ url('/') }}">Ir al inicio</a>
            <button class="boton" type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">Volver</button>
        </div>
    </main>
</body>
</html>
