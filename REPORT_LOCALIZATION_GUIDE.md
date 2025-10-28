# 🌐 Guía de Localización para Reportes

## Overview

Se ha implementado un sistema centralizado de traducción para que todos los reportes (PDF, HTML, Excel) muestren estados, frecuencias, métodos de pago y otros campos **completamente en español**.

---

## Archivos Creados

### 1. **ReportLocalizationHelper.php**
Ubicación: `app/Helpers/ReportLocalizationHelper.php`

Clase centralizada con todos los métodos de traducción:
- `creditStatus()` - Estados de crédito
- `frequency()` - Frecuencias de pago
- `paymentMethod()` - Métodos de pago
- `paymentStatus()` - Estados de pago
- `balanceStatus()` - Estados de balance
- `userCategory()` - Categorías de usuario
- `documentStatus()` - Estados de documentos
- `gender()` - Géneros
- `state()` - Estados/Provincias
- `term()` - Términos generales
- `translateField()` - Traducción genérica por campo

### 2. **BladeLocalizationHelper.php**
Ubicación: `app/Helpers/BladeLocalizationHelper.php`

Funciones globales para usar en vistas blade:
- `statusBadge()` - Badge coloreado con ícono
- `translate()` - Traducción genérica
- `label()` - Etiquetas traducidas
- Registra funciones globales: `creditStatus()`, `frequency()`, etc.

### 3. **AppServiceProvider.php**
Se agregó el registro de helpers globales en el método `boot()`.

---

## Cómo Usar

### En Vistas Blade (HTML/PDF)

#### Opción 1: Funciones Globales

```blade
<!-- Traducir estado de crédito -->
{{ creditStatus($credit->status) }}
<!-- Output: "Activo" en lugar de "active" -->

<!-- Traducir frecuencia -->
{{ frequency($credit->frequency) }}
<!-- Output: "Mensual" en lugar de "monthly" -->

<!-- Traducir método de pago -->
{{ paymentMethod($payment->method) }}
<!-- Output: "Efectivo" en lugar de "cash" -->

<!-- Badge coloreado -->
{{ statusBadge($credit->status) }}
<!-- Output: <span style="...">✅ Activo</span> -->

<!-- Obtener etiqueta -->
{{ label('credits') }}
<!-- Output: "Créditos" -->
```

#### Opción 2: Usar el Helper Directamente

```blade
@use('App\Helpers\ReportLocalizationHelper as Translate')

{{ Translate::creditStatus($credit->status) }}
{{ Translate::frequency($credit->frequency) }}
{{ Translate::paymentMethod($payment->method) }}
```

#### Opción 3: En Loops

```blade
@foreach($credits as $credit)
    <tr>
        <td>{{ $credit->id }}</td>
        <td>{{ $credit->client?->name }}</td>
        <td>{{ frequency($credit->frequency) }}</td>
        <td>{{ creditStatus($credit->status) }}</td>
        <td>Bs {{ number_format($credit->amount, 2) }}</td>
    </tr>
@endforeach
```

---

### En Clases Export (Excel)

Las exportaciones usan los datos del servicio que ya están transformados. Puedes agregar traducción adicional:

#### Opción 1: En el Constructor

```php
use App\Helpers\ReportLocalizationHelper;

class CreditsExport implements FromCollection, WithHeadings, WithColumnFormatting
{
    private Collection $data;
    private array $summary;

    public function __construct(Collection $data, array $summary)
    {
        // ✅ Traducir los datos si es necesario
        $this->data = $data->map(function ($item) {
            return [
                'id' => $item['id'],
                'cliente' => $item['client_name'],
                'monto' => $item['amount'],
                'estado' => ReportLocalizationHelper::creditStatus($item['status']),
                'frecuencia' => ReportLocalizationHelper::frequency($item['frequency']),
                // ... más campos
            ];
        });

        $this->summary = $summary;
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Cliente',
            'Monto',
            'Estado',
            'Frecuencia',
        ];
    }
}
```

