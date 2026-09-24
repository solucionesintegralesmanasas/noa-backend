<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de vencimientos de documentos</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; background-color: #f9fafb; margin: 0; padding: 20px;">
    <div style="max-width: 640px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px;">
        <h2 style="color: #c53030; text-align: center; margin-top: 0;">
            Reporte de vencimientos - {{ $companyName }}
        </h2>

        <p style="font-size: 15px;">{{ $intro }}</p>

        <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin: 20px 0;">
            <thead>
                <tr style="background-color: #fef2f2;">
                    <th style="text-align: left; padding: 10px; border-bottom: 2px solid #f56565;">Veh&iacute;culo</th>
                    <th style="text-align: left; padding: 10px; border-bottom: 2px solid #f56565;">Documento</th>
                    <th style="text-align: left; padding: 10px; border-bottom: 2px solid #f56565;">Vence</th>
                    <th style="text-align: left; padding: 10px; border-bottom: 2px solid #f56565;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr style="border-bottom: 1px solid #edf2f7;">
                        <td style="padding: 10px; font-weight: bold; color: #2b6cb0;">{{ $item['placa'] }}</td>
                        <td style="padding: 10px;">{{ $item['documento'] }}</td>
                        <td style="padding: 10px;">{{ $item['fecha'] }}</td>
                        <td style="padding: 10px;">
                            @if ($item['estado'] === 'VENCIDO')
                                <span style="color: #ffffff; background-color: #c53030; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                                    VENCIDO hace {{ abs($item['days_left']) }} d&iacute;a(s)
                                </span>
                            @elseif ($item['estado'] === 'VENCE HOY')
                                <span style="color: #ffffff; background-color: #dd6b20; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                                    VENCE HOY
                                </span>
                            @else
                                <span style="color: #744210; background-color: #fefcbf; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold;">
                                    {{ $item['days_left'] }} d&iacute;a(s)
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="background-color: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin: 20px 0;">
            <p style="color: #c53030; font-weight: bold; margin: 0;">
                Evite bloqueos en la generaci&oacute;n de sus FUEC. P&oacute;ngase al d&iacute;a con los documentos vencidos.
            </p>
        </div>

        <hr style="border: none; border-top: 1px solid #edf2f7; margin: 30px 0;">

        <p style="font-size: 13px; color: #718096; text-align: center;">
            Este es un mensaje autom&aacute;tico del sistema de alertas. No responda a este correo.
        </p>
    </div>
</body>
</html>
