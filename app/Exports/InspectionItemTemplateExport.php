<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Plantilla Excel descargable con ejemplos para la carga masiva de ítems de inspección.
 */
class InspectionItemTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return [
            'CATEGORIA',
            'NOMBRE_ITEM',
            'DESCRIPCION',
        ];
    }

    public function collection(): Collection
    {
        return collect([
            [
                'DOCUMENTOS',
                'Licencia de tránsito',
                'Verificar vigencia y estado físico del documento',
            ],
            [
                'DOCUMENTOS',
                'Seguro obligatorio (SOAT)',
                'Verificar que se encuentre vigente',
            ],
            [
                'LUCES',
                'Luces bajas y altas',
                'Comprobar funcionamiento en ambos costados',
            ],
            [
                'HERRAMIENTAS',
                'Gato hidráulico y cruceta',
                'Capacidad acorde al vehículo',
            ],
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'],
                ],
            ],
        ];
    }
}
