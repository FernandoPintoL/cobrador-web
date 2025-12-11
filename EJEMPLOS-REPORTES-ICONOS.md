# 📊 Ejemplos: Iconos en Reportes PDF y Excel

Guía completa para implementar iconos en reportes exportados.

---

## 📗 EXCEL - Ejemplo Completo

### **Opción 1: Con Emojis y Colores (Recomendado)**

```php
<?php

namespace App\Exports;

use App\Models\Credit;
use App\Services\CreditReportFormatterService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CreditsExport implements FromCollection, WithHeadings, WithStyles, WithEvents
{
    protected $credits;

    public function __construct($credits)
    {
        $this->credits = $credits;
    }

    public function collection()
    {
        return $this->credits->map(function ($credit) {
            $formatter = CreditReportFormatterService::formatForExcel($credit);

            return [
                'Cliente'    => $credit->client->name,
                'CI'         => $credit->client->ci,
                'Monto'      => 'Bs. ' . number_format($credit->amount, 2),
                'Balance'    => 'Bs. ' . number_format($credit->balance, 2),
                'Estado'     => $formatter['text'],  // ✅ Al día
                'Días'       => $formatter['days'],
                'Requiere Atención' => $credit->requires_attention ? 'Sí' : 'No',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'CI',
            'Monto',
            'Balance',
            'Estado de Retraso',
            'Días de Retraso',
            'Requiere Atención',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo de headers
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2196F3'],
            ],
            'font' => [
                'color' => ['argb' => 'FFFFFFFF'],
            ],
        ]);

        // Auto-ajustar columnas
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = 2; // Empezar después del header

                foreach ($this->credits as $credit) {
                    $formatter = CreditReportFormatterService::formatForExcel($credit);

                    // Aplicar color de fondo a la columna "Estado"
                    $sheet->getStyle('E' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $formatter['bg_color']],
                        ],
                        'font' => [
                            'bold' => true,
                            'color' => ['argb' => $formatter['text_color']],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);

                    // Resaltar "Requiere Atención" si es true
                    if ($credit->requires_attention) {
                        $sheet->getStyle('G' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFFFEBEE'],
                            ],
                            'font' => [
                                'bold' => true,
                                'color' => ['argb' => 'FFD32F2F'],
                            ],
                        ]);
                    }

                    $row++;
                }
            },
        ];
    }
}
```

**Uso:**
```php
// En tu controlador
public function exportExcel(Request $request)
{
    $credits = Credit::with('client')
        ->where('status', 'active')
        ->get();

    return Excel::download(new CreditsExport($credits), 'creditos.xlsx');
}
```

**Resultado en Excel:**
```
┌──────────────┬──────────┬──────────┬──────────┬───────────────┬──────┬──────────────┐
│ Cliente      │ CI       │ Monto    │ Balance  │ Estado        │ Días │ Req. Atención│
├──────────────┼──────────┼──────────┼──────────┼───────────────┼──────┼──────────────┤
│ Juan Pérez   │ 12345678 │ Bs 5,000 │ Bs 2,000 │ ✅ Al día     │  0   │ No           │
│              │          │          │          │ (fondo verde) │      │              │
├──────────────┼──────────┼──────────┼──────────┼───────────────┼──────┼──────────────┤
│ María García │ 87654321 │ Bs 3,000 │ Bs 1,500 │ ⚠️ Alerta leve│  2   │ No           │
│              │          │          │          │ (fondo amarillo)│    │              │
├──────────────┼──────────┼──────────┼──────────┼───────────────┼──────┼──────────────┤
│ Pedro López  │ 11223344 │ Bs 7,000 │ Bs 4,000 │ 🟠 Moderado   │  5   │ Sí           │
│              │          │          │          │ (fondo naranja)│     │ (rojo)       │
├──────────────┼──────────┼──────────┼──────────┼───────────────┼──────┼──────────────┤
│ Ana Martínez │ 44332211 │ Bs10,000 │ Bs 8,000 │ 🔴 Crítico    │ 15   │ Sí           │
│              │          │          │          │ (fondo rojo)  │      │ (rojo)       │
└──────────────┴──────────┴──────────┴──────────┴───────────────┴──────┴──────────────┘
```

---

## 📄 PDF - Ejemplo Completo

### **Con DomPDF (Recomendado)**

```php
<?php

namespace App\Services;

use App\Models\Credit;
use Barryvdh\DomPDF\Facade\Pdf;

class CreditPdfReportService
{
    public function generate($credits)
    {
        $data = [
            'credits' => $credits,
            'formatter' => CreditReportFormatterService::class,
            'generated_at' => now()->format('d/m/Y H:i:s'),
        ];

        $pdf = Pdf::loadView('reports.credits-pdf', $data);

        return $pdf->download('reporte-creditos.pdf');
    }
}
```

