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

    public function __construct(array $activities, array $summary)
    {
        $this->activities = $activities;
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
        return [
            $item['cobrador_name'],
            $item['cash_balance']['status'],
            $item['cash_balance']['initial_amount'],
            $item['cash_balance']['collected_amount'],
            $item['cash_balance']['lent_amount'],
            $item['cash_balance']['final_amount'],
            $item['credits_delivered']['count'],
            $item['credits_delivered']['total_amount'],
            $item['payments_collected']['count'],
            $item['payments_collected']['total_amount'],
            $item['credits_to_deliver_today']['count'],
            $item['credits_to_deliver_today']['total_amount'],
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
            'Pendientes Entregar',
            'Monto Pend. Entregar',
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
