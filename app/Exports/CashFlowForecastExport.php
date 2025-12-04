<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CashFlowForecastExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use RegistersEventListeners;

    protected Collection $projections;
    protected array $summary;

    public function __construct(Collection|array $projections, array $summary)
    {
        $this->projections = is_array($projections) ? collect($projections) : $projections;
        $this->summary = $summary;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->projections;
    }

    /**
     * Map data for export
     */
    public function map($projection): array
    {
        // Manejar tanto arrays como objetos
        $proj = is_array($projection) ? $projection : (array) $projection;

        $typeLabel = match($proj['type']) {
            'payment' => 'Pago Esperado',
            'delivery' => 'Entrega Programada',
            default => 'Desconocido',
        };

        $statusLabel = match($proj['status']) {
            'overdue' => 'Vencido',
            'pending' => 'Pendiente',
            'scheduled' => 'Programado',
            default => $proj['status'],
        };

        return [
            $proj['date'],
            $typeLabel,
            $proj['credit_id'],
            $proj['client_name'],
            $proj['cobrador_name'],
            number_format($proj['amount'], 2),
            $proj['frequency'] ?? 'N/A',
            $proj['installment_number'] ?? '-',
            $statusLabel,
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo',
            'ID Crédito',
            'Cliente',
            'Cobrador',
            'Monto (Bs)',
            'Frecuencia',
            'Nº Cuota',
            'Estado',
        ];
    }

    /**
     * Apply styles to worksheet
     */
    public function styles(Worksheet $sheet)
    {
        // Estilo del encabezado
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Agregar sección de resumen al final
        $lastRow = $sheet->getHighestRow() + 2;

        $sheet->setCellValue('A'.$lastRow, 'RESUMEN DE PROYECCIÓN');
        $sheet->mergeCells('A'.$lastRow.':I'.$lastRow);

        $lastRow++;
        $sheet->setCellValue('A'.$lastRow, 'Período:');
        $sheet->setCellValue('B'.$lastRow, $this->summary['period']['start'] . ' a ' . $this->summary['period']['end']);

        $lastRow++;
        $sheet->setCellValue('A'.$lastRow, 'ENTRADAS (Pagos Esperados):');
        $sheet->setCellValue('B'.$lastRow, $this->summary['total_projected_payments'] . ' pagos');
        $sheet->setCellValue('C'.$lastRow, 'Bs ' . number_format($this->summary['total_entries'], 2));

        $lastRow++;
        $sheet->setCellValue('A'.$lastRow, 'SALIDAS (Entregas Programadas):');
        $sheet->setCellValue('B'.$lastRow, $this->summary['total_projected_deliveries'] . ' entregas');
        $sheet->setCellValue('C'.$lastRow, 'Bs ' . number_format($this->summary['total_exits'], 2));

        $lastRow++;
        $sheet->setCellValue('A'.$lastRow, 'BALANCE NETO:');
        $netBalance = $this->summary['net_balance'];
        $sheet->setCellValue('B'.$lastRow, 'Bs ' . number_format($netBalance, 2));

        // Color del balance neto
        $balanceColor = $netBalance >= 0 ? '90EE90' : 'FFB6C1'; // Verde si positivo, rojo si negativo
        $sheet->getStyle('B'.$lastRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $balanceColor],
            ],
        ]);

        $lastRow++;
        $sheet->setCellValue('A'.$lastRow, 'Vencidos:');
        $sheet->setCellValue('B'.$lastRow, 'Bs ' . number_format($this->summary['overdue_amount'], 2));

        $lastRow++;
        $sheet->setCellValue('A'.$lastRow, 'Pendientes:');
        $sheet->setCellValue('B'.$lastRow, 'Bs ' . number_format($this->summary['pending_amount'], 2));

        // Estilo para títulos de resumen
        $summaryStartRow = $sheet->getHighestRow() - 6;
        $sheet->getStyle('A'.$summaryStartRow.':I'.$summaryStartRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        return [];
    }

    /**
     * Aplica colores condicionales según tipo de transacción
     */
    public function registerEvents(): array
    {
        $projections = $this->projections;

        return [
            AfterSheet::class => function(AfterSheet $event) use ($projections) {
                $sheet = $event->sheet->getDelegate();

                $row = 2; // Empezar después del encabezado
                foreach ($projections as $projection) {
                    $proj = is_array($projection) ? $projection : (array) $projection;

                    // Colores según tipo y estado
                    if ($proj['type'] === 'payment') {
                        // Pagos: verde claro si pendiente, rojo claro si vencido
                        $color = $proj['is_overdue'] ? 'FFE5E5' : 'E8F5E9';
                    } else {
                        // Entregas: amarillo claro si programado, naranja si vencido
                        $color = $proj['is_overdue'] ? 'FFE0B2' : 'FFF9C4';
                    }

                    $sheet->getStyle('A'.$row.':I'.$row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $color],
                        ],
                    ]);

                    $row++;
                }
            },
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Proyección de Flujo';
    }
}
