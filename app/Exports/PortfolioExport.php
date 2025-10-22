<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PortfolioExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $portfolio;

    protected array $summary;

    public function __construct($portfolio, array $summary)
    {
        // Convertir a collection si viene como array asociativo
        if (is_array($portfolio)) {
            $this->portfolio = collect($portfolio)->map(function ($data, $cobradorName) {
                $data['cobrador_name'] = $cobradorName;
                return $data;
            })->values();
        } else {
            $this->portfolio = $portfolio instanceof Collection
                ? $portfolio
                : collect($portfolio);
        }
        $this->summary = $summary;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->portfolio;
    }

    /**
     * Map data for export
     */
    public function map($item): array
    {
        return [
            $item['cobrador_name'] ?? 'N/A',
            $item['total_credits'],
            $item['active_credits'],
            $item['completed_credits'],
            $item['total_balance'],
            $item['total_lent'],
            $item['overdue_credits'],
            $item['overdue_amount'],
            $item['portfolio_quality'].'%',
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'Cobrador',
            'Total Créditos',
            'Créditos Activos',
            'Créditos Completados',
            'Balance Total',
            'Monto Prestado',
            'Créditos en Mora',
            'Monto en Mora',
            'Calidad de Cartera',
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
        return 'Reporte de Cartera';
    }
}
