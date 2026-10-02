<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Enlace para firmar</title>
</head>

<body style="margin:0;padding:24px;background-color:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1f2933;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0"
        style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e1e5ea;">
        <tr>
            <td style="background-color:#0b5ed7;padding:20px 24px;color:#ffffff;">
                <div style="font-size:17px;font-weight:bold;">{{ $empresa }}</div>
                <div style="font-size:13px;opacity:.9;">Enlace de firma de contrato</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 14px;font-size:15px;">Estimado(a) {{ $nombre }}:</p>

                <p style="margin:0 0 14px;font-size:14px;line-height:1.5;">
                    Por medio del presente le enviamos el enlace para revisar y firmar el
                    <strong>{{ $rotulo }}</strong>.
                </p>

                <p style="margin:0 0 18px;">
                    <a href="{{ $url }}" target="_blank"
                        style="display:inline-block;background-color:#198754;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:6px;font-size:15px;font-weight:bold;">
                        Revisar y firmar el contrato
                    </a>
                </p>

                <p style="margin:0 0 8px;font-size:12px;color:#616e7c;">
                    Si el botón no funciona, copie esta dirección en su navegador:
                </p>
                <p style="margin:0 0 20px;font-size:11px;color:#52606d;word-break:break-all;background-color:#f4f6f9;padding:8px;border-radius:4px;">
                    {{ $url }}
                </p>

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;background-color:#fff8e1;border-left:4px solid #f0ad4e;">
                    <tr>
                        <td style="padding:12px 14px;font-size:12px;color:#7a5b00;line-height:1.5;">
                            <strong>Enlace de un solo uso.</strong><br>
                            Vence el {{ $expira }} y solo puede utilizarse una vez.
                        </td>
                    </tr>
                </table>

                <p style="margin:20px 0 0;font-size:12px;color:#616e7c;">
                    Si ya firmó o no reconoce esta solicitud, ignore este mensaje y
                    comuníquese con la oficina de la empresa.
                </p>
            </td>
        </tr>
        <tr>
            <td style="background-color:#f4f6f9;padding:14px 24px;font-size:11px;color:#7b8794;text-align:center;">
                Este mensaje contiene un enlace seguro de firma. No lo reenvíe.
            </td>
        </tr>
    </table>
</body>

</html>