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
        // ✅ Si ya es Collection (datos del Service), extraer _model
        // Si es Query Builder (legacy), ejecutar get()
        if ($this->query instanceof Collection) {
            // Extraer el modelo Eloquent de cada elemento
            return $this->query->map(fn($item) => $item['_model'] ?? $item);
        }

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
            // ✅ CAMPOS CACHEADOS - Consistencia con Blade y API JSON
            'Porción Principal',
            'Porción Interés',
            'Falta para Cuota',
            'Número Cuota',
            'Cuotas Pendientes',
            'Balance Crédito',
            'Tipo de Pago',
            'Estado',
            'Fecha de Creación',
        ];
    }

    public function map($payment): array
    {
        // ✅ OPTIMIZACIÓN: Los métodos usan caché en memoria
        return [
            $payment->id,
            $payment->payment_date->format('d/m/Y'),
            $payment->cobrador->name ?? 'N/A',
            $payment->credit->client->name ?? 'N/A',
            number_format($payment->amount, 2),
            // ✅ Métodos cacheados
            number_format($payment->getPrincipalPortion(), 2),
            number_format($payment->getInterestPortion(), 2),
            $payment->getRemainingForInstallment() !== null
                ? number_format($payment->getRemainingForInstallment(), 2)
                : 'N/A',
            $payment->installment_number > 0 ? $payment->installment_number : 'N/A',
            $payment->credit ? $payment->credit->getPendingInstallments() : 'N/A',
            $payment->credit ? number_format($payment->credit->balance, 2) : 'N/A',
            $payment->payment_method ?? 'N/A',
            $payment->status ?? 'N/A',
            $payment->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // ✅ Estilo para el encabezado (ahora con más columnas)
        $sheet->getStyle('A1:N1')->applyFromArray([
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
        $sheet->setCellValue('C'.$lastRow, 'Monto Total: Bs '.number_format($this->summary['total_amount'], 2));
        $sheet->setCellValue('D'.$lastRow, 'Promedio: Bs '.number_format($this->summary['average_payment'], 2));
        $sheet->setCellValue('E'.$lastRow, 'Principal: Bs '.number_format($this->summary['total_without_interest'], 2));
        $sheet->setCellValue('F'.$lastRow, 'Interés: Bs '.number_format($this->summary['total_interest'], 2));

        // Estilo para la fila de resumen
        $sheet->getStyle('A'.$lastRow.':F'.$lastRow)->applyFromArray([
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