#### Opción 2: En el Servicio (Recomendado)

Agregar la traducción directamente en el servicio al transformar los datos:

```php
use App\Helpers\ReportLocalizationHelper;

class CreditReportService
{
    private function transformCredits(Collection $credits): Collection
    {
        return $credits->map(function ($credit) {
            return [
                'id' => $credit->id,
                'client_name' => $credit->client?->name ?? 'N/A',
                // ✅ Usar las traducciones aquí
                'status_text' => ReportLocalizationHelper::creditStatus($credit->status),
                'frequency_text' => ReportLocalizationHelper::frequency($credit->frequency),
                // ... más campos
                '_model' => $credit,
            ];
        });
    }
}
```

---

## Traducciones Disponibles

### Estados de Crédito

| Código | Español |
|--------|---------|
| `pending_approval` | Pendiente de Aprobación |
| `waiting_delivery` | Esperando Entrega |
| `active` | Activo |
| `completed` | Completado |
| `defaulted` | Incumplido |
| `cancelled` | Cancelado |
| `rejected` | Rechazado |
| `on_hold` | En Espera |

### Frecuencias

| Código | Español |
|--------|---------|
| `daily` | Diaria |
| `weekly` | Semanal |
| `biweekly` | Quincenal |
| `monthly` | Mensual |
| `yearly` | Anual |
| `custom` | Personalizada |

### Métodos de Pago

| Código | Español |
|--------|---------|
| `cash` | Efectivo |
| `bank_transfer` | Transferencia Bancaria |
| `check` | Cheque |
| `credit_card` | Tarjeta de Crédito |
| `debit_card` | Tarjeta de Débito |
| `mobile_payment` | Pago Móvil |
| `other` | Otro |

### Estados de Pago

| Código | Español |
|--------|---------|
| `completed` | Completado |
| `current` | Al Día |
| `ahead` | Adelantado |
| `warning` | Retraso Bajo |
| `danger` | Retraso Alto |
| `overdue` | Vencido |
| `pending` | Pendiente |
| `cancelled` | Cancelado |

### Estados de Balance/Caja

| Código | Español |
|--------|---------|
| `open` | Abierta |
| `closed` | Cerrada |
| `pending_close` | Pendiente Cierre |
| `reconciled` | Conciliada |

### Categorías de Usuario

| Código | Español |
|--------|---------|
| `premium` | Premium |
| `standard` | Estándar |
| `economy` | Económica |
| `vip` | VIP |
| `bronze` | Bronce |
| `silver` | Plata |
| `gold` | Oro |
| `platinum` | Platino |

### Provincias de Venezuela

| Código | Español |
|--------|---------|
| `amazonas` | Amazonas |
| `anzoategui` | Anzoátegui |
| `apure` | Apure |
| `aragua` | Aragua |
| `barinas` | Barinas |
| `bolivar` | Bolívar |
| `carabobo` | Carabobo |
| ... (todas las 23 provincias) | ... |

---

## Ejemplos Completos

### Ejemplo 1: Actualizar Vista de Reporte de Créditos

**ANTES** (con valores en inglés):

```blade
@foreach($credits as $credit)
    <tr>
        <td>{{ $credit->id }}</td>
        <td>{{ $credit->client_name }}</td>
        <td>Bs {{ number_format($credit->amount, 2) }}</td>
        <td>{{ $credit->status }}</td>
        <td>{{ $credit->frequency }}</td>
    </tr>
@endforeach
```

**DESPUÉS** (completamente en español):

```blade
@foreach($credits as $credit)
    <tr>
        <td>{{ $credit->id }}</td>
        <td>{{ $credit->client_name }}</td>
        <td>Bs {{ number_format($credit->amount, 2) }}</td>
        <td>{{ creditStatus($credit->status) }}</td>
        <td>{{ frequency($credit->frequency) }}</td>
    </tr>
@endforeach
```

### Ejemplo 2: Actualizar Clase Export (Excel)

