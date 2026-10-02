<style>
    @page {
        size: letter;
        margin: 2cm 2cm 2cm 2cm;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10.5pt;
        line-height: 1.45;
        color: #000;
        margin: 0;
        padding: 0;
    }

    .encabezado {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 1.5px solid #000;
        padding-bottom: 6px;
        margin-bottom: 14px;
    }

    .encabezado td {
        vertical-align: middle;
        border: none;
        padding: 0;
    }

    .encabezado .marca {
        font-size: 13pt;
        font-weight: bold;
        line-height: 1.1;
    }

    .encabezado .submarca {
        font-size: 8.5pt;
        color: #333;
    }

    .logo {
        max-height: 52px;
        max-width: 170px;
    }

    .titulo {
        text-align: center;
        font-size: 12pt;
        font-weight: bold;
        text-transform: uppercase;
        margin: 10px 0 14px;
        letter-spacing: .3px;
    }

    .destinatario {
        margin-bottom: 12px;
    }

    .destinatario p {
        margin: 0 0 2px;
    }

    .referencia {
        margin: 10px 0;
    }

    .cuerpo {
        text-align: justify;
        margin-bottom: 10px;
    }

    .datos {
        width: 100%;
        border-collapse: collapse;
        margin: 8px 0 14px;
        border: 1px solid #666;
    }

    .datos th,
    .datos td {
        border: 1px solid #666;
        padding: 4px 6px;
        text-align: left;
        font-size: 9.5pt;
    }

    .datos th {
        background-color: #f0f0f0;
        font-weight: bold;
        width: 32%;
    }

    .clausula {
        text-align: justify;
        margin-bottom: 7px;
    }

    .clausula strong {
        text-transform: uppercase;
    }

    .firmas {
        width: 100%;
        border-collapse: collapse;
        margin-top: 26px;
    }

    .firmas td {
        width: 50%;
        text-align: center;
        border: none;
        padding: 0 8px;
        vertical-align: bottom;
        font-size: 9.5pt;
    }

    .espacio-firma {
        height: 56px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
    }

    .espacio-firma img {
        max-height: 48px;
        max-width: 160px;
    }

    /* Firma tomada por enlace: el firmante escribe su nombre en vez de trazar. */
    .firma-escrita {
        font-family: "Segoe Script", "Brush Script MT", cursive;
        font-size: 15pt;
        font-style: italic;
        line-height: 48px;
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .renglon-firma {
        border-top: 1px solid #000;
        padding-top: 4px;
        line-height: 1.3;
    }

    .pie {
        position: fixed;
        bottom: -1.1cm;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 8pt;
        color: #444;
        border-top: 1px solid #bbb;
        padding-top: 3px;
    }
</style>