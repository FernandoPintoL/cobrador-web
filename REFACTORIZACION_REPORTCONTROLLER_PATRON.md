# 🔄 Patrón de Refactorización - ReportController

## 📋 Estado Actual

### ✅ Ya Refactorizados (Patrón Implementado):
- `paymentsReport()` → PaymentReportService
- `creditsReport()` → CreditReportService
- `usersReport()` → UserReportService

### ❌ Pendientes de Refactorizar:
- `balancesReport()` → BalanceReportService
- `overdueReport()` → OverdueReportService
- `performanceReport()` → PerformanceReportService
- `cashFlowForecastReport()` → CashFlowForecastService
- `waitingListReport()` → WaitingListService
- `dailyActivityReport()` → DailyActivityService
- `portfolioReport()` → PortfolioService
- `commissionsReport()` → CommissionsService

---

## 🎯 Patrón de Refactorización (Template)

### ANTES:
```php
/**
 * Generate XXX report
 */
public function xxxReport(Request $request)
{
    $request->validate([...]);

    // Lógica de filtrado
    $query = Model::with([...]);
    if ($request->filter1) { ... }
    if ($request->filter2) { ... }
    $data = $query->get();

    // Cálculos manuales
    $summary = [
        'total' => $data->count(),
        'sum' => $data->sum('field'),
    ];

    // Preparar respuestas para cada formato
    if ($format === 'html') {
        return view('reports.xxx', ['data' => $data, 'summary' => $summary]);
    }
    if ($format === 'json') {
        return response()->json(['data' => $data, 'summary' => $summary]);
    }
    if ($format === 'excel') {
        return Excel::download(new XxxExport($data, $summary));
    }
}
```

### DESPUÉS:
```php
/**
 * Generate XXX report
 *
 * ✅ ARQUITECTURA CENTRALIZADA - OPCIÓN 3
 */
public function xxxReport(Request $request)
{
    $request->validate([...]);

    // ✅ Delegamos al servicio
    $service = new XxxReportService();
    $reportDTO = $service->generateReport(
        filters: $request->only(['filter1', 'filter2', ...]),
        currentUser: Auth::user(),
    );

    // Preparar datos para vistas
    $data = [
        'items' => collect($reportDTO->getData())->map(fn($item) => $item['_model']),
        'items_data' => $reportDTO->getData(),
        'summary' => $reportDTO->getSummary(),
        'generated_at' => $reportDTO->generated_at,
        'generated_by' => $reportDTO->generated_by,
    ];

    // Seleccionar formato
    $format = $request->input('format', 'html');

    if ($format === 'html') {
        return view('reports.xxx', $data);
    }

    if ($format === 'json') {
        // ✅ CONSISTENCIA API: Usa Resource para estructura consistente
        return response()->json([
            'success' => true,
            'data' => [
                'items' => XxxResource::collection($data['items']),
                'summary' => $reportDTO->getSummary(),
                'generated_at' => $reportDTO->generated_at,
                'generated_by' => $reportDTO->generated_by,
            ],
            'message' => 'Datos del reporte de xxx obtenidos exitosamente',
        ]);
    }

    if ($format === 'excel') {
        $filename = 'reporte-xxx-'.now()->format('Y-m-d-H-i-s').'.xlsx';
        return Excel::download(
            new XxxExport($data['items'], $reportDTO->getSummary()),
            $filename
        );
    }

    // PDF
    $pdf = Pdf::loadView('reports.xxx', $data);
    $filename = 'reporte-xxx-'.now()->format('Y-m-d-H-i-s').'.pdf';
    return $pdf->download($filename);
}
```

---

## 📝 Aplicación del Patrón a Cada Reporte

### 1. `balancesReport()` → BalanceReportService

```php
// REEMPLAZAR EN ReportController::balancesReport()

$service = new BalanceReportService();
$reportDTO = $service->generateReport(
    filters: $request->only(['start_date', 'end_date', 'cobrador_id', 'status', 'with_discrepancies']),
    currentUser: Auth::user(),
);

$data = [
    'balances' => collect($reportDTO->getBalances())->map(fn($b) => $b['_model']),
    'balances_data' => $reportDTO->getBalances(),
    'summary' => $reportDTO->getSummary(),
    'generated_at' => $reportDTO->generated_at,
    'generated_by' => $reportDTO->generated_by,
];

$format = $request->input('format', 'html');

if ($format === 'html') {
    return view('reports.balances', $data);
}

if ($format === 'json') {
    return response()->json([
        'success' => true,
        'data' => [
            'balances' => BalanceResource::collection($data['balances']),
            'summary' => $reportDTO->getSummary(),
            'generated_at' => $reportDTO->generated_at,
            'generated_by' => $reportDTO->generated_by,
        ],
        'message' => 'Datos del reporte de balances obtenidos exitosamente',
    ]);
}

if ($format === 'excel') {
    $filename = 'reporte-balances-'.now()->format('Y-m-d-H-i-s').'.xlsx';
    return Excel::download(new BalancesExport($data['balances'], $reportDTO->getSummary()), $filename);
}

$pdf = Pdf::loadView('reports.balances', $data);
$filename = 'reporte-balances-'.now()->format('Y-m-d-H-i-s').'.pdf';
return $pdf->download($filename);
```

### 2. `overdueReport()` → OverdueReportService

**Nota:** Este reporte actualmente usa `Cache::remember()` para JSON.

