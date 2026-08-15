<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render the initial visits made to your application's pages.
    |
    | You can specify a custom SSR bundle path, or omit it to let Inertia
    | try and automatically detect it for you.
    |
    | Do note that enabling these options will NOT automatically make SSR work,
    | as a separate rendering service needs to be available. To learn more,
    | please visit https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        'enabled' => true,

        'url' => 'http://127.0.0.1:13714',

        'bundle' => base_path('bootstrap/ssr/ssr.js'),
    ],

    /*
    |--------------------------------------------------------------------------
    | History Encryption
    |--------------------------------------------------------------------------
    |
    | Inertia guarda los props de cada página en `window.history.state` para
    | poder restaurarlas al pulsar "atrás" sin ir al servidor. Sin cifrar, esos
    | datos quedan en el historial del navegador incluso después del logout.
    |
    | Con `encrypt` activo los props se guardan cifrados con una clave que vive
    | en sessionStorage. `Inertia::clearHistory()` (ver App\Http\Responses\
    | LogoutResponse) borra esa clave al cerrar sesión, así que las entradas
    | anteriores ya no se pueden descifrar: Inertia detecta el fallo y pide la
    | página al servidor, que redirige al login.
    |
    | Requiere contexto seguro (HTTPS o localhost) para `window.crypto.subtle`.
    | Sin él Inertia avisa por consola y guarda en claro, sin romper nada.
    |
    */

    'history' => [
        'encrypt' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to any of the
    | paths AND with any of the extensions specified here.
    |
    */

    'testing' => [
        'ensure_pages_exist' => true,

        'page_paths' => [resource_path('js/pages')],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],
];
