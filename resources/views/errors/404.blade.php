@php
    // Un abort(…, 'mensaje') propio se muestra tal cual; el texto por defecto
    // de Laravel viene en inglés, así que en ese caso va el de esta página.
    $propio = trim((string) ($exception?->getMessage() ?? ''));
    $esPorDefecto = $propio === '' || preg_match('/not found|could not be found|^no query results/i', $propio);
    $codigo = 404;
@endphp
@extends('errors.layout')

@section('titulo', 'No encontramos lo que buscas')
@section('detalle', $esPorDefecto ? 'La página no existe o ya no está disponible. Revisa el enlace o vuelve al inicio.' : $propio)
