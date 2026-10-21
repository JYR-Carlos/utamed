<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Leído por los componentes Svelte que hacen fetch() a mano (cabecera X-CSRF-TOKEN) --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Nombre fijo y no config('app.name'): quien arma la vista previa de un
         enlace (WhatsApp, Teams, correo…) no ejecuta JS y se queda con esta
         cabecera, y con APP_NAME sin definir en el servidor mostraba «Laravel».
         Cambiar APP_NAME allá no es gratis: también nombra la cookie de sesión. --}}
    <title inertia>UTAmed</title>
    <meta name="description" content="Entorno Virtual de Aprendizaje de la Universidad de Tarapacá.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="UTAmed">
    <meta property="og:title" content="UTAmed | Entorno Virtual de Aprendizaje">
    <meta property="og:description" content="Entorno Virtual de Aprendizaje de la Universidad de Tarapacá.">
    <meta property="og:image" content="{{ asset('banner-utamed.jpg') }}">
    <meta property="og:image:width" content="550">
    <meta property="og:image:height" content="880">
    <meta property="og:locale" content="es_CL">

    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    @vite(['resources/js/app.ts'])
    @inertiaHead

    {{-- Tema antes del primer pintado, con la cookie que guarda useAppearance:
         sin esto la página destella en claro antes de pasar a oscuro. --}}
    <script>
        (function () {
            const appearance = '{{ $appearance ?? "light" }}';
            const oscuro = appearance === 'dark'
                || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

            document.documentElement.classList.toggle('dark', oscuro);
        })();
    </script>
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>