**ANTES**:

```php
public function headings(): array
{
    return [
        'ID',
        'Client',
        'Amount',
        'Status',
        'Frequency',
    ];
}
```

**DESPUÉS**:

```php
public function headings(): array
{
    return [
        'ID',
        'Cliente',
        'Monto',
        'Estado',
        'Frecuencia',
    ];
}
```

### Ejemplo 3: Badge con Color en HTML/PDF

```blade
<table>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Monto</th>
        </tr>
    </thead>
    <tbody>
        @foreach($credits as $credit)
            <tr>
                <td>{{ $credit->client_name }}</td>
                <td>{{ statusBadge($credit->status) }}</td>
                <td>Bs {{ number_format($credit->amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
```

Output:
```
Cliente          | Estado              | Monto
FERNANDO PINTO   | ✅ Activo          | Bs 1,000.00
CLIENTE TEST     | ⏳ Pendiente        | Bs 500.00
JUAN PÉREZ       | ❌ Incumplido       | Bs 2,000.00
```

---

## Agregando Nuevas Traducciones

### Para agregar una nueva traducción:

1. **Abre** `app/Helpers/ReportLocalizationHelper.php`

2. **Agrega un nuevo método**:

```php
/**
 * Nuevos Estados Personalizados
 */
public static function customStatus(?string $status): string
{
    return match ($status) {
        'in_progress' => 'En Progreso',
        'on_hold' => 'En Espera',
        'completed' => 'Completado',
        default => ucfirst($status ?? 'Desconocido'),
    };
}
```

3. **Agrégalo a `translateField()`** para acceso genérico:

```php
public static function translateField(string $field, mixed $value): string
{
    return match ($field) {
        // ... otros campos ...
        'custom_status' => self::customStatus($value),
        default => $value,
    };
}
```

4. **Úsalo en vistas**:

```blade
{{ customStatus($item->status) }}
<!-- O genéricamente -->
{{ translate('custom_status', $item->status) }}
```

---

## Testing

Verifica que las traducciones funcionen:

```php
use App\Helpers\ReportLocalizationHelper as Translate;

// Test directo
echo Translate::creditStatus('active'); // Output: "Activo"
echo Translate::frequency('monthly'); // Output: "Mensual"
echo Translate::paymentMethod('cash'); // Output: "Efectivo"

// Test en Blade (en cualquier vista)
{{ creditStatus('active') }} <!-- Output: "Activo" -->
```

---

## Ventajas del Sistema

✅ **Centralizado**: Un solo lugar para todas las traducciones
✅ **Reutilizable**: Funciona en HTML, PDF, Excel
✅ **Extensible**: Fácil agregar nuevas traducciones
✅ **Consistente**: Mismo valor en todos los reportes
✅ **Type-safe**: Usa match expressions en lugar de arrays
✅ **DRY**: No repites traducciones en varias vistas
✅ **Mantenible**: Cambios afectan todos los reportes

---

## Próximos Pasos

Para aplicar completamente al sistema:

1. **Actualizar vistas blade** de todos los reportes
   - resources/views/reports/credits.blade.php
   - resources/views/reports/payments.blade.php
   - resources/views/reports/balances.blade.php
   - etc.

2. **Actualizar servicios** para incluir campos traducidos
   - Agregar `status_text`, `frequency_text`, etc. a los DTOs

3. **Actualizar clases Export** para usar las traducciones
   - Headings traducidos
   - Datos traducidos

4. **Actualizar componentes blade** reutilizables
   - components/table.blade.php
   - components/summary-section.blade.php
   - etc.

---

## Resumen

El sistema de localización está completamente implementado y listo para usar. Solo necesitas:

1. ✅ Importar el helper en vistas
2. ✅ Usar las funciones globales (ya están registradas)
3. ✅ Aplicar a todas las vistas y exportaciones

**Resultado**: Todos los reportes mostrarán contenido completamente en español 🇻🇪
