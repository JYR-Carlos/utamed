@php
    // Un abort(…, 'mensaje') propio se muestra tal cual; el texto por defecto
    // de Laravel viene en inglés, así que en ese caso va el de esta página.
    $propio = trim((string) ($exception?->getMessage() ?? ''));
    $esPorDefecto = $propio === '' || preg_match('/too many/i', $propio);
    $codigo = 429;
@endphp
@extends('errors.layout')

@section('titulo', 'Demasiados intentos')
@section('detalle', $esPorDefecto ? 'Hiciste muchas solicitudes en poco tiempo. Espera un momento e inténtalo otra vez.' : $propio)