**Vista Blade:** `resources/views/reports/credits-pdf.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Créditos</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background-color: #2196F3;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        /* Estilos de severidad */
        .severity-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: bold;
            text-align: center;
        }

        .severity-none {
            background-color: #E8F5E9;
            color: #4CAF50;
            border: 1px solid #4CAF50;
        }

        .severity-light {
            background-color: #FFF9C4;
            color: #F57C00;
            border: 1px solid #FFC107;
        }

        .severity-moderate {
            background-color: #FFE0B2;
            color: #E65100;
            border: 1px solid #FF9800;
        }

        .severity-critical {
            background-color: #FFCDD2;
            color: #C62828;
            border: 1px solid #F44336;
        }

        .alert-badge {
            background-color: #FFEBEE;
            color: #D32F2F;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Créditos</h1>
        <p>Generado el: {{ $generated_at }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>CI</th>
                <th>Monto</th>
                <th>Balance</th>
                <th>Estado de Retraso</th>
                <th>Días</th>
                <th>Alerta</th>
            </tr>
        </thead>
        <tbody>
            @foreach($credits as $credit)
                @php
                    $format = $formatter::formatForPDF($credit);
                @endphp
                <tr>
                    <td>{{ $credit->client->name }}</td>
                    <td>{{ $credit->client->ci }}</td>
                    <td>Bs. {{ number_format($credit->amount, 2) }}</td>
                    <td>Bs. {{ number_format($credit->balance, 2) }}</td>
                    <td>
                        <span class="severity-badge severity-{{ $credit->overdue_severity }}">
                            {{ $format['emoji'] }} {{ $format['label'] }}
                        </span>
                    </td>
                    <td style="text-align: center;">{{ $format['days'] }}</td>
                    <td style="text-align: center;">
                        @if($credit->requires_attention)
                            <span class="alert-badge">⚠️ ATENCIÓN</span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Este reporte contiene {{ count($credits) }} créditos</p>
        <p>Sistema de Gestión de Créditos - Cobrador v1.0</p>
    </div>
</body>
</html>
```

**Uso:**
```php
// En tu controlador
public function exportPdf(Request $request)
{
    $credits = Credit::with('client')
        ->where('status', 'active')
        ->get();

    $service = new CreditPdfReportService();
    return $service->generate($credits);
}
```

**Resultado en PDF:**

```
╔════════════════════════════════════════════════════════════════════════╗
║                     REPORTE DE CRÉDITOS                                ║
║                   Generado el: 10/12/2024 14:30                        ║
╚════════════════════════════════════════════════════════════════════════╝

┌──────────────┬──────────┬──────────┬──────────┬───────────────┬──────┬──────────┐
│ Cliente      │ CI       │ Monto    │ Balance  │ Estado        │ Días │ Alerta   │
├──────────────┼──────────┼──────────┼──────────┼───────────────┼──────┼──────────┤
│ Juan Pérez   │ 12345678 │ Bs 5,000 │ Bs 2,000 │ ✅ Al día     │  0   │    -     │
│              │          │          │          │ [Verde]       │      │          │
│              │          │          │          │               │      │          │
│ María García │ 87654321 │ Bs 3,000 │ Bs 1,500 │ ⚠️ Alerta leve│  2   │    -     │
│              │          │          │          │ [Amarillo]    │      │          │
│              │          │          │          │               │      │          │
│ Pedro López  │ 11223344 │ Bs 7,000 │ Bs 4,000 │ 🟠 Moderado   │  5   │⚠️ATENCIÓN│
│              │          │          │          │ [Naranja]     │      │  [Rojo]  │
│              │          │          │          │               │      │          │
│ Ana Martínez │ 44332211 │ Bs10,000 │ Bs 8,000 │ 🔴 Crítico    │ 15   │⚠️ATENCIÓN│
│              │          │          │          │ [Rojo]        │      │  [Rojo]  │
└──────────────┴──────────┴──────────┴──────────┴───────────────┴──────┴──────────┘

Este reporte contiene 4 créditos
Sistema de Gestión de Créditos - Cobrador v1.0
```

---

## 🎨 COMPARACIÓN VISUAL

### **Lo que verá el usuario:**

| Formato | Iconos | Colores | Emojis | Recomendación |
|---------|--------|---------|--------|---------------|
| **Excel** | ❌ No nativos | ✅ Sí (fondo + texto) | ✅ Sí (Unicode) | ⭐⭐⭐⭐⭐ Emoji + Color |
| **PDF** | ✅ Sí (SVG/PNG) | ✅ Sí (fondo + borde) | ✅ Sí (Unicode) | ⭐⭐⭐⭐⭐ Emoji + Color + Badge |
| **CSV** | ❌ No | ❌ No | ⚠️ Depende | ⭐⭐ Solo texto |

---

## 📝 RESUMEN

### **✅ Excel: Usar Emojis + Colores de fondo**
```php
// En cada fila:
'Estado' => '✅ Al día'           // Emoji + texto
// + Color de fondo verde claro
// + Texto verde oscuro
```

### **✅ PDF: Usar Emojis + Badges con colores**
```html
<span class="severity-badge severity-moderate">
    🟠 Alerta moderada
</span>
```

### **✅ Beneficios:**
- 🎨 **Visual:** Fácil de escanear visualmente
- ♿ **Accesible:** Emoji + Color + Texto
- 📱 **Universal:** Funciona en todos los dispositivos
- 🖨️ **Imprimible:** Se mantiene en blanco/negro

---

## 🚀 IMPLEMENTACIÓN RÁPIDA

1. **Copiar el servicio:** `CreditReportFormatterService.php` ✅ Ya está creado
2. **Actualizar exportador Excel:** Usar `formatForExcel()`
3. **Actualizar generador PDF:** Usar `formatForPDF()`
4. **Probar descarga:** Verificar que se vean bien

---

**¿Necesitas que implemente alguno de estos exportadores completos?** 🚀
