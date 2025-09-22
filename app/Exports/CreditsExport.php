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

class CreditsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
        return $this->query->with(['client', 'createdBy'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Cliente',
            'Cobrador',
            'Monto Total',
            'Monto Pagado',
            'Saldo Pendiente',
            'Estado',
            'Fecha de Creación',
            'Fecha de Vencimiento',
        ];
    }

    public function map($credit): array
    {
        $totalAmount = (float) ($credit->total_amount ?? $credit->calculateTotalAmount());
        $balance = (float) ($credit->balance ?? $credit->getCurrentBalance());
        $paidAmount = max(0, $totalAmount - $balance);

        return [
            $credit->id,
            $credit->client->name ?? 'N/A',
            $credit->createdBy->name ?? 'N/A',
            number_format($totalAmount, 2),
            number_format($paidAmount, 2),
            number_format($balance, 2),
            $credit->status,
            $credit->created_at->format('d/m/Y'),
            $credit->end_date ? $credit->end_date->format('d/m/Y') : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para el encabezado
        $sheet->getStyle('A1:I1')->applyFromArray([
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
        $sheet->setCellValue('B'.$lastRow, 'Total de Créditos: '.$this->summary['total_credits']);
        $sheet->setCellValue('C'.$lastRow, 'Monto Total: $'.number_format($this->summary['total_amount'], 2));
        $sheet->setCellValue('D'.$lastRow, 'Saldo Pendiente: $'.number_format($this->summary['pending_amount'], 2));

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
