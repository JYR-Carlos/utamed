@php
    // Un abort(…, 'mensaje') propio se muestra tal cual; el texto por defecto
    // de Laravel viene en inglés, así que en ese caso va el de esta página.
    $propio = trim((string) ($exception?->getMessage() ?? ''));
    $esPorDefecto = $propio === '' || preg_match('/server error/i', $propio);
    $codigo = 500;
@endphp
@extends('errors.layout')

@section('titulo', 'Algo salió mal de nuestro lado')
@section('detalle', $esPorDefecto ? 'No pudimos completar la acción y no se perdió nada de lo que ya estaba guardado. Inténtalo de nuevo en unos minutos.' : $propio)
