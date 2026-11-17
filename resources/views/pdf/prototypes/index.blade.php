<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Prototipos de Estilo PDF — NOA</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 32px; }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p.lead { color: #475569; margin: 0 0 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .card { background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 20px; }
        .card h2 { font-size: 16px; margin: 0 0 8px; color: #1e3a8a; }
        .card p { font-size: 13px; color: #475569; line-height: 1.45; margin: 0 0 16px; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        a.btn { display: inline-block; padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; background: #1e3a8a; color: #fff; }
        a.btn.sec { background: #e2e8f0; color: #0f172a; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Prototipos de Base Estándar PDF</h1>
        <p class="lead">Rama aislada <code>feat/pdf-style-prototypes</code> · Comparativa de 3 estilos generados con DomPDF (2 páginas reales, carta vertical y horizontal).</p>
        <div class="grid">
            @foreach ($variantes as $clave => $info)
                <div class="card">
                    <h2>{{ $info['nombre'] }}</h2>
                    <p>Archivo: <code>{{ $info['archivo'] }}</code></p>
                    <div class="actions">
                        <a class="btn" target="_blank" href="/dev/pdf-prototypes/{{ $clave }}">Ver Carta Vertical</a>
                        <a class="btn sec" target="_blank" href="/dev/pdf-prototypes/{{ $clave }}?orientacion=landscape">Ver Horizontal</a>
                        <a class="btn sec" href="/dev/pdf-prototypes/{{ $clave }}?download=1">Descargar</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
