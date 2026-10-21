<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Solicitud de tanqueo {{ $solicitud->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f4f6f9;
            color: #1f2937;
            padding: 20px;
        }
        .card {
            max-width: 520px;
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, .08);
            overflow: hidden;
        }
        .head {
            padding: 22px 24px;
            background: #00103e;
            color: #fff;
        }
        .head h1 { margin: 0 0 4px; font-size: 20px; }
        .head p { margin: 0; color: #c7d2fe; font-size: 13px; }
        .body { padding: 22px 24px; }
        .estado {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .estado--ok { background: #dcfce7; color: #166534; }
        .estado--usada { background: #dbeafe; color: #1d4ed8; }
        .estado--bad { background: #fee2e2; color: #b91c1c; }
        dl { display: grid; grid-template-columns: 150px 1fr; gap: 10px 14px; margin: 0; }
        dt { color: #6b7280; font-size: 13px; }
        dd { margin: 0; font-weight: 600; font-size: 14px; word-break: break-word; }
        .foot {
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        @media (max-width: 420px) {
            dl { grid-template-columns: 1fr; gap: 4px; }
            dt { margin-top: 8px; }
        }
    </style>
</head>
<body>
    <main class="card">
        <header class="head">
            <h1>Validación de solicitud de tanqueo</h1>
            <p>Código {{ $solicitud->code }}</p>
        </header>
        <div class="body">
            @php
                $estadoClase = $valida ? 'estado--ok' : ($solicitud->status === 'USADA' ? 'estado--usada' : 'estado--bad');
                $estadoTexto = $valida ? 'APROBADA Y VIGENTE' : $solicitud->status;
            @endphp
            <span class="estado {{ $estadoClase }}">{{ $estadoTexto }}</span>

            <dl>
                <dt>Empresa</dt>
                <dd>{{ $datos['empresa']['nombre'] ?? '—' }}</dd>

                <dt>NIT</dt>
                <dd>{{ $datos['empresa']['nit'] ?? '—' }}</dd>

                <dt>Placa</dt>
                <dd>{{ $datos['vehiculo']['placa'] ?? '—' }}</dd>

                <dt>Marca</dt>
                <dd>{{ $datos['vehiculo']['marca'] ?? '—' }}</dd>

                <dt>Modelo</dt>
                <dd>{{ $datos['vehiculo']['modelo'] ?? '—' }}</dd>

                <dt>Clase</dt>
                <dd>{{ $datos['vehiculo']['clase'] ?? '—' }}</dd>

                <dt>Solicitó</dt>
                <dd>{{ $datos['conductor'] ?? '—' }}</dd>

                <dt>Aprobó</dt>
                <dd>{{ $datos['aprobador'] ?? '—' }}</dd>

                <dt>Vigencia</dt>
                <dd>{{ $datos['vigencia'] ?? '—' }}</dd>
            </dl>

            <p class="foot">
                Esta página valida la solicitud. No autoriza un monto ni un surtimiento diferente al registrado.
            </p>
        </div>
    </main>
</body>
</html>
