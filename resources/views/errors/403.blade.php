@php
    // Un abort(…, 'mensaje') propio se muestra tal cual; el texto por defecto
    // de Laravel viene en inglés, así que en ese caso va el de esta página.
    $propio = trim((string) ($exception?->getMessage() ?? ''));
    $esPorDefecto = $propio === '' || preg_match('/unauthorized|forbidden|^this action/i', $propio);
    $codigo = 403;
@endphp
@extends('errors.layout')

@section('titulo', 'No tienes acceso a esta página')
@section('detalle', $esPorDefecto ? 'Tu cuenta no tiene permiso para ver este contenido. Si crees que es un error, avisa a tu docente o al equipo de soporte.' : $propio)
