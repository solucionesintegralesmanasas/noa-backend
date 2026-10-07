<table class="header-table">
    <tr>
        <td>
            @if (!empty($data['transpor'] ?? null))
                <img class="logo-header" src="data:image/png;base64,{{ $data['transpor'] }}" alt="MinTransporte">
            @endif
        </td>
        <td>
            @if (!empty($data['super'] ?? null))
                <img class="logo-header" src="data:image/png;base64,{{ $data['super'] }}" alt="Super">
            @endif
        </td>
        <td>
            @if (!empty($data['logo'] ?? null))
                <img class="logo-header" src="data:image/png;base64,{{ $data['logo'] }}" alt="Logo">
            @endif
        </td>
    </tr>
</table>
