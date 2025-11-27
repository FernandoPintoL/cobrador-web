<?php
namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BalancesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $balances;
    protected $summary;

    public function __construct($balances, $summary)
    {
        $this->balances = $balances;
        $this->summary  = $summary;
    }

    public function collection(): Collection
    {
        $collection = collect($this->balances);

        // ✅ Si los items son arrays con _model, extraer el modelo
        return $collection->map(fn($item) =>
            is_array($item) && isset($item['_model']) ? $item['_model'] : $item
        );
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Cobrador',
            'Monto Inicial',
            'Monto Recaudado',
            'Monto Prestado',
            'Monto Final',
            'Diferencia',
            'Notas',
        ];
    }

    public function map($balance): array
    {
        $difference = $balance->final_amount - ($balance->initial_amount + $balance->collected_amount - $balance->lent_amount);

        return [
            $balance->date->format('d/m/Y'),
            $balance->cobrador->name ?? 'N/A',
            number_format($balance->initial_amount, 2),
            number_format($balance->collected_amount, 2),
            number_format($balance->lent_amount, 2),
            number_format($balance->final_amount, 2),
            number_format($difference, 2),
            $balance->notes ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para el encabezado
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font'      => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill'      => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Agregar fila de resumen al final
        $lastRow = $sheet->getHighestRow() + 2;
        $sheet->setCellValue('A' . $lastRow, 'RESUMEN GENERAL');
        $sheet->setCellValue('B' . $lastRow, 'Total de Registros: ' . $this->summary['total_records']);
        $sheet->setCellValue('C' . $lastRow, 'Monto Inicial Total: $' . number_format($this->summary['total_initial'], 2));
        $sheet->setCellValue('D' . $lastRow, 'Monto Recaudado Total: $' . number_format($this->summary['total_collected'], 2));
        $sheet->setCellValue('E' . $lastRow, 'Monto Prestado Total: $' . number_format($this->summary['total_lent'], 2));
        $sheet->setCellValue('F' . $lastRow, 'Monto Final Total: $' . number_format($this->summary['total_final'], 2));
        $sheet->setCellValue('G' . $lastRow, 'Diferencia Promedio: $' . number_format($this->summary['average_difference'], 2));

        // Estilo para la fila de resumen
        $sheet->getStyle('A' . $lastRow . ':F' . $lastRow)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'],
            ],
        ]);

        return [];
    }
}
