@php
    // Un abort(…, 'mensaje') propio se muestra tal cual; el texto por defecto
    // de Laravel viene en inglés, así que en ese caso va el de esta página.
    $propio = trim((string) ($exception?->getMessage() ?? ''));
    $esPorDefecto = $propio === '' || preg_match('/service unavailable/i', $propio);
    $codigo = 503;
@endphp
@extends('errors.layout')

@section('titulo', 'Estamos en mantenimiento')
@section('detalle', $esPorDefecto ? 'El sistema vuelve a estar disponible en unos minutos.' : $propio)
