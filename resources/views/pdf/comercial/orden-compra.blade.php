@php
    $acento = '#1F4E5F';
    $texto = '#1B2430';
    $tarjeta = '#F3F6F7';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Formato - Orden de Compra</title>
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
        .bloque { margin-top: 14px; font-size: 11px; line-height: 1.6; }
        .bloque .titulo-bloque { font-weight: bold; color: {{ $acento }}; font-size: 12px; }
        table.firmas { margin-top: 30px; }
        table.firmas td { width: 33%; text-align: center; font-size: 11px; padding-top: 34px; border-top: 1px solid {{ $texto }}; }
        table.firmas .cargo { font-size: 10px; }
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
                <div class="nombre">ORDEN DE COMPRA</div>
                <div class="numero">OC-[0000]</div>
                <div class="fechas">
                    Emisión: [FECHA]<br>
                    Entrega requerida: [FECHA]
                </div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 50%; padding-right: 6px;">
                <table><tr><td class="tarjeta">
                    <span class="etiqueta">Proveedor</span><br>
                    [RAZÓN SOCIAL]<br>
                    NIT [NIT]-[DV]<br>
                    [DIRECCIÓN]<br>
                    [CORREO]
                </td></tr></table>
            </td>
            <td style="width: 50%; padding-left: 6px;">
                <table><tr><td class="tarjeta">
                    <span class="etiqueta">Enviar a</span><br>
                    Sitio: [SITIO DE ENTREGA]<br>
                    [DIRECCIÓN]<br>
                    Recibe: [NOMBRE]<br>
                    Tel: [TELÉFONO]
                </td></tr></table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 60px;">Ref.</th>
                <th>Descripción</th>
                <th class="num" style="width: 60px;">Cant.</th>
                <th class="num" style="width: 100px;">Vr. unitario</th>
                <th class="num" style="width: 100px;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>[REF1]</td><td>[DESCRIPCIÓN 1]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[TOTAL]</td></tr>
            <tr><td>[REF2]</td><td>[DESCRIPCIÓN 2]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[TOTAL]</td></tr>
            <tr><td>[REF3]</td><td>[DESCRIPCIÓN 3]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[TOTAL]</td></tr>
            <tr><td>[REF4]</td><td>[DESCRIPCIÓN 4]</td><td class="num">[CANT]</td><td class="num">[VR UNITARIO]</td><td class="num">[TOTAL]</td></tr>
        </tbody>
    </table>

    <table class="totales">
        <tr><td class="etiq">Subtotal:</td><td class="val">[SUBTOTAL]</td></tr>
        <tr><td class="etiq">IVA 19 %:</td><td class="val">[IVA]</td></tr>
        <tr class="fila-total"><td class="etiq">TOTAL:</td><td class="val">[TOTAL]</td></tr>
    </table>

    <div class="bloque">
        <span class="titulo-bloque">Condiciones de la orden</span><br>
        Forma de pago: [FORMA DE PAGO]<br>
        Lugar de entrega: [LUGAR]<br>
        Citar el No. de orden en la factura y remisión.
    </div>

    <table class="firmas">
        <tr>
            <td>
                ___________________________<br>
                <strong>Solicitado por</strong><br>
                <span class="cargo">[NOMBRE]</span>
            </td>
            <td>
                ___________________________<br>
                <strong>Aprobado por</strong><br>
                <span class="cargo">[NOMBRE]</span>
            </td>
            <td>
                ___________________________<br>
                <strong>Recibido por el proveedor</strong><br>
                <span class="cargo">[NOMBRE]</span>
            </td>
        </tr>
    </table>
</body>
</html>
