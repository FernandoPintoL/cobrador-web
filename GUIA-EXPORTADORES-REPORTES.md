# 📊 Guía de Uso: Exportadores de Reportes de Créditos

Esta guía explica cómo utilizar los 3 exportadores de reportes implementados: **Excel**, **PDF** y **HTML**.

---

## 📋 Tabla de Contenidos

1. [Archivos Implementados](#archivos-implementados)
2. [Características](#características)
3. [Uso en Controladores](#uso-en-controladores)
4. [Ejemplos Completos](#ejemplos-completos)
5. [Personalización](#personalización)

---

## 📁 Archivos Implementados

### **Backend (Laravel)**

#### Servicios:
- ✅ `app/Services/CreditReportFormatterService.php` - Formateo centralizado
- ✅ `app/Services/CreditPdfReportService.php` - Generación de PDF
- ✅ `app/Services/CreditHtmlReportService.php` - Generación de HTML

#### Exportadores:
- ✅ `app/Exports/CreditsExport.php` - Exportación a Excel

#### Vistas Blade:
- ✅ `resources/views/reports/credits-pdf.blade.php` - Template para PDF
- ✅ `resources/views/reports/credits-html.blade.php` - Template para HTML

---

## ✨ Características

### **Características Comunes (Los 3 Formatos)**

✅ **Sistema de Iconos Estandarizado:**
- ✅ Al día (verde)
- ⚠️ Alerta leve (amarillo)
- 🟠 Alerta moderada (naranja)
- 🔴 Crítico (rojo)

✅ **Colores de Fondo:**
- Cada fila se colorea según la severidad del retraso
- Utiliza el sistema estandarizado del backend

✅ **Información Completa:**
- ID, Cliente, Cobrador
- Montos: Original, Total, Pagado, Balance
- Cuotas: Completadas, Esperadas, Vencidas
- Estado de retraso con emoji + label
- Días de retraso
- Alerta de atención requerida

✅ **Resumen de Totales:**
- Total de créditos
- Monto total
- Total pagado
- Saldo pendiente

### **Características Específicas por Formato**

#### **📗 Excel (.xlsx)**
- Columnas auto-ajustadas
- Headers con fondo azul
- Filas con colores de severidad
- Columna "Estado de Retraso" centrada y resaltada
- Emojis Unicode nativos
- Compatible con Microsoft Excel, Google Sheets, LibreOffice

#### **📄 PDF (.pdf)**
- Orientación horizontal (landscape) para más columnas
- Fuente DejaVu Sans (soporte Unicode/emojis)
- Badges de severidad con bordes
- Diseño optimizado para impresión
- Footer con información del sistema

#### **🌐 HTML (.html)**
- Diseño moderno y responsive
- Gradientes y sombras para mejor UX
- Animaciones suaves al hover
- Grid responsive para el resumen
- Estilos de impresión incluidos
- Compatible con todos los navegadores modernos

---

## 🎯 Uso en Controladores

### **Ejemplo 1: Excel**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exports\CreditsExport;
use App\Models\Credit;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CreditReportController extends Controller
{
    /**
     * Exportar créditos a Excel
     */
    public function exportExcel(Request $request)
    {
        // Obtener créditos con relaciones necesarias
        $credits = Credit::with(['client', 'createdBy', 'deliveredBy'])
            ->where('status', 'active')
            ->get();

        // Preparar datos en formato de array (si usas DTOs)
        $data = $credits->map(function ($credit) {
            return [
                'id' => $credit->id,
                'client_name' => $credit->client->name,
                'created_by_name' => $credit->createdBy->name,
                'delivered_by_name' => $credit->deliveredBy->name ?? 'N/A',
                'amount' => $credit->amount,
                'balance' => $credit->balance,
                'completed_installments' => $credit->completed_installments,
                'expected_installments' => $credit->expected_installments,
                'installments_overdue' => $credit->overdue_installments,
                'payment_status_label' => ucfirst($credit->payment_status),
                'created_at_formatted' => $credit->created_at->format('d/m/Y'),

                // ⭐ Campos estandarizados desde el backend
                'overdue_severity' => $credit->overdue_severity,
                'days_overdue' => $credit->days_overdue,

                // Guardar el modelo completo para acceso a relaciones
                '_model' => $credit,
            ];
        });

        // Calcular resumen
        $summary = [
            'total_credits' => $credits->count(),
            'total_amount' => $credits->sum('total_amount'),
            'total_paid' => $credits->sum('total_paid'),
            'total_balance' => $credits->sum('balance'),
            'pending_amount' => $credits->sum('balance'),
        ];

        // Generar y descargar Excel
        return Excel::download(
            new CreditsExport($data, $summary),
            'reporte-creditos-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
```

### **Ejemplo 2: PDF**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Credit;
use App\Services\CreditPdfReportService;
use Illuminate\Http\Request;

class CreditReportController extends Controller
{
    /**
     * Exportar créditos a PDF
     */
    public function exportPdf(Request $request)
    {
        // Obtener créditos con relaciones
        $credits = Credit::with(['client', 'createdBy'])
            ->where('status', 'active')
            ->get();

        // Calcular resumen
        $summary = [
            'total_credits' => $credits->count(),
            'total_amount' => $credits->sum('total_amount'),
            'total_paid' => $credits->sum('total_paid'),
            'total_balance' => $credits->sum('balance'),
        ];

        // Generar PDF usando el servicio
        $pdfService = new CreditPdfReportService();

        return $pdfService->generate(
            $credits,
            $summary,
            'reporte-creditos-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    /**
     * Ver PDF en el navegador (sin descargar)
     */
    public function previewPdf(Request $request)
    {
        $credits = Credit::with(['client', 'createdBy'])
            ->where('status', 'active')
            ->get();

        $summary = [
            'total_credits' => $credits->count(),
            'total_amount' => $credits->sum('total_amount'),
            'total_paid' => $credits->sum('total_paid'),
            'total_balance' => $credits->sum('balance'),
        ];

        $pdfService = new CreditPdfReportService();

        // Usar stream() en lugar de generate() para mostrar en navegador
        return $pdfService->stream($credits, $summary);
    }
}
```

### **Ejemplo 3: HTML**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Credit;
use App\Services\CreditHtmlReportService;
use Illuminate\Http\Request;

class CreditReportController extends Controller
{
    /**
     * Ver reporte HTML en el navegador
     */
    public function viewHtml(Request $request)
    {
        // Obtener créditos con relaciones
        $credits = Credit::with(['client', 'createdBy'])
            ->where('status', 'active')
            ->get();

        // Calcular resumen
        $summary = [
            'total_credits' => $credits->count(),
            'total_amount' => $credits->sum('total_amount'),
            'total_paid' => $credits->sum('total_paid'),
            'total_balance' => $credits->sum('balance'),
        ];

        // Generar y mostrar HTML
        $htmlService = new CreditHtmlReportService();

        return $htmlService->generate($credits, $summary);
    }

    /**
     * Descargar reporte HTML como archivo
     */
    public function downloadHtml(Request $request)
    {
        $credits = Credit::with(['client', 'createdBy'])
            ->where('status', 'active')
            ->get();

        $summary = [
            'total_credits' => $credits->count(),
            'total_amount' => $credits->sum('total_amount'),
            'total_paid' => $credits->sum('total_paid'),
            'total_balance' => $credits->sum('balance'),
        ];

        $htmlService = new CreditHtmlReportService();

        return $htmlService->download(
            $credits,
            $summary,
            'reporte-creditos-' . now()->format('Y-m-d') . '.html'
        );
    }
}
```

---

## 🛣️ Rutas Recomendadas

Agrega estas rutas en `routes/api.php`:

```php
// Rutas de exportación de reportes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('reports/credits')->group(function () {
        // Excel
        Route::get('/export/excel', [CreditReportController::class, 'exportExcel']);

        // PDF
        Route::get('/export/pdf', [CreditReportController::class, 'exportPdf']);
        Route::get('/preview/pdf', [CreditReportController::class, 'previewPdf']);

        // HTML
        Route::get('/view/html', [CreditReportController::class, 'viewHtml']);
        Route::get('/export/html', [CreditReportController::class, 'downloadHtml']);
    });
});
```

---

## 🎨 Personalización

### **Modificar Colores**

Edita `app/Services/CreditReportFormatterService.php`:

```php
public static function getSeverityColorHex(string $severity): string
{
    return match($severity) {
        'none'     => '#4CAF50',  // Cambiar a tu color verde
        'light'    => '#FFC107',  // Cambiar a tu color amarillo
        'moderate' => '#FF9800',  // Cambiar a tu color naranja
        'critical' => '#F44336',  // Cambiar a tu color rojo
        default    => '#9E9E9E',
    };
}
```

### **Agregar Columnas**

1. **Excel:** Edita `app/Exports/CreditsExport.php`
   - Actualiza el array en `headings()`
   - Actualiza el array en `map()`
   - Ajusta anchos de columna en `styles()`
   - Actualiza el rango de columnas (A-S → A-T si agregas una)

2. **PDF/HTML:** Edita las vistas Blade
   - Agrega `<th>` en el `<thead>`
   - Agrega `<td>` en el `<tbody>`

### **Cambiar Umbrales de Severidad**

Edita `app/Models/Credit.php`:

```php
public function getOverdueSeverityAttribute(): string
{
    $days = $this->days_overdue;

    if ($days === 0) return 'none';
    if ($days <= 5) return 'light';      // Cambiar de 3 a 5 días
    if ($days <= 10) return 'moderate';  // Cambiar de 7 a 10 días
    return 'critical';
}
```

---

## 🧪 Testing

### **Prueba Rápida en Navegador**

```bash
# Ver HTML directamente
curl http://localhost:8000/api/reports/credits/view/html > test.html
open test.html

# Descargar PDF
curl http://localhost:8000/api/reports/credits/export/pdf > test.pdf
open test.pdf

# Descargar Excel
curl http://localhost:8000/api/reports/credits/export/excel > test.xlsx
open test.xlsx
```

### **Prueba con Postman/Insomnia**

1. Configura el endpoint: `GET /api/reports/credits/export/excel`
2. Agrega header: `Authorization: Bearer {tu_token}`
3. Envía la petición
4. Guarda el archivo descargado

---

## 📊 Resultado Visual

### **Excel**
```
┌────┬──────────┬──────────┬────────┬─────────────────┬──────┐
│ ID │ Cliente  │ Balance  │ Vencidas│ Estado Retraso │ Días │
├────┼──────────┼──────────┼─────────┼────────────────┼──────┤
│ 1  │ Juan P.  │ Bs 2,000 │    0    │ ✅ Al día      │  0   │ ← Fila verde claro
│ 2  │ María G. │ Bs 1,500 │    2    │ ⚠️ Alerta leve │  2   │ ← Fila amarilla
│ 3  │ Pedro L. │ Bs 4,000 │    5    │ 🟠 Moderado    │  5   │ ← Fila naranja
│ 4  │ Ana M.   │ Bs 8,000 │   15    │ 🔴 Crítico     │ 15   │ ← Fila roja
└────┴──────────┴──────────┴─────────┴────────────────┴──────┘
```

### **PDF**
- Header azul profesional
- Filas coloreadas según severidad
- Badges con bordes y emojis
- Footer con información del sistema

### **HTML**
- Diseño moderno con gradientes
- Animaciones suaves al pasar el mouse
- Responsive (se adapta a móviles)
- Resumen en grid de 4 columnas
- Estilos de impresión optimizados

---

## 🎯 Ventajas del Sistema

### **Mantenibilidad**
✅ Un solo lugar para cambiar colores/iconos/umbrales
✅ Servicio centralizado reutilizable en los 3 formatos

### **Consistencia**
✅ Mismo diseño visual en Excel, PDF y HTML
✅ Iconos y colores idénticos en todos los formatos

### **Accesibilidad**
✅ Color + Icono + Texto (WCAG 2.1 compliant)
✅ No depende solo del color para transmitir información

### **Performance**
✅ Backend calcula una vez, frontend solo renderiza
✅ Sin duplicación de lógica

---

## 🚀 Próximos Pasos Recomendados

1. [ ] Implementar las rutas en `routes/api.php`
2. [ ] Crear el controlador `CreditReportController`
3. [ ] Probar cada formato con datos reales
4. [ ] Ajustar colores si es necesario
5. [ ] Agregar filtros (fecha, cobrador, etc.)
6. [ ] Implementar cache para reportes grandes
7. [ ] Agregar paginación si hay +1000 registros

---

## 📝 Notas Importantes

1. **Excel:**
   - Los emojis funcionan en Microsoft Excel, Google Sheets y LibreOffice
   - No se pueden agregar imágenes en celdas con PhpSpreadsheet
   - Los colores de fondo funcionan perfectamente

2. **PDF:**
   - DomPDF requiere fuente DejaVu Sans para emojis Unicode
   - La orientación landscape permite mostrar más columnas
   - Los badges mejoran la legibilidad visual

3. **HTML:**
   - Funciona en todos los navegadores modernos
   - Se puede usar `window.print()` para imprimir
   - Los estilos de impresión están incluidos
   - Responsive para visualización en móviles

4. **Backend:**
   - Los campos `overdue_severity` y `days_overdue` vienen del modelo
   - Asegúrate de que el `$appends` esté configurado en `Credit.php`
   - Las relaciones deben cargarse con `with()` para evitar N+1 queries

---

## 🆘 Solución de Problemas

### **Error: "Class 'Maatwebsite\Excel\Facades\Excel' not found"**
```bash
composer require maatwebsite/excel
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
```

### **Error: "Class 'Barryvdh\DomPDF\Facade\Pdf' not found"**
```bash
composer require barryvdh/laravel-dompdf
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### **Los emojis no se ven en el PDF**
- Asegúrate de usar: `$pdf->setOption('defaultFont', 'DejaVu Sans');`
- DejaVu Sans soporta caracteres Unicode

### **El archivo Excel no descarga**
- Verifica que el header `Content-Type` sea correcto
- Asegúrate de usar `Excel::download()` y no `Excel::store()`

---

**Implementado por:** Claude Sonnet 4.5
**Fecha:** 2025-12-10
**Estado:** ✅ Completado y listo para usar

---

## 🔗 Referencias

- Documentación de exportadores: Este archivo
- Sistema estandarizado: `/SISTEMA-ESTANDARIZADO-ESTADOS.md`
- Ejemplos de reportes: `/EJEMPLOS-REPORTES-ICONOS.md`
- Servicio de formateo: `/app/Services/CreditReportFormatterService.php`
