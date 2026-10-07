{{-- Marca de agua y membrete del layout base.
     Patrón tomado de la ficha técnica (pdf.fleet.technical-sheet-vehicles):
     - El fondo prioriza el logo en alta (`$logo_fondo` top-level o en `$data`),
       con respaldo al logo normal. Sin imagen no se renderiza nada (nunca un img roto).
     - `$ocultar_marca` (modo limpio) y `$letterhead` (modo membrete) suprimen
       la marca de agua para no duplicar identidad ni ensuciar la hoja. --}}
@php
    $marcaFondo = $logo_fondo ?? $data['logo_fondo'] ?? $data['logo'] ?? null;
@endphp
@if (empty($ocultar_marca ?? false) && empty($letterhead ?? null) && !empty($marcaFondo))
    <div id="watermark">
        <img src="data:image/png;base64,{{ $marcaFondo }}" alt="Marca de agua" />
    </div>
@endif

@if (!empty($data['cancelado'] ?? null))
    <div id="cancelado-watermark">
        <img src="data:image/png;base64,{{ $data['cancelado'] }}" alt="Anulado" />
    </div>
@endif

@if (!empty($letterhead ?? null))
    <div id="letterhead-banner">
        <img src="{{ $letterhead }}" alt="Membrete" />
    </div>
@endif
