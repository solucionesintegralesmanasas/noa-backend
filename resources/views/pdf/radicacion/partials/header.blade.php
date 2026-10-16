<table class="encabezado">
    <tr>
        <td style="width: 26%;">
            @if (! empty($imagenes['ministerio']))
                <img class="logo" src="{{ $imagenes['ministerio'] }}" alt="Ministerio de Transporte">
            @endif
        </td>
        <td style="width: 48%; text-align: center;">
            <div class="marca">{{ $empresa['razon_social'] ?? 'Transportadora' }}</div>
            @if (! empty($empresa['nit']))
                <div class="submarca">NIT. {{ $empresa['nit'] }}</div>
            @endif
            @if (! empty($empresa['ciudad']))
                <div class="submarca">{{ $empresa['ciudad'] }}</div>
            @endif
        </td>
        <td style="width: 26%; text-align: right;">
            @if (! empty($imagenes['empresa']))
                <img class="logo" src="{{ $imagenes['empresa'] }}" alt="Logo de la empresa">
            @endif
        </td>
    </tr>
</table>