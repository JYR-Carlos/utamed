{{-- Texto libre del programa, ya separado por App\Syllabus\SyllabusBloques. --}}
@foreach($bloques as $bloque)
    @switch($bloque['tipo'])
        @case('subtitulo')
            <div class="subtitulo">{{ $bloque['texto'] }}</div>
            @break

        @case('campos')
            <table class="kv-table">
                @foreach($bloque['filas'] as $fila)
                    <tr>
                        <td class="kv-key">{{ $fila['etiqueta'] }}</td>
                        <td>{!! $renderizarTextoConEnlaces($fila['valor']) !!}</td>
                    </tr>
                @endforeach
            </table>
            @break

        @case('lista')
            <ul>
                @foreach($bloque['items'] as $item)
                    <li @class(['anidado' => $item['anidado']])>
                        {!! $renderizarTextoConEnlaces($item['texto']) !!}
                        @if($item['cita'])
                            <span class="cita">{!! $renderizarTextoConEnlaces($item['cita']) !!}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @break

        @default
            <p>{!! $renderizarTextoConEnlaces($bloque['texto']) !!}</p>
    @endswitch
@endforeach
