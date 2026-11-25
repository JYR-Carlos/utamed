@if(!empty($seccion['componentes']))
    <table class="eval-table">
        <thead>
            <tr>
                <th>Componente</th>
                <th>%</th>
                <th>Genera Acta</th>
                <th>Aprobación oblig.</th>
                <th>Asistencia oblig.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($seccion['componentes'] as $comp)
                <tr>
                    <td><strong>{{ $comp['componente'] ?? '' }}</strong></td>
                    <td>{{ $comp['porcentaje'] ?? 0 }}%</td>
                    <td>{{ !empty($comp['genera_acta']) ? 'Sí' : 'No' }}</td>
                    <td>{{ !empty($comp['aprobacion_obligatoria']) ? 'Sí' : 'No' }}</td>
                    <td>
                        {{-- 0 es que el componente no exige asistencia. --}}
                        @if(!isset($comp['asistencia_obligatoria']))
                            —
                        @else
                            {{ (float) $comp['asistencia_obligatoria'] > 0 ? $comp['asistencia_obligatoria'] . '%' : 'No' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
