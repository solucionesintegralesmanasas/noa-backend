<?php

declare(strict_types=1);

namespace App\Traits;

use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Trait ExcelExportable
 *
 * Agrega capacidades de exportación a Excel a cualquier Servicio o Controlador.
 */
trait ExcelExportable
{
    /**
     * Exporta datos a Excel usando Maatwebsite.
     *
     * @param  string  $exportClass  Clase de exportación (ej: InvoiceExport::class).
     * @param  string  $fileName  Nombre del archivo a generar.
     * @param  mixed  ...$args  Argumentos adicionales para el constructor de la clase de exportación.
     * @return BinaryFileResponse
     */
    public function exportToExcel(string $exportClass, string $fileName = 'export.xlsx', ...$args)
    {
        return Excel::download(new $exportClass(...$args), $fileName);
    }
}
