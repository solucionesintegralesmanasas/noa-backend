<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Company;
use App\Services\Pdf\PdfService;
use App\Support\Billing\QrDian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

/**
 * Construye y descarga el PDF de notas crédito y notas débito comerciales
 * reutilizando el layout corporativo y la lógica de marca de agua/membrete.
 */
class NotaPdfService
{
    public const TIPO_CREDITO = 'CREDITO';

    public const TIPO_DEBITO = 'DEBITO';

    public function __construct(
        private readonly PdfService $pdfService
    ) {}

    public function descargar(Model $nota, string $tipo): Response
    {
        $nota->loadMissing([
            'items.measurementUnit',
            'items.withholdings',
            'paymentMethod',
            'invoice.customer.municipality.department',
            'invoice.customer.documentType',
            'invoice.customer.taxResponsibility',
            'invoice.resolution',
            'invoice.billingProvider',
        ]);

        $empresa = Company::with([
            'municipality.department',
            'taxRegime',
            'enablingResolutions',
            'economicActivities',
        ])->where('uuid', $nota->company_uuid)->first();

        $logoModel = PdfService::logoParaMarca($empresa);
        $imagenes = $this->pdfService->getBase64Parallel(['logo' => $logoModel]);

        $qrUrl = data_get($nota->dian_response, 'data.links.qr')
            ?: ($nota->cude
                ? 'https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey='.$nota->cude
                : null);

        $esCredito = $tipo === self::TIPO_CREDITO;

        $data = [
            'nota' => $nota,
            'tipoNota' => $tipo,
            'esCredito' => $esCredito,
            'empresa' => $empresa,
            'data' => [
                'logo' => $imagenes['logo'] ?? null,
                'qr' => QrDian::pngBase64($qrUrl),
            ],
        ];

        $data = $this->pdfService->conMarca(
            $data,
            $empresa,
            $esCredito ? 'nota_credito' : 'nota_debito',
            $logoModel
        );

        $titulo = $esCredito ? 'Nota Crédito' : 'Nota Débito';
        $nombre = PdfService::nombreArchivo(
            $titulo,
            (string) $nota->note_number,
            $nota->issue_date?->format('Y-m-d') ?? ''
        );

        return $this->pdfService->downloadFromView('pdf.commercial-note', $data, $nombre, ['sello' => false]);
    }
}
