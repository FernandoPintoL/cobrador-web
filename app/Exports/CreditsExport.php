<?php

namespace App\Exports;

use App\Services\CreditReportFormatterService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CreditsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithEvents
{
    use RegistersEventListeners;

    protected $data;

    protected $summary;

    public function __construct($data, $summary)
    {
        $this->data = $data;
        $this->summary = $summary;
    }

    public function collection(): Collection
    {
        // $data is already a Collection of transformed credit data from the DTO
        return $this->data instanceof Collection ? $this->data : collect($this->data);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Cliente',
            'Creador/Cobrador',
            'Entregó',
            'Monto',
            'Interés',
            'Total',
            'Por Cuota',
            'Pagado',
            'Balance',
            'Completadas',
            'Esperadas',
            'Vencidas',
            'Estado Pago',
            'Estado de Retraso',
            'Días Retraso',
            'Frecuencia',
            'Vencimiento',
            'Creación',
        ];
    }

    public function map($credit): array
    {
        // Handle both array and object formats for flexibility
        $creditArray = is_array($credit) ? $credit : (array) $credit;

        // Get the model for additional data
        $model = is_array($credit) ? ($credit['_model'] ?? null) : ($credit->_model ?? null);

        // Extract all needed values
        $amount = (float) ($creditArray['amount'] ?? 0);
        $totalAmount = $model ? (float) $model->total_amount : (float) ($amount * 1.2); // Default 20% interest
        $interest = $totalAmount - $amount;
        $balance = (float) ($creditArray['balance'] ?? 0);
        $paidAmount = $model ? (float) $model->total_paid : 0;
        $installmentAmount = $model ? (float) $model->installment_amount : 0;
        $frequency = $model ? $model->frequency : 'N/A';
        $endDate = $model && $model->end_date ? $model->end_date->format('d/m/Y') : 'N/A';

        // Get severity data from backend or use formatter service if model is available
        $overdueSeverity = $creditArray['overdue_severity'] ?? 'none';
        $daysOverdue = $creditArray['days_overdue'] ?? 0;

        // Generate formatted severity text with emoji
        $severityEmoji = CreditReportFormatterService::getSeverityEmoji($overdueSeverity);
        $severityLabel = CreditReportFormatterService::getSeverityLabel($overdueSeverity);
        $severityText = $severityEmoji . ' ' . $severityLabel;

        // Traducir frecuencia al español
        $frequencyTranslations = [
            'daily' => 'Diario',
            'weekly' => 'Semanal',
            'biweekly' => 'Quincenal',
            'monthly' => 'Mensual',
        ];
        $frequencySpanish = $frequencyTranslations[$frequency] ?? ucfirst(str_replace('_', ' ', $frequency));

        return [
            $creditArray['id'] ?? 'N/A',
            $creditArray['client_name'] ?? 'N/A',
            $creditArray['created_by_name'] ?? 'N/A',
            $creditArray['delivered_by_name'] ?? 'N/A',
            number_format($amount, 2),
            number_format($interest, 2),
            number_format($totalAmount, 2),
            number_format($installmentAmount, 2),
            number_format($paidAmount, 2),
            number_format($balance, 2),
            $creditArray['completed_installments'] ?? 0,
            $creditArray['expected_installments'] ?? 0,
            $creditArray['installments_overdue'] ?? 0,
            $creditArray['payment_status_label'] ?? 'N/A',
            $severityText,  // ✅ Al día, ⚠️ Alerta leve, etc.
            $daysOverdue,
            $frequencySpanish,  // ⭐ Traducido al español
            $endDate,
            $creditArray['created_at_formatted'] ?? 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Establecer ancho de columnas para mejor visibilidad
        $sheet->getColumnDimension('A')->setWidth(8);   // ID
        $sheet->getColumnDimension('B')->setWidth(20);  // Cliente
        $sheet->getColumnDimension('C')->setWidth(18);  // Creador/Cobrador
        $sheet->getColumnDimension('D')->setWidth(18);  // Entregó
        $sheet->getColumnDimension('E')->setWidth(12);  // Monto
        $sheet->getColumnDimension('F')->setWidth(12);  // Interés
        $sheet->getColumnDimension('G')->setWidth(12);  // Total
        $sheet->getColumnDimension('H')->setWidth(12);  // Por Cuota
        $sheet->getColumnDimension('I')->setWidth(12);  // Pagado
        $sheet->getColumnDimension('J')->setWidth(12);  // Balance
        $sheet->getColumnDimension('K')->setWidth(12);  // Completadas
        $sheet->getColumnDimension('L')->setWidth(12);  // Esperadas
        $sheet->getColumnDimension('M')->setWidth(10);  // Vencidas
        $sheet->getColumnDimension('N')->setWidth(20);  // Estado Pago
        $sheet->getColumnDimension('O')->setWidth(18);  // Estado de Retraso (con emoji)
        $sheet->getColumnDimension('P')->setWidth(12);  // Días Retraso
        $sheet->getColumnDimension('Q')->setWidth(14);  // Frecuencia
        $sheet->getColumnDimension('R')->setWidth(14);  // Vencimiento
        $sheet->getColumnDimension('S')->setWidth(12);  // Creación

        // Estilo para el encabezado (19 columnas)
        $sheet->getStyle('A1:S1')->applyFromArray([
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
                'wrapText' => true,
            ],
        ]);

        // Altura para el encabezado
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Aplicar estilos a filas de datos (bordes y alineación)
        $dataStartRow = 2;
        $dataEndRow = $sheet->getHighestRow();

        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle('A'.$dataStartRow.':S'.$dataEndRow)->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => false,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D3D3D3'],
                    ],
                ],
            ]);
        }

        // Agregar fila de resumen al final
        $lastRow = $sheet->getHighestRow() + 2;
        $sheet->setCellValue('A'.$lastRow, 'RESUMEN');
        $sheet->setCellValue('B'.$lastRow, 'Total de Créditos: '.$this->summary['total_credits']);
        $sheet->setCellValue('C'.$lastRow, 'Monto Total: Bs '.number_format($this->summary['total_amount'], 2));
        $sheet->setCellValue('D'.$lastRow, 'Total Pagado: Bs '.number_format($this->summary['total_paid'] ?? 0, 2));

        $lastRow++;
        $sheet->setCellValue('B'.$lastRow, 'Saldo Pendiente: Bs '.number_format($this->summary['total_balance'] ?? $this->summary['pending_amount'], 2));

        // Estilo para las filas de resumen
        $summaryStartRow = $lastRow - 1;
        $sheet->getStyle('A'.$summaryStartRow.':S'.$lastRow)->applyFromArray([
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

    /**
     * Aplica colores condicionales a las filas basado en el estado de pago
     * Utiliza el sistema de payment_status:
     * - completed: Verde claro (#e8f5e9) - Completado
     * - current: Azul claro (#e3f2fd) - Al día
     * - ahead: Morado claro (#f3e5f5) - Adelantado
     * - warning: Amarillo claro (#fffacd) - Retraso leve
     * - danger: Rojo claro (#ffcccc) - Retraso alto
     */
    public function registerEvents(): array
    {
        $data = $this->data;

        return [
            AfterSheet::class => function(AfterSheet $event) use ($data) {
                $sheet = $event->sheet->getDelegate();

                // Comenzar desde la fila 2 (después del encabezado)
                $creditIndex = 0;
                foreach ($data as $credit) {
                    $row = $creditIndex + 2; // +2 porque la fila 1 es encabezado y array empieza en 0

                    $creditArray = is_array($credit) ? $credit : (array) $credit;

                    // Obtener el estado de pago desde el backend
                    $paymentStatus = $creditArray['payment_status'] ?? 'danger';

                    // Mapear estados de pago a colores
                    $colorMap = [
                        'completed' => ['bg' => 'e8f5e9', 'text' => '1b5e20'], // Verde claro
                        'current'   => ['bg' => 'e3f2fd', 'text' => '0d47a1'], // Azul claro
                        'ahead'     => ['bg' => 'f3e5f5', 'text' => '4a148c'], // Morado claro
                        'warning'   => ['bg' => 'fffacd', 'text' => '827717'], // Amarillo claro
                        'danger'    => ['bg' => 'ffcccc', 'text' => 'b71c1c'], // Rojo claro
                    ];

                    // Obtener colores, usar 'danger' como default
                    $colors = $colorMap[$paymentStatus] ?? $colorMap['danger'];
                    $bgColor = $colors['bg'];
                    $textColor = $colors['text'];

                    // Aplicar el color de fondo a toda la fila (19 columnas: A-S)
                    $sheet->getStyle('A'.$row.':S'.$row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $bgColor],
                        ],
                    ]);

                    // Aplicar estilo especial a la columna "Estado Pago" (columna N)
                    $sheet->getStyle('N'.$row)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => $textColor],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);

                    // También aplicar estilo a la columna "Estado de Retraso" (columna O)
                    $overdueSeverity = $creditArray['overdue_severity'] ?? 'none';
                    $severityColorMap = [
                        'none'     => '1b5e20', // Verde oscuro
                        'light'    => 'f57c00', // Naranja
                        'moderate' => 'e65100', // Naranja oscuro
                        'critical' => 'b71c1c', // Rojo oscuro
                    ];
                    $severityTextColor = $severityColorMap[$overdueSeverity] ?? '000000';

                    $sheet->getStyle('O'.$row)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => $severityTextColor],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);

                    $creditIndex++;
                }
            },
        ];
    }
}
