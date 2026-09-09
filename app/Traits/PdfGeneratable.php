<?php

declare(strict_types=1);

namespace App\Traits;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Trait PdfGeneratable
 *
 * Agrega capacidades de generación de PDFs a cualquier Servicio o Controlador.
 */
trait PdfGeneratable
{
    /**
     * Genera y descarga/muestra un archivo PDF usando Barryvdh DomPDF.
     *
     * @param  string  $viewPath  Ruta de la vista Blade (ej: 'pdfs.invoice').
     * @param  array  $data  Datos para pasar a la vista.
     * @param  string  $fileName  Nombre del archivo PDF.
     * @param  bool  $download  True para forzar descarga, False para previsualizar en el navegador.
     * @param  string  $paperSize  Tamaño de papel (A4, letter, etc).
     * @param  string  $orientation  Orientación (portrait, landscape).
     * @return Response
     */
    public function generatePdf(
        string $viewPath,
        array $data,
        string $fileName = 'document.pdf',
        bool $download = false,
        string $paperSize = 'A4',
        string $orientation = 'portrait'
    ) {
        $pdf = Pdf::loadView($viewPath, $data);
        $pdf->setPaper($paperSize, $orientation);

        if ($download) {
            return $pdf->download($fileName);
        }

        return $pdf->stream($fileName);
    }
}
