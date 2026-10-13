@php
    // Un abort(…, 'mensaje') propio se muestra tal cual; el texto por defecto
    // de Laravel viene en inglés, así que en ese caso va el de esta página.
    $propio = trim((string) ($exception?->getMessage() ?? ''));
    $esPorDefecto = $propio === '' || preg_match('/page expired|csrf/i', $propio);
    $codigo = 419;
@endphp
@extends('errors.layout')

@section('titulo', 'Tu sesión expiró')
@section('detalle', $esPorDefecto ? 'Por seguridad la página caducó. Recárgala e inténtalo de nuevo.' : $propio)
