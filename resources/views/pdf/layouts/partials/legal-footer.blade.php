@if (!empty($validUntil ?? null) || !empty($extraLines ?? null))
    <div class="legal-footer">
        @if (!empty($validUntil ?? null))
            <p style="margin: 1px 0;"><strong>Válido hasta:</strong> {{ $validUntil }}</p>
        @endif
        @foreach (($extraLines ?? []) as $line)
            <p style="margin: 1px 0;">{!! $line !!}</p>
        @endforeach
    </div>
@endif
