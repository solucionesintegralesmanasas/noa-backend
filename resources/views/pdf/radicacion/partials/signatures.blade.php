<table class="firmas">
    <tr>
        <td>
            <div class="espacio-firma">
                @if (! empty($izquierda['firma']['imagen']))
                    <img src="{{ $izquierda['firma']['imagen'] }}" alt="Firma">
                @elseif (! empty($izquierda['firma']['texto']))
                    <span class="firma-escrita">{{ $izquierda['firma']['texto'] }}</span>
                @endif
            </div>
            <div class="renglon-firma">
                <strong>{{ $izquierda['nombre'] ?? '' }}</strong><br>
                @if (! empty($izquierda['documento']))
                    C.C. {{ $izquierda['documento'] }}<br>
                @endif
                {{ $izquierda['cargo'] ?? '' }}
            </div>
        </td>
        <td>
            <div class="espacio-firma">
                @if (! empty($derecha['firma']['imagen']))
                    <img src="{{ $derecha['firma']['imagen'] }}" alt="Firma">
                @elseif (! empty($derecha['firma']['texto']))
                    <span class="firma-escrita">{{ $derecha['firma']['texto'] }}</span>
                @endif
            </div>
            <div class="renglon-firma">
                <strong>{{ $derecha['nombre'] ?? '' }}</strong><br>
                @if (! empty($derecha['documento']))
                    C.C. {{ $derecha['documento'] }}<br>
                @endif
                {{ $derecha['cargo'] ?? '' }}
            </div>
        </td>
    </tr>
</table>