<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WaitingListExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return $this->credits;
    }

    /**
     * Map data for export
     */
    public function map($credit): array
    {
        return [
            $credit->id,
            $credit->client->name ?? 'N/A',
            $credit->createdBy?->name ?? 'N/A',
            $credit->amount,
            $credit->status_label ?? $credit->status,
            $credit->days_waiting ?? 0,
            $credit->created_at?->format('Y-m-d'),
            $credit->approved_at?->format('Y-m-d') ?? 'N/A',
            $credit->scheduled_delivery_date?->format('Y-m-d') ?? 'N/A',
            ($credit->is_overdue_delivery ?? false) ? 'Sí' : 'No',
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
            'Cobrador',
            'Monto',
            'Estado',
            'Días en Espera',
            'Fecha Creación',
            'Fecha Aprobación',
            'Entrega Programada',
            'Vencido para Entrega',
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
        return 'Lista de Espera';
    }
}
