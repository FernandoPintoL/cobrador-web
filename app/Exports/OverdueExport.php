<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OverdueExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $credits;

    protected array $summary;

    public function __construct(Collection $credits, array $summary)
    {
        $this->credits = $credits;
        $this->summary = $summary;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        // ✅ Si los items son arrays con _model, extraer el modelo
        return $this->credits->map(fn($item) =>
            is_array($item) && isset($item['_model']) ? $item['_model'] : $item
        );
    }

    /**
     * Map data for export
     */
    public function map($credit): array
    {
        return [
            $credit->id,
            $credit->client->name ?? 'N/A',
            $credit->client->client_category ?? 'N/A',
            $credit->deliveredBy?->name ?? $credit->createdBy?->name ?? 'N/A',
            $credit->amount,
            $credit->total_amount,
            $credit->balance,
            $credit->days_overdue ?? 0,
            $credit->overdue_amount ?? 0,
            $credit->overdue_installments ?? 0,
            $credit->completion_rate ?? 0,
            $credit->frequency,
            $credit->start_date?->format('Y-m-d'),
        ];
    }

    /**
     * Column headings
     */
    public function headings(): array
    {
        return [
            'ID Crédito',
            'Cliente',
            'Categoría',
            'Cobrador',
            'Monto Original',
            'Total con Interés',
            'Balance Pendiente',
            'Días de Mora',
            'Monto Vencido',
            'Cuotas Vencidas',
            '% Completado',
            'Frecuencia',
            'Fecha Inicio',
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
        return 'Reporte de Mora';
    }
}
