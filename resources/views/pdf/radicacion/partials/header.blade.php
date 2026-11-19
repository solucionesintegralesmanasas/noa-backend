@php
    $__esHojaMembretada = ! empty($es_hoja_membretada)
        || \App\Services\Pdf\PdfService::esHojaMembretadaCompleta($letterhead ?? null);
    $__ocultarMarca = ! empty($ocultar_marca);
    $__sinMarcaVisual = $__esHojaMembretada || $__ocultarMarca;
@endphp

@if ($__esHojaMembretada && ! empty($letterhead))
    <div id="letterhead-page-bg">
        <img src="{{ $letterhead }}" alt="Membrete">
    </div>
@elseif (! $__sinMarcaVisual && ! empty($logo_fondo))
    <div id="watermark">
        <img src="{{ $logo_fondo }}" alt="Marca de agua">
    </div>
@endif

<table class="encabezado" cellpadding="0" cellspacing="0">
    <tr>
        <td style="width: 20%;">
            @if (! empty($imagenes['ministerio']))
                <img class="logo" src="{{ $imagenes['ministerio'] }}" alt="Ministerio de Transporte">
            @endif
        </td>
        <td style="width: {{ $__sinMarcaVisual ? '64%' : '60%' }}; text-align: center;">
            <div class="marca">{{ $empresa['razon_social'] ?? 'Transportadora' }}</div>
            @if (! empty($empresa['nit']))
                <div class="submarca">NIT. {{ $empresa['nit'] }}</div>
            @endif
            @if (! empty($empresa['ciudad']))
                <div class="submarca">{{ $empresa['ciudad'] }}</div>
            @endif
        </td>
        <td style="width: {{ $__sinMarcaVisual ? '16%' : '20%' }}; text-align: right;">
            @if (! $__sinMarcaVisual && ! empty($imagenes['empresa']))
                <img class="logo" src="{{ $imagenes['empresa'] }}" alt="Logo de la empresa">
            @endif
        </td>
    </tr>
</table>
