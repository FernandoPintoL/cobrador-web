<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PerformanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $performance;

    protected array $summary;

    public function __construct(Collection $performance, array $summary)
    {
        $this->performance = $performance;
        $this->summary = $summary;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->performance;
    }

    /**
     * Map data for export
     */
    public function map($item): array
    {
        return [
            $item['cobrador_name'],
            $item['manager_name'],
            $item['metrics']['credits_delivered'],
            $item['metrics']['total_amount_lent'],
            $item['metrics']['payments_collected_count'],
            $item['metrics']['total_amount_collected'],
            $item['metrics']['collection_rate'].'%',
            $item['metrics']['active_credits'],
            $item['metrics']['completed_credits'],
            $item['metrics']['overdue_credits'],
            $item['metrics']['portfolio_quality'].'%',
            $item['metrics']['avg_days_to_complete'],
            $item['metrics']['efficiency_score'],
            $item['metrics']['active_clients'],
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'Cobrador',
            'Manager',
            'Créditos Entregados',
            'Monto Prestado',
            'Pagos Cobrados',
            'Monto Cobrado',
            'Tasa de Cobranza',
            'Créditos Activos',
            'Créditos Completados',
            'Créditos en Mora',
            'Calidad de Cartera',
            'Días Prom. Completar',
            'Score de Eficiencia',
            'Clientes Activos',
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
        return 'Reporte de Rendimiento';
    }
}