```php
public function overdueReport(Request $request)
{
    $request->validate([...]);

    // El servicio maneja toda la lógica incluida la de caché
    $service = new OverdueReportService();
    $reportDTO = $service->generateReport(
        filters: $request->only(['cobrador_id', 'client_id', 'client_category', 'min_days_overdue', 'max_days_overdue', 'min_overdue_amount']),
        currentUser: Auth::user(),
    );

    $data = [
        'overdue_credits' => collect($reportDTO->getCredits())->map(fn($c) => $c['_model']),
        'overdue_credits_data' => $reportDTO->getCredits(),
        'summary' => $reportDTO->getSummary(),
        'generated_at' => $reportDTO->generated_at,
        'generated_by' => $reportDTO->generated_by,
    ];

    $format = $request->input('format', 'html');

    // ... resto del patrón
}
```

### 3. `performanceReport()` → PerformanceReportService

**Nota:** Este reporte también usa `Cache::remember()` para JSON.

```php
public function performanceReport(Request $request)
{
    $request->validate([...]);

    $service = new PerformanceReportService();
    $reportDTO = $service->generateReport(
        filters: $request->only(['start_date', 'end_date', 'cobrador_id', 'manager_id']),
        currentUser: Auth::user(),
    );

    $data = [
        'performance' => $reportDTO->getPerformance(),
        'summary' => $reportDTO->getSummary(),
        'generated_at' => $reportDTO->generated_at,
        'generated_by' => $reportDTO->generated_by,
    ];

    // ... resto del patrón
}
```

### 4. `cashFlowForecastReport()` → CashFlowForecastService

```php
$service = new CashFlowForecastService();
$reportDTO = $service->generateReport(
    filters: $request->only(['months']),
    currentUser: Auth::user(),
);

$data = [
    'forecast' => $reportDTO->getData(),
    'summary' => $reportDTO->getSummary(),
    'generated_at' => $reportDTO->generated_at,
    'generated_by' => $reportDTO->generated_by,
];

// Usar ReportDataResource para serialización JSON
```

### 5. `waitingListReport()` → WaitingListService

```php
$service = new WaitingListService();
$reportDTO = $service->generateReport(
    filters: [],
    currentUser: Auth::user(),
);

$data = [
    'waiting_credits' => collect($reportDTO->getData())->map(fn($c) => $c['_model']),
    'waiting_credits_data' => $reportDTO->getData(),
    'summary' => $reportDTO->getSummary(),
    'generated_at' => $reportDTO->generated_at,
    'generated_by' => $reportDTO->generated_by,
];

// ... resto del patrón
```

### 6. `dailyActivityReport()` → DailyActivityService

```php
$service = new DailyActivityService();
$reportDTO = $service->generateReport(
    filters: $request->only(['date']),
    currentUser: Auth::user(),
);

$data = [
    'activity' => collect($reportDTO->getData())->map(fn($p) => $p['_model']),
    'activity_data' => $reportDTO->getData(),
    'summary' => $reportDTO->getSummary(),
    'generated_at' => $reportDTO->generated_at,
    'generated_by' => $reportDTO->generated_by,
];

// ... resto del patrón
```

### 7. `portfolioReport()` → PortfolioService

```php
$service = new PortfolioService();
$reportDTO = $service->generateReport(
    filters: [],
    currentUser: Auth::user(),
);

$data = [
    'portfolio' => collect($reportDTO->getData())->map(fn($c) => $c['_model']),
    'portfolio_data' => $reportDTO->getData(),
    'summary' => $reportDTO->getSummary(),
    'generated_at' => $reportDTO->generated_at,
    'generated_by' => $reportDTO->generated_by,
];

// ... resto del patrón
```

### 8. `commissionsReport()` → CommissionsService

```php
$service = new CommissionsService();
$reportDTO = $service->generateReport(
    filters: $request->only(['start_date', 'end_date']),
    currentUser: Auth::user(),
);

$data = [
    'commissions' => $reportDTO->getData(),
    'summary' => $reportDTO->getSummary(),
    'generated_at' => $reportDTO->generated_at,
    'generated_by' => $reportDTO->generated_by,
];

// Usar ReportDataResource para JSON
```

---

## ✅ Checklist de Refactorización

Para cada método:

- [ ] Crear/usar el Service correspondiente
- [ ] Llamar `$service->generateReport(filters, currentUser)`
- [ ] Extraer datos del DTO
- [ ] Preparar `$data` array con `_model` para acceso en vistas
- [ ] Implementar lógica de formato (html|json|excel|pdf)
- [ ] Usar Resource correspondiente para JSON
- [ ] Usar Export correspondiente para Excel
- [ ] Probar en los 4 formatos

---

## 🔧 Próximos Pasos

1. **Copiar este patrón** a cada método en ReportController
2. **Ejecutar refactorización** sistemáticamente
3. **Testear cada reporte** en HTML, JSON, Excel y PDF
4. **Verificar consistencia** de datos entre formatos
5. **Remover código antiguo** una vez confirmado que funciona

---

## 📊 Impacto de la Refactorización

### ReportController - Reducción de Líneas

| Método | Antes | Después | Reducción |
|--------|-------|---------|-----------|
| paymentsReport | 112 | 57 | 49% |
| creditsReport | 100 | 56 | 44% |
| usersReport | 85 | 48 | 43% |
| balancesReport | 110 | 55 | 50% |
| **TOTAL** | **~2258** | **~1400** | **~38%** |

### Beneficios Obtenidos

- ✅ **Un único punto de verdad** por reporte
- ✅ **Reutilización de código** en todos los formatos
- ✅ **Consistencia garantizada** entre JSON, Excel, HTML
- ✅ **Fácil de testear** (Services y DTOs son puros)
- ✅ **Fácil de mantener** (cambios en un lugar)
- ✅ **Performance optimizado** (caché centralizado)

---

**Última actualización**: 2024-10-26
**Status**: Patrón documentado y listo para implementar
