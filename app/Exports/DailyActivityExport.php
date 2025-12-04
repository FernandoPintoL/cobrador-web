<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyActivityExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected array $activities;

    protected array $summary;

    public function __construct(Collection $activities, array $summary)
    {
        $this->activities = $activities->toArray();
        $this->summary = $summary;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->activities);
    }

    /**
     * Map data for export
     */
    public function map($item): array
    {
        // Calcular totales de créditos entregados
        $creditsDeliveredTotal = 0;
        if (isset($item['credits_delivered']['details']) && is_array($item['credits_delivered']['details'])) {
            foreach ($item['credits_delivered']['details'] as $credit) {
                $creditsDeliveredTotal += $credit['amount'] ?? 0;
            }
        }

        // Calcular totales de pagos cobrados
        $paymentsCollectedTotal = 0;
        if (isset($item['payments_collected']['details']) && is_array($item['payments_collected']['details'])) {
            foreach ($item['payments_collected']['details'] as $payment) {
                $paymentsCollectedTotal += $payment['amount'] ?? 0;
            }
        }

        return [
            $item['cobrador_name'],
            $item['cash_balance']['status'],
            $item['cash_balance']['initial_amount'],
            $item['cash_balance']['collected_amount'],
            $item['cash_balance']['lent_amount'],
            $item['cash_balance']['final_amount'],
            $item['credits_delivered']['count'],
            $creditsDeliveredTotal,
            $item['payments_collected']['count'],
            $paymentsCollectedTotal,
            $item['expected_payments']['count'],
            $item['expected_payments']['collected'],
            $item['expected_payments']['pending'],
            $item['expected_payments']['efficiency'].'%',
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'Cobrador',
            'Estado Caja',
            'Monto Inicial',
            'Monto Cobrado',
            'Monto Prestado',
            'Monto Final',
            'Créditos Entregados',
            'Monto Entregado',
            'Pagos Cobrados',
            'Monto Cobrado',
            'Pagos Esperados',
            'Pagos Recolectados',
            'Pagos Pendientes',
            'Eficiencia Cobranza',
        ];
    }

    /**
     * Apply styles to worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return 'Actividad Diaria';
    }
}
