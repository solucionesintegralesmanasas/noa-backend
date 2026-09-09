<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alerta de Vencimiento de Documento</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333333; line-height: 1.6; background-color: #f9fafb; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        
        <h2 style="color: #e53e3e; text-align: center; margin-top: 0;">
            ⚠️ Aviso de Vencimiento
        </h2>
        
        <p style="font-size: 16px;">
            <strong>{{ $document->company->business_name ?? 'EMUNAH' }}</strong> le recuerda que su 
            <strong>{{ $document->document_type ?? 'Tarjeta de Operación' }}</strong> del vehículo con placa 
            <strong style="color: #2b6cb0;">{{ $document->vehicle->vehicle_license_plate ?? 'N/A' }}</strong> 
            vencerá el <strong>{{ \Carbon\Carbon::parse($document->expiry_date ?? $document->expiration_date)->format('d/m/Y') }}</strong>.
        </p>

        <div style="background-color: #fff5f5; border-left: 4px solid #f56565; padding: 15px; margin: 20px 0;">
            <p style="color: #c53030; font-weight: bold; margin: 0;">
                Evite bloqueos en la generación de su FUEC.
            </p>
        </div>

        <hr style="border: none; border-top: 1px solid #edf2f7; margin: 30px 0;">

        <p style="font-size: 13px; color: #718096; text-align: center;">
            Si necesitas ayuda o quieres saber más sobre el trámite del FUEC, comunícate con soporte para indicar cómo prefieres continuar.
        </p>
    </div>
</body>
</html>
