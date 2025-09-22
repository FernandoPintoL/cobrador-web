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

class PaymentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $query;

    protected $summary;

    public function __construct($query, $summary)
    {
        $this->query = $query;
        $this->summary = $summary;
    }

    public function collection(): Collection
    {
        return $this->query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha de Pago',
            'Cobrador',
            'Cliente',
            'Monto',
            'Tipo de Pago',
            'Notas',
            'Fecha de Creación',
        ];
    }

    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->payment_date->format('d/m/Y'),
            $payment->cobrador->name ?? 'N/A',
            $payment->credit->client->name ?? 'N/A',
            number_format($payment->amount, 2),
            $payment->payment_method ?? 'N/A',
            $payment->notes ?? '',
            $payment->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para el encabezado
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Agregar fila de resumen al final
        $lastRow = $sheet->getHighestRow() + 2;
        $sheet->setCellValue('A'.$lastRow, 'RESUMEN');
        $sheet->setCellValue('B'.$lastRow, 'Total de Pagos: '.$this->summary['total_payments']);
        $sheet->setCellValue('C'.$lastRow, 'Monto Total: $'.number_format($this->summary['total_amount'], 2));
        $sheet->setCellValue('D'.$lastRow, 'Promedio: $'.number_format($this->summary['average_payment'], 2));

        // Estilo para la fila de resumen
        $sheet->getStyle('A'.$lastRow.':D'.$lastRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '000000'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'],
            ],
        ]);

        return [];
    }
}
