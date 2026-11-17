{{-- Placa · número interno, alineados en una micro-tabla (DomPDF no alinea bien inline-block con borde).
     Variables: $placa, $numero_interno (opcional) --}}
<table class="placa-inline-tbl" cellpadding="0" cellspacing="0">
    <tr>
        <td class="placa-badge">{{ $placa ?? 'N/A' }}</td>
        @if (!empty($numero_interno))
            <td class="vehiculo-sep">&middot;</td>
            <td class="vehiculo-interno">{{ $numero_interno }}</td>
        @endif
    </tr>
</table>
