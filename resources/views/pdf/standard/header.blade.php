{{-- Encabezado institucional estándar (3 celdas: logo de la empresa | títulos | metadatos SGC).
     Requiere: pdf.standard.styles incluido en el <head>.
     Variables:
       $logo_data_url          data URL del logo configurado por la empresa (null = monograma con siglas)
       $empresa_siglas         siglas de la empresa activa para el monograma de respaldo
       $empresa_transportadora razón social
       $empresa_detalle        (opcional) línea bajo la razón social: NIT · ciudad
       $titulo_documento       título principal
       $subtitulo_documento    (opcional) línea normativa o de proceso
       $meta_sgc               array etiqueta => valor (CÓDIGO, VERSIÓN, FECHA, PERÍODO...) --}}
@php($__sinLogoCelda = ! empty($ocultar_celda_logo ?? false))
<table class="header-table" cellpadding="0" cellspacing="0">
    <tr>
        @if (! $__sinLogoCelda)
            <td class="header-logo-cell">
                @if (!empty($logo_data_url))
                    <img src="{{ $logo_data_url }}" class="header-logo-img" alt="Logo de la empresa">
                @else
                    <div class="logo-monogram">{{ $empresa_siglas ?? 'EMP' }}</div>
                @endif
            </td>
        @endif
        <td class="header-title-cell" @if ($__sinLogoCelda) style="width: 74%;" @endif>
            <div class="company-name">{{ $empresa_transportadora ?? '' }}</div>
            @if (!empty($empresa_detalle))
                <div class="company-sub">{{ $empresa_detalle }}</div>
            @endif
            <div class="doc-main-title">{{ $titulo_documento ?? '' }}</div>
            @if (!empty($subtitulo_documento))
                <div class="doc-sub-title">{{ $subtitulo_documento }}</div>
            @endif
        </td>
        <td class="header-meta-cell">
            <table class="meta-table" cellpadding="0" cellspacing="0">
                @foreach (($meta_sgc ?? []) as $etiqueta => $valor)
                    <tr>
                        <td class="meta-label">{{ $etiqueta }}</td>
                        <td class="meta-val">{{ $valor }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
