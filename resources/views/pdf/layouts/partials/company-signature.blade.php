@if (!empty($company ?? null) || !empty($signature ?? null))
    <table style="width:100%; border-collapse: collapse; table-layout: fixed;">
        <tr>
            <td class="company-info" style="border-top: 1px solid #000; padding: 10px; border-left: 0.5px solid #000; border-right: 0.5px solid #000; border-bottom: 0.5px solid #000;">
                @if (!empty($company ?? null))
                    <strong>{{ $company['business_name'] ?? '' }}</strong><br>
                    @if (!empty($company['nit'] ?? null))
                        NIT: {{ $company['nit'] }}<br>
                    @endif
                    {{ trim(($company['address'] ?? '').' | Tel: '.($company['phone'] ?? ''), ' |') }}
                @endif
            </td>
            <td class="text-center" style="border-top: 1px solid #000; border-left: 0.5px solid #000; border-right: 0.5px solid #000; border-bottom: 0.5px solid #000;">
                @if (!empty($signature ?? null))
                    <img class="firma" src="{{ $signature }}" alt="Firma">
                @endif
                <div style="font-size: 8px; margin-top: 4px; border-top: 0.5px solid #000; padding-top: 2px;">
                    Firma Digital Ley 527 de 1999<br>Decreto 2364 de 2012
                </div>
            </td>
        </tr>
    </table>
@endif
