<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CashFlowForecastExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
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
        return [
            $projection['date']->format('Y-m-d'),
            $projection['credit_id'],
            $projection['client_name'],
            $projection['cobrador_name'],
            $projection['amount'],
            $projection['frequency'],
            $projection['status'] === 'overdue' ? 'Vencido' : 'Pendiente',
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'Fecha Esperada',
            'ID Crédito',
            'Cliente',
            'Cobrador',
            'Monto Esperado',
            'Frecuencia',
            'Estado',
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
        return 'Proyección de Flujo';
    }
}
