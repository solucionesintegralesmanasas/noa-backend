@if (!empty($qr ?? null) || !empty($instruction ?? null))
    <div class="qr-section-container">
        <div class="qr-box">
            @if (!empty($qr ?? null))
                <img class="qr" src="{{ $qr }}" alt="QR">
            @endif
        </div>
        <div class="instruction-box">
            {!! $instruction ?? 'Para verificar este documento, por favor leer el código QR por medio de la cámara de su dispositivo y/o la aplicación correspondiente.' !!}
        </div>
    </div>
@endif
