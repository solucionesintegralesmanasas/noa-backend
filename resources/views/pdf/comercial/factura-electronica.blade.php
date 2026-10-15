@php
    $acento = '#1F4E5F';
    $texto = '#1B2430';
    $tarjeta = '#F3F6F7';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato - Factura Electrónica de Venta</title>
    <style>
        @page { size: A4 portrait; margin: 56px; }
        body { font-family: 'Plus Jakarta Sans', 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: {{ $texto }}; background: #fff; }
        table { width: 100%; border-collapse: collapse; }
        .encabezado-logo { width: 110px; height: 90px; border: 2px solid {{ $acento }}; text-align: center; vertical-align: middle; font-size: 11px; color: {{ $acento }}; }
        .datos-empresa { padding-left: 12px; vertical-align: middle; font-size: 11px; line-height: 1.5; }
        .titulo-doc { text-align: right; vertical-align: top; }
        .titulo-doc .nombre { font-size: 20px; font-weight: bold; color: {{ $acento }}; }
        .titulo-doc .numero { font-size: 14px; font-weight: bold; margin-top: 4px; }
        .titulo-doc .fechas { font-size: 11px; margin-top: 4px; line-height: 1.5; }
        .tarjeta { background: {{ $tarjeta }}; border-radius: 6px; padding: 10px 12px; vertical-align: top; font-size: 11px; line-height: 1.6; }
        .tarjeta .etiqueta { font-weight: bold; color: {{ $acento }}; font-size: 12px; }
        table.items { margin-top: 14px; }
        table.items th { background: {{ $acento }}; color: #fff; font-size: 11px; padding: 7px 6px; text-align: left; }
        table.items td { font-size: 11px; padding: 7px 6px; border-bottom: 1px solid #D5DDE0; }
        table.items .num { text-align: right; }
        table.totales { margin-top: 10px; }
        table.totales td { font-size: 12px; padding: 3px 6px; }
        table.totales .etiq { text-align: right; }
        table.totales .val { text-align: right; width: 150px; }
        table.totales .fila-total td { background: {{ $acento }}; color: #fff; font-weight: bold; font-size: 13px; padding: 7px 6px; }
        .pie { margin-top: 16px; font-size: 10px; line-height: 1.6; }
        .qr-box { width: 130px; height: 130px; border: 2px solid {{ $acento }}; text-align: center; vertical-align: middle; font-size: 10px; color: {{ $acento }}; }
        .cufe { word-wrap: break-word; font-size: 10px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 320px;">
                <table>
                    <tr>
                        <td class="encabezado-logo">[LOGO]</td>
                        <td class="datos-empresa">
                            <strong>[RAZÓN SOCIAL]</strong><br>
                            NIT [NIT]-[DV]<br>
                            [DIRECCIÓN]<br>
                            [CIUDAD]<br>
                            Tel: [TELÉFONO]<br>
                            [CORREO]
                        </td>
                    </tr>
                </table>
            </td>
            <td class="titulo-doc">
                <div class="nombre">FACTURA ELECTRÓNICA DE VENTA</div>
                <div class="numero">[PREFIJO][0000]</div>
                <div class="fechas">
                    Generación: [FECHA HORA]<br>
                    Validación DIAN: [FECHA HORA]<br>
                    Vencimiento: [FECHA]
                </div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 50%; padding-right: 6px;">
                <table><tr><td class="tarjeta">
                    <span class="etiqueta">Emisor</span><br>
                    [RAZÓN SOCIAL]<br>
                    NIT [NIT]-[DV] · Responsable de IVA: [SÍ/NO]<br>
                    Actividad económica (CIIU): [CÓDIGO] - [DESCRIPCIÓN]<br>
                    [DIRECCIÓN] · [CIUDAD]<br>
                    Tel: [TELÉFONO] · [CORREO]
                </td></tr></table>
            </td>
            <td style="width: 50%; padding-left: 6px;">
                <table><tr><td class="tarjeta">
                    <span class="etiqueta">Adquirente</span><br>
                    [NOMBRE O RAZÓN SOCIAL]<br>
                    [TIPO DOCUMENTO] [NÚMERO]<br>
                    [DIRECCIÓN]<br>
                    [CORREO]
                </td></tr></table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td>
                <table><tr><td class="tarjeta">
                    <span class="etiqueta">Forma y medio de pago</span><br>
                    Forma: [CONTADO/CRÉDITO] · Plazo: [N DÍAS]<br>
                    Medio de pago: [MEDIO] · Moneda: COP
                </td></tr></table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 50px;">Cód.</th>
                <th>Descripción</th>
                <th style="width: 45px;">Un.</th>
                <th class="num" style="width: 50px;">Cant.</th>
                <th class="num" style="width: 90px;">Vr. unitario</th>
                <th class="num" style="width: 55px;">IVA %</th>
                <th class="num" style="width: 95px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>[COD1]</td><td>[DESCRIPCIÓN 1]</td><td>[UN]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[IVA %]</td><td class="num">[TOTAL]</td></tr>
            <tr><td>[COD2]</td><td>[DESCRIPCIÓN 2]</td><td>[UN]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[IVA %]</td><td class="num">[TOTAL]</td></tr>
            <tr><td>[COD3]</td><td>[DESCRIPCIÓN 3]</td><td>[UN]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[IVA %]</td><td class="num">[TOTAL]</td></tr>
            <tr><td>[COD4]</td><td>[DESCRIPCIÓN 4]</td><td>[UN]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[IVA %]</td><td class="num">[TOTAL]</td></tr>
        </tbody>
    </table>

    <table class="totales">
        <tr><td class="etiq">Subtotal:</td><td class="val">[SUBTOTAL]</td></tr>
        <tr><td class="etiq">Descuento:</td><td class="val">[DESCUENTO]</td></tr>
        <tr><td class="etiq">Base gravable:</td><td class="val">[BASE]</td></tr>
        <tr><td class="etiq">IVA 19 %:</td><td class="val">[IVA]</td></tr>
        <tr><td class="etiq">Retenciones:</td><td class="val">[RETENCIONES]</td></tr>
        <tr class="fila-total"><td class="etiq">TOTAL:</td><td class="val">[TOTAL]</td></tr>
    </table>

    <table class="pie">
        <tr>
            <td class="qr-box">[CÓDIGO QR]</td>
            <td style="padding-left: 12px; vertical-align: top;">
                <strong>CUFE:</strong><br>
                <span class="cufe">[CUFE]</span><br><br>
                <strong>Resolución de numeración DIAN:</strong> [NÚMERO] del [FECHA]<br>
                Prefijo [PREFIJO] · Rango [DESDE] al [HASTA] · Vigencia hasta [FECHA]<br><br>
                <strong>Firma electrónica:</strong> [FIRMA]<br>
                <strong>Software de facturación:</strong> [NOMBRE SOFTWARE] · NIT [NIT]<br><br>
                Representación gráfica de la factura electrónica de venta.
            </td>
        </tr>
    </table>
</body>
</html>
