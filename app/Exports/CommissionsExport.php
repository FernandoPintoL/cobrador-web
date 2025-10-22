<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CommissionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $commissions;

    protected array $summary;

    public function __construct($commissions, array $summary)
    {
        $this->commissions = $commissions instanceof Collection
            ? $commissions
            : collect($commissions);
        $this->summary = $summary;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->commissions;
    }

    /**
     * Map data for export
     */
    public function map($item): array
    {
        return [
            $item['cobrador_name'],
            $item['payments_collected']['count'],
            $item['payments_collected']['total_amount'],
            $item['credits_delivered']['count'],
            $item['credits_delivered']['total_amount'],
            $item['commission']['rate'].'%',
            $item['commission']['on_collection'],
            $item['commission']['bonus'],
            $item['commission']['total'],
            $item['performance']['expected_collection'],
            $item['performance']['actual_collection'],
            $item['performance']['collection_percentage'].'%',
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'Cobrador',
            'Pagos Cobrados',
            'Monto Cobrado',
            'Créditos Entregados',
            'Monto Prestado',
            'Tasa Comisión',
            'Comisión Base',
            'Bonus',
            'Comisión Total',
            'Cobro Esperado',
            'Cobro Real',
            'Porcentaje Cumplimiento',
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
        return 'Reporte de Comisiones';
    }
}
