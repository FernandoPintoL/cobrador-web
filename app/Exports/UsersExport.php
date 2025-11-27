<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $query;

    protected $summary;

    public function __construct($query, $summary)
    {
        $this->query = $query;
        $this->summary = $summary;
    }

    public function collection(): Collection
    {
        // ✅ Si ya es Collection (datos del Service), extraer _model
        // Si es Query Builder (legacy), ejecutar get()
        if ($this->query instanceof Collection) {
            return $this->query->map(fn($item) =>
                is_array($item) && isset($item['_model']) ? $item['_model'] : $item
            );
        }

        return $this->query->with(['roles', 'assignedManager'])->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Email',
            'Rol',
            'Manager',
            'Estado',
            'Fecha de Creación',
            'Último Acceso',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->roles->pluck('name')->join(', ') ?? 'N/A',
            $user->assignedManager->name ?? 'N/A',
            $user->status ?? 'Activo',
            $user->created_at->format('d/m/Y'),
            $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Nunca',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para el encabezado
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Agregar fila de resumen al final
        $lastRow = $sheet->getHighestRow() + 2;
        $sheet->setCellValue('A'.$lastRow, 'RESUMEN');
        $sheet->setCellValue('B'.$lastRow, 'Total de Usuarios: '.$this->summary['total_users']);
        $sheet->setCellValue('C'.$lastRow, 'Cobradores: '.$this->summary['cobradores_count']);
        $sheet->setCellValue('D'.$lastRow, 'Managers: '.$this->summary['managers_count']);

        // Estilo para la fila de resumen
        $sheet->getStyle('A'.$lastRow.':D'.$lastRow)->applyFromArray([
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
}